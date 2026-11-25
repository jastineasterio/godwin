<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Jobs\Sms\SendAdmissionNotificationJob;
use App\Models\SchoolApplication;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\SmsProviderConfig;
use App\Models\User;
use App\Services\Sms\SmsMessageService;
use App\Services\Sms\SmsRecipientService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Tests\TestCase;

/**
 * SMS idempotency, safe retry and admission notification recovery tests.
 *
 * No live SMS is sent. DARSMS HTTP calls are faked via Http::fake() and the
 * queue runs synchronously (QUEUE_CONNECTION=sync).
 */
class SmsAdmissionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function makeApplication(array $overrides = []): SchoolApplication
    {
        $class = SchoolClass::create([
            'name' => 'KG1',
            'code' => 'KG1-'.uniqid(),
            'level' => 'kg1',
            'is_active' => true,
            'capacity' => 25,
        ]);

        return SchoolApplication::create(array_merge([
            'reference_no' => 'APP-2026-0001',
            'parent_name' => 'Mama Neema Juma',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'class_id' => $class->id,
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ], $overrides));
    }

    protected function seedActiveProvider(): void
    {
        $config = SmsProviderConfig::create([
            'name' => 'Test DARSMS',
            'provider_type' => 'darsms',
            'base_url' => 'https://test.darsms.local/api/v1',
            'sender_id' => 'DARSMS',
            'is_active' => true,
        ]);
        $config->setApiKeyAttribute('test-api-key');
        $config->save();
    }

    protected function fakeProviderAccepted(string $messageId = 'msg_1'): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => [
                    'batchId' => 'batch_1',
                    'queued' => 1,
                    'messageIds' => [$messageId],
                    'senderId' => 'DARSMS',
                ],
            ], 202),
        ]);
    }

    protected function fakeProviderFails(): void
    {
        Http::fake(function () {
            throw new ConnectionException('DNS resolution failed.');
        });
    }

    protected function fakeStatusLookup(string $providerStatus): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'messageId' => 'msg_provider_1',
                    'status' => $providerStatus,
                    'sentAt' => now()->toISOString(),
                    'deliveredAt' => $providerStatus === 'DELIVERED' ? now()->toISOString() : null,
                ],
            ], 200),
        ]);
    }

    protected function runJob(SchoolApplication $application, string $type = 'submitted'): void
    {
        $job = app(SendAdmissionNotificationJob::class, [
            'applicationId' => $application->id,
            'notificationType' => $type,
        ]);

        $job->handle(app(SmsMessageService::class), app(SmsRecipientService::class));
    }

    protected function admissionMessages(): array
    {
        return SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->get()
            ->all();
    }

    protected function withLogsCaptured(callable $callback): array
    {
        $entries = [];

        Log::listen(function (MessageLogged $event) use (&$entries) {
            $entries[] = [
                'level' => $event->level,
                'message' => $event->message,
                'context' => $event->context,
            ];
        });

        $callback();

        return $entries;
    }

    protected function hasLogEntry(array $entries, string $level, string $messageFragment, ?string $category = null): bool
    {
        return (bool) collect($entries)->first(fn ($entry) => $entry['level'] === $level
            && str_contains($entry['message'], $messageFragment)
            && ($category === null || ($entry['context']['error_category'] ?? null) === $category));
    }

    protected function createUser(UserRole $role): User
    {
        return User::create([
            'name' => 'Recovery Test User',
            'email' => 'recovery-'.strtolower($role->value).'@godwin.ac.tz',
            'password' => 'password',
            'role' => $role->value,
            'status' => 'active',
        ]);
    }

    // ------------------------------------------------------------------
    // 1 & 2. Queued / sent / delivered messages cannot be duplicated.
    // ------------------------------------------------------------------

    public function test_queued_message_cannot_be_duplicated(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderAccepted();

        $existing = SmsMessage::create([
            'to' => '255712345678',
            'message' => 'Already queued',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => 'msg_existing',
            'provider_status' => 'QUEUED',
            'status' => 'queued',
            'idempotency_key' => 'admission_application_submitted:1',
            'metadata' => [],
        ]);

        $service = app(SmsMessageService::class);
        $result = $service->send('255712345678', 'Duplicate attempt', [
            'idempotency_key' => 'admission_application_submitted:1',
        ]);

        $this->assertTrue($result['idempotent'] ?? false);
        $this->assertSame('queued', $result['status']);
        $this->assertSame('msg_existing', $result['messageId']);
        // Provider must not be called again.
        Http::assertNothingSent();
        $this->assertCount(1, $this->admissionMessages());
        $this->assertDatabaseHas('sms_messages', ['id' => $existing->id, 'status' => 'queued']);
    }

    public function test_sent_and_delivered_messages_cannot_be_duplicated(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderAccepted();

        foreach (['sent', 'delivered'] as $status) {
            $existing = SmsMessage::create([
                'to' => '255712345679',
                'message' => 'Already '.$status,
                'category' => 'admission',
                'sender_id' => 'DARSMS',
                'provider_type' => 'darsms',
                'provider_name' => 'DARSMS',
                'provider_message_id' => 'msg_'.$status,
                'provider_status' => $status === 'delivered' ? 'DELIVERED' : 'SENT',
                'status' => $status,
                'idempotency_key' => 'admission_application_accepted:'.$status,
                'metadata' => [],
            ]);

            $service = app(SmsMessageService::class);
            $result = $service->send('255712345679', 'Duplicate attempt', [
                'idempotency_key' => 'admission_application_accepted:'.$status,
            ]);

            $this->assertTrue($result['idempotent'] ?? false, "$status must not be duplicated");
            $this->assertSame($status, $result['status']);
            $this->assertCount(1, SmsMessage::where('idempotency_key', 'admission_application_accepted:'.$status)->get());
        }

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // 3. Failed messages can be retried through the authorized workflow.
    // ------------------------------------------------------------------

    public function test_failed_message_can_be_retried_through_authorized_workflow(): void
    {
        $admin = $this->createUser(UserRole::Admin);
        $this->seedActiveProvider();
        $this->fakeProviderAccepted();

        $failed = SmsMessage::create([
            'to' => '255712345680',
            'message' => 'Retry me',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'failed',
            'error_message' => 'Invalid phone number.',
            'metadata' => [],
        ]);

        $service = app(SmsMessageService::class);
        $result = $service->resendMessage($failed, $admin, 'Authorized retry after provider rejection.');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['idempotent'] ?? false);
        $this->assertSame('msg_1', $result['messageId']);
        // Original failed record is preserved.
        $this->assertDatabaseHas('sms_messages', ['id' => $failed->id, 'status' => 'failed']);
        // A distinct resend record was created.
        $this->assertDatabaseHas('sms_messages', [
            'idempotency_key' => 'sms_resend:'.$failed->id,
            'metadata->resend_of' => $failed->id,
        ]);
        // Delivery event audited on the original.
        $this->assertDatabaseHas('sms_delivery_events', [
            'sms_message_id' => $failed->id,
            'error_message' => 'Manual resend: Authorized retry after provider rejection.',
        ]);
    }

    // ------------------------------------------------------------------
    // 4. Unknown submissions are not automatically resent.
    // ------------------------------------------------------------------

    public function test_unknown_submission_is_not_automatically_resent_by_job(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderFails();

        $application = $this->makeApplication();

        // First attempt: ambiguous failure creates submission_unknown and is retried.
        $entries = $this->withLogsCaptured(fn () => $this->expectExceptionAndRun($application));

        $this->assertTrue($this->hasLogEntry($entries, 'warning', 'transient provider failure, will retry', 'connection_failure'));

        $messages = $this->admissionMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('submission_unknown', $messages[0]->status);
        $this->assertNull($messages[0]->provider_message_id);

        // Second attempt: idempotent, provider NOT called, stays submission_unknown.
        $sendCount = 0;
        Http::fake(function () use (&$sendCount) {
            $sendCount++;

            return Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['messageIds' => ['msg_should_not_send']],
            ], 202);
        });

        $entries = $this->withLogsCaptured(fn () => $this->runJob($application));

        $this->assertSame(0, $sendCount, 'Provider must not be called again for submission_unknown');
        $this->assertTrue($this->hasLogEntry($entries, 'warning', 'requires manual reconciliation', 'submission_unknown'));
        $this->assertDatabaseHas('sms_messages', ['id' => $messages[0]->id, 'status' => 'submission_unknown']);
        $this->assertCount(1, $this->admissionMessages());
    }

    protected function expectExceptionAndRun(SchoolApplication $application): void
    {
        $this->expectException(\RuntimeException::class);

        $this->runJob($application);
    }

    public function test_send_returns_submission_unknown_as_unresolved_not_success(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderAccepted();

        $existing = SmsMessage::create([
            'to' => '255712345681',
            'message' => 'Unknown',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider timeout.',
            'idempotency_key' => 'admission_application_submitted:2',
            'metadata' => [],
        ]);

        $service = app(SmsMessageService::class);
        $result = $service->send('255712345681', 'Retry attempt', [
            'idempotency_key' => 'admission_application_submitted:2',
        ]);

        $this->assertTrue($result['idempotent'] ?? false);
        $this->assertFalse($result['success'] ?? true, 'submission_unknown must not be reported as success');
        $this->assertSame('submission_unknown', $result['status']);
        $this->assertTrue($result['resend_required'] ?? false);
        Http::assertNothingSent();
        $this->assertDatabaseHas('sms_messages', ['id' => $existing->id, 'status' => 'submission_unknown']);
    }

    // ------------------------------------------------------------------
    // 5. Unknown submissions can be reconciled when provider status is available.
    // ------------------------------------------------------------------

    public function test_unknown_submission_can_be_reconciled_when_provider_status_available(): void
    {
        $this->seedActiveProvider();
        $this->fakeStatusLookup('DELIVERED');

        $unknown = SmsMessage::create([
            'to' => '255712345682',
            'message' => 'Reconcile me',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => 'msg_provider_1',
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider timeout.',
            'metadata' => [],
        ]);

        $service = app(SmsMessageService::class);
        $result = $service->reconcileSubmissionUnknown($unknown);

        $this->assertTrue($result['success']);
        $this->assertSame('delivered', $result['status']);
        $this->assertDatabaseHas('sms_messages', [
            'id' => $unknown->id,
            'status' => 'delivered',
            'provider_status' => 'DELIVERED',
        ]);
        $this->assertDatabaseHas('sms_delivery_events', [
            'sms_message_id' => $unknown->id,
            'status' => 'delivered',
        ]);
    }

    public function test_unknown_submission_without_provider_id_cannot_be_reconciled(): void
    {
        $this->seedActiveProvider();

        $unknown = SmsMessage::create([
            'to' => '255712345683',
            'message' => 'No provider id',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider timeout.',
            'metadata' => [],
        ]);

        $service = app(SmsMessageService::class);
        $result = $service->reconcileSubmissionUnknown($unknown);

        $this->assertFalse($result['success']);
        $this->assertSame('no_provider_id', $result['code']);
        $this->assertFalse($result['reconcilable'] ?? true);
        $this->assertDatabaseHas('sms_messages', ['id' => $unknown->id, 'status' => 'submission_unknown']);
    }

    // ------------------------------------------------------------------
    // 6. Manual resend requires explicit authorization.
    // ------------------------------------------------------------------

    public function test_manual_resend_requires_admin_or_head_of_school(): void
    {
        $teacher = $this->createUser(UserRole::Teacher);
        $this->seedActiveProvider();

        $unknown = SmsMessage::create([
            'to' => '255712345684',
            'message' => 'No resend',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider timeout.',
            'metadata' => [],
        ]);

        $service = app(SmsMessageService::class);
        $result = $service->resendMessage($unknown, $teacher, 'Unauthorized attempt.');

        $this->assertFalse($result['success']);
        $this->assertSame('unauthorized', $result['code']);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // 7. Concurrent retries cannot produce duplicate sends.
    // ------------------------------------------------------------------

    public function test_concurrent_resend_attempts_cannot_produce_duplicate_sends(): void
    {
        $admin = $this->createUser(UserRole::Admin);
        $this->seedActiveProvider();

        $sendCount = 0;
        Http::fake(function () use (&$sendCount) {
            $sendCount++;

            return Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['messageIds' => ['msg_concurrent_'.$sendCount]],
            ], 202);
        });

        $unknown = SmsMessage::create([
            'to' => '255712345685',
            'message' => 'Concurrent resend',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider timeout.',
            'metadata' => [],
        ]);

        $service = app(SmsMessageService::class);

        $first = $service->resendMessage($unknown, $admin, 'First concurrent resend.');
        $second = $service->resendMessage($unknown, $admin, 'Second concurrent resend (blocked).');

        $this->assertTrue($first['success']);
        $this->assertFalse($first['idempotent'] ?? false);
        $this->assertTrue($second['idempotent'] ?? false, 'Second resend must be idempotent');
        $this->assertSame(1, $sendCount, 'Provider must only be called once');
        $this->assertCount(1, SmsMessage::where('idempotency_key', 'sms_resend:'.$unknown->id)->get());
    }

    // ------------------------------------------------------------------
    // 8. Existing results SMS behavior remains intact.
    // ------------------------------------------------------------------

    public function test_results_sms_idempotency_remains_intact(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderAccepted();

        $user = $this->createUser(UserRole::HeadOfSchool);

        SmsMessage::create([
            'to' => '255712345686',
            'message' => 'Results already sent',
            'category' => 'results',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => 'msg_result',
            'provider_status' => 'DELIVERED',
            'status' => 'delivered',
            'idempotency_key' => 'results_sms:existing',
            'metadata' => [],
        ]);

        $service = app(SmsMessageService::class);
        $result = $service->send('255712345686', 'Duplicate results', [
            'idempotency_key' => 'results_sms:existing',
            'user' => $user,
            'category' => 'results',
        ]);

        $this->assertTrue($result['idempotent'] ?? false);
        $this->assertSame('delivered', $result['status']);
        Http::assertNothingSent();
        $this->assertCount(1, SmsMessage::where('category', 'results')->get());
    }

    // ------------------------------------------------------------------
    // 9. SMS-disabled settings still prevent sending.
    // ------------------------------------------------------------------

    public function test_sms_disabled_settings_prevent_sending(): void
    {
        $this->seedActiveProvider();
        Setting::put('admissions.application_submitted_sms_enabled', 'false', 'admissions');

        $application = $this->makeApplication();

        $this->withLogsCaptured(fn () => $this->runJob($application, 'submitted'));

        $this->assertCount(0, $this->admissionMessages());
    }

    // ------------------------------------------------------------------
    // 10. Provider failures do not roll back application submission or approval.
    // ------------------------------------------------------------------

    public function test_acceptance_persists_regardless_of_sms_outcome(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderFails();

        $application = $this->makeApplication(['status' => ApplicationStatus::Submitted]);
        $admin = $this->createUser(UserRole::Admin);

        // Simulate the accept flow: transition status (committed), then dispatch job.
        $application->update(['status' => ApplicationStatus::Accepted->value, 'reviewed_by' => $admin->id, 'review_notes' => 'Accepted.']);

        $this->expectException(\RuntimeException::class);
        $this->runJob($application, 'accepted');

        // The application remains accepted even though the SMS job failed.
        $this->assertDatabaseHas('school_applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Accepted->value,
        ]);
    }

    // ------------------------------------------------------------------
    // Admin recovery action (controller-level).
    // ------------------------------------------------------------------

    public function test_admin_can_view_unresolved_admission_notifications(): void
    {
        $this->seedActiveProvider();

        $unknown = SmsMessage::create([
            'to' => '255712345688',
            'message' => 'Unresolved admission',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider timeout.',
            'idempotency_key' => 'admission_application_submitted:3',
            'metadata' => ['reference_no' => 'APP-2026-0003', 'notification_type' => 'submitted', 'application_id' => 3],
        ]);

        $admin = $this->createUser(UserRole::Admin);

        $response = $this->actingAs($admin)
            ->get(route('sms.admin.admission-notifications'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Sms/AdmissionRecovery'));
    }

    public function test_head_of_school_cannot_access_admission_recovery(): void
    {
        $this->seedActiveProvider();
        $head = $this->createUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($head)
            ->get(route('sms.admin.admission-notifications'));

        $response->assertStatus(302);
    }

    public function test_reconcile_updates_status_from_provider_lookup(): void
    {
        $this->seedActiveProvider();
        $this->fakeStatusLookup('DELIVERED');

        $admin = $this->createUser(UserRole::Admin);

        $unknown = SmsMessage::create([
            'to' => '255712345689',
            'message' => 'To reconcile',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => 'msg_provider_1',
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider timeout.',
            'metadata' => [],
        ]);

        $response = $this->actingAs($admin)
            ->post(route('sms.admin.admission-notifications.reconcile', $unknown->id));

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertDatabaseHas('sms_messages', [
            'id' => $unknown->id,
            'status' => 'delivered',
        ]);
    }

    public function test_reconcile_requires_provider_message_id(): void
    {
        $this->seedActiveProvider();

        $admin = $this->createUser(UserRole::Admin);

        $unknown = SmsMessage::create([
            'to' => '255712345690',
            'message' => 'No provider id',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider timeout.',
            'metadata' => [],
        ]);

        $response = $this->actingAs($admin)
            ->post(route('sms.admin.admission-notifications.reconcile', $unknown->id));

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertDatabaseHas('sms_messages', ['id' => $unknown->id, 'status' => 'submission_unknown']);
    }

    public function test_resend_requires_explicit_confirmation(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderAccepted();

        $admin = $this->createUser(UserRole::Admin);

        $unknown = SmsMessage::create([
            'to' => '255712345691',
            'message' => 'Needs confirmation',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider timeout.',
            'metadata' => [],
        ]);

        // Missing confirmed flag -> blocked by validation.
        $response = $this->actingAs($admin)
            ->post(route('sms.admin.admission-notifications.resend', $unknown->id), [
                'reason' => 'No confirmation.',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('confirmed');
        $this->assertDatabaseHas('sms_messages', ['id' => $unknown->id, 'status' => 'submission_unknown']);

        // confirmed = false -> blocked by explicit check.
        $response = $this->actingAs($admin)
            ->post(route('sms.admin.admission-notifications.resend', $unknown->id), [
                'confirmed' => false,
                'reason' => 'Not confirmed.',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('sms_messages', ['id' => $unknown->id, 'status' => 'submission_unknown']);
    }

    public function test_resend_requires_admin_head_of_school_excluded(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderAccepted();

        $head = $this->createUser(UserRole::HeadOfSchool);

        $unknown = SmsMessage::create([
            'to' => '255712345692',
            'message' => 'Head of school resend',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider timeout.',
            'metadata' => [],
        ]);

        $response = $this->actingAs($head)
            ->post(route('sms.admin.admission-notifications.resend', $unknown->id), [
                'confirmed' => true,
                'reason' => 'Head of school attempt.',
            ]);

        $response->assertStatus(302);
        Http::assertNothingSent();
    }

    public function test_admin_can_explicitly_resend_unresolved_notification(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderAccepted();

        $admin = $this->createUser(UserRole::Admin);

        $unknown = SmsMessage::create([
            'to' => '255712345693',
            'message' => 'Authorized resend',
            'category' => 'admission',
            'sender_id' => 'DARSMS',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'provider_status' => null,
            'status' => 'submission_unknown',
            'error_message' => 'Provider timeout.',
            'metadata' => ['reference_no' => 'APP-2026-0004', 'notification_type' => 'accepted', 'application_id' => 4],
        ]);

        $response = $this->actingAs($admin)
            ->post(route('sms.admin.admission-notifications.resend', $unknown->id), [
                'confirmed' => true,
                'reason' => 'Admin authorized recovery resend.',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertTrue($response->getSession()->get('success') !== null);
        // Resend record created, provider called once.
        $this->assertDatabaseHas('sms_messages', [
            'idempotency_key' => 'sms_resend:'.$unknown->id,
            'metadata->resend_of' => $unknown->id,
        ]);
    }
}
