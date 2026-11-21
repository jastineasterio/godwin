<?php

namespace Tests\Feature;

use App\Models\SmsMessage;
use App\Models\SmsProviderConfig;
use App\Models\User;
use App\Enums\UserRole;
use App\Services\Sms\Providers\DarsmsProvider;
use App\Services\Sms\SmsMessageService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ambiguous SMS timeout and duplicate-prevention tests.
 *
 * No live SMS is sent. Uses mocked provider requests.
 */
class SmsTimeoutHandlingTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedProvider();
    }

    public function test_ambiguous_timeout_records_submission_unknown_not_failed(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out while awaiting response.');
        });

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'timeout-key',
            'timeout' => 1,
        ]);

        $result = $provider->send('255712345678', 'Hello');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['ambiguous'] ?? false);
        $this->assertNull($result['messageId']);
    }

    public function test_service_records_submission_unknown_for_ambiguous_send(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out.');
        });

        $user = $this->createUser(UserRole::HeadOfSchool);
        $service = app(SmsMessageService::class);

        $result = $service->send('255712345678', 'Ambiguous message', ['user' => $user]);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['ambiguous'] ?? false);

        $message = SmsMessage::find($result['internal_id']);
        $this->assertNotNull($message);
        $this->assertEquals('submission_unknown', $message->status);
        $this->assertNull($message->provider_message_id);
        $this->assertNotNull($message->error_message);
        $this->assertArrayHasKey('provider_request', $message->metadata);
    }

    public function test_definite_provider_rejection_marks_failed(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'message' => 'Invalid phone number.',
            ], 400),
        ]);

        $user = $this->createUser(UserRole::HeadOfSchool);
        $service = app(SmsMessageService::class);

        $result = $service->send('invalid', 'Hello', ['user' => $user]);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['ambiguous'] ?? false);

        $message = SmsMessage::find($result['internal_id']);
        $this->assertEquals('failed', $message->status);
        $this->assertNotNull($message->error_message);
    }

    public function test_ordinary_network_failure_before_submission_is_ambiguous(): void
    {
        Http::fake(function () {
            throw new ConnectionException('DNS resolution failed.');
        });

        $user = $this->createUser(UserRole::HeadOfSchool);
        $service = app(SmsMessageService::class);

        $result = $service->send('255712345678', 'Network failure', ['user' => $user]);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['ambiguous'] ?? false);

        $message = SmsMessage::find($result['internal_id']);
        $this->assertEquals('submission_unknown', $message->status);
        // Not marked delivered or definitely failed.
        $this->assertNotEquals('delivered', $message->status);
        $this->assertNotEquals('failed', $message->status);
    }

    public function test_provider_message_id_used_for_status_checking_when_available(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['messageIds' => ['msg_provider_1'], 'batchId' => 'batch_1'],
            ], 202),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'msgid-key',
        ]);

        $result = $provider->send('255712345678', 'With message id');

        $this->assertTrue($result['success']);
        $this->assertEquals('msg_provider_1', $result['messageId']);
    }

    public function test_manual_resend_of_submission_unknown_message(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $original = SmsMessage::create([
            'to' => '255712345678',
            'message' => 'Resend me',
            'category' => 'general',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider unavailable.',
            'metadata' => [],
        ]);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['messageIds' => ['msg_resend_1'], 'batchId' => 'batch_resend'],
            ], 202),
        ]);

        $service = app(SmsMessageService::class);
        $result = $service->resendMessage($original, $admin, 'Investigated: provider timeout confirmed.');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['idempotent'] ?? false);
        $this->assertEquals('msg_resend_1', $result['messageId']);

        // Delivery event recorded on the original message.
        $this->assertDatabaseHas('sms_delivery_events', [
            'sms_message_id' => $original->id,
            'error_message' => 'Manual resend: Investigated: provider timeout confirmed.',
        ]);
    }

    public function test_manual_resend_is_idempotent_prevents_duplicates(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $original = SmsMessage::create([
            'to' => '255712345679',
            'message' => 'Idempotent resend',
            'category' => 'general',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider unavailable.',
            'metadata' => [],
        ]);

        $sendCount = 0;
        Http::fake(function () use (&$sendCount) {
            $sendCount++;

            return Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['messageIds' => ['msg_idem_'.$sendCount]],
            ], 202);
        });

        $service = app(SmsMessageService::class);

        $first = $service->resendMessage($original, $admin, 'First manual resend.');
        $second = $service->resendMessage($original, $admin, 'Second manual resend (should be blocked).');

        $this->assertTrue($first['success']);
        $this->assertFalse($first['idempotent'] ?? false);
        $this->assertTrue($second['idempotent'] ?? false, 'Second resend should be idempotent and not create a duplicate');
        $this->assertEquals(1, $sendCount, 'Provider must only be called once thanks to idempotency');
    }

    public function test_non_authorized_user_cannot_resend(): void
    {
        $teacher = $this->createUser(UserRole::Teacher);

        $original = SmsMessage::create([
            'to' => '255712345680',
            'message' => 'No resend',
            'category' => 'general',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider unavailable.',
            'metadata' => [],
        ]);

        $service = app(SmsMessageService::class);
        $result = $service->resendMessage($original, $teacher, 'Unauthorized attempt.');

        $this->assertFalse($result['success']);
        $this->assertEquals('unauthorized', $result['code']);
    }

    public function test_sent_or_delivered_message_is_not_resendable(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $delivered = SmsMessage::create([
            'to' => '255712345681',
            'message' => 'Already delivered',
            'category' => 'general',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => 'msg_delivered',
            'provider_status' => 'DELIVERED',
            'status' => 'delivered',
            'metadata' => [],
        ]);

        $service = app(SmsMessageService::class);
        $result = $service->resendMessage($delivered, $admin, 'Should be blocked.');

        $this->assertFalse($result['success']);
        $this->assertEquals('not_resendable', $result['code']);
    }

    // ------------------------------------------------------------------
    // DARSMS send-response parsing (the original failure was an
    // "Undefined array key messageIds" ErrorException thrown while parsing
    // a successful HTTP 202 response that lacked a messageIds key).
    // ------------------------------------------------------------------

    /** @test */
    public function accepted_202_without_message_ids_does_not_throw_and_is_queued(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['batchId' => 'batch_no_ids', 'queued' => 1, 'senderId' => 'DARSMS'],
                // deliberately no messageIds / messageId key
            ], 202),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'no-ids-key',
        ]);

        $result = $provider->send('255712345678', 'No message id key');

        $this->assertTrue($result['success']);
        $this->assertNull($result['messageId']);
        $this->assertEquals([], $result['messageIds']);
        $this->assertEquals('batch_no_ids', $result['batchId']);
        $this->assertEquals(1, $result['queued']);
        $this->assertEquals('QUEUED', $result['providerStatus']);
        $this->assertFalse($result['ambiguous'] ?? false);
    }

    /** @test */
    public function send_bulk_preserves_duplicates_removed_and_message_ids(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => [
                    'messageIds' => ['msg_one', 'msg_two'],
                    'batchId' => 'batch_bulk',
                    'queued' => 2,
                    'duplicatesRemoved' => 1,
                    'senderId' => 'DARSMS',
                ],
            ], 202),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'bulk-key',
        ]);

        $result = $provider->sendBulk(['255712345678', '255712345679'], 'Bulk message');

        $this->assertTrue($result['success']);
        $this->assertEquals('msg_one', $result['messageId']);
        $this->assertEquals(['msg_one', 'msg_two'], $result['messageIds']);
        $this->assertEquals('batch_bulk', $result['batchId']);
        $this->assertEquals(2, $result['queued']);
        $this->assertEquals(1, $result['duplicatesRemoved']);
        $this->assertEquals('QUEUED', $result['providerStatus']);
    }

    /** @test */
    public function message_id_is_mapped_from_array_or_singular_key(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['messageId' => 'msg_singular', 'batchId' => 'batch_single'],
            ], 202),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'single-key',
        ]);

        $result = $provider->send('255712345678', 'Singular message id');

        $this->assertTrue($result['success']);
        $this->assertEquals('msg_singular', $result['messageId']);
        $this->assertEquals('batch_single', $result['batchId']);
    }

    protected function seedProvider(): void
    {
        $config = SmsProviderConfig::create([
            'name' => 'Test DARSMS',
            'provider_type' => 'darsms',
            'base_url' => 'https://test.darsms.local/api/v1',
            'sender_id' => 'DARSMS',
            'is_active' => true,
        ]);
        $config->setApiKeyAttribute('seed-key');
        $config->save();
    }

    protected function createUser(UserRole $role): User
    {
        return User::create([
            'name' => 'Timeout Test User',
            'email' => 'timeout-'.strtolower($role->value).'@godwin.ac.tz',
            'password' => 'password',
            'role' => $role->value,
            'status' => 'active',
        ]);
    }
}
