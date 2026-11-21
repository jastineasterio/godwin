<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SmsCampaign;
use App\Models\SmsMessage;
use App\Models\SmsProviderConfig;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bulk SMS composer (preview + send) feature tests.
 *
 * No live SMS is sent. DARSMS HTTP calls are faked and the queue runs
 * synchronously (QUEUE_CONNECTION=sync) so campaigns and messages are
 * persisted end-to-end.
 */
class SmsBulkControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(UserRole $role): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'test-'.strtolower($role->value).'-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function activeProvider(): void
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

    /** @test */
    public function head_of_school_can_preview_all_parents(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);

        $class = \App\Models\SchoolClass::create([
            'name' => 'KG1',
            'code' => 'KG1',
            'level' => \App\Enums\ClassLevel::KG1,
            'is_active' => true,
            'capacity' => 25,
        ]);

        $student = \App\Models\Student::create([
            'reg_no' => 'GWD-'.uniqid(),
            'first_name' => 'Amina',
            'last_name' => 'Juma',
            'gender' => \App\Enums\Gender::Female,
            'class_id' => $class->id,
            'status' => 'active',
        ]);

        $parent = User::create([
            'name' => 'Parent One',
            'email' => 'parent-one-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);

        \Illuminate\Support\Facades\DB::table('parent_student')->insert([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship_type' => 'father',
            'is_primary_contact' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->post('/sms/bulk/preview-recipients', [
                'recipient_type' => 'parents_all',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success', 'recipients', 'recipient_count', 'valid_count',
            'invalid_count', 'invalid_phones', 'duplicates', 'duplicate_count',
            'recipient_type',
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(1, $response->json('recipients'));
        $this->assertEquals('255712345678', $response->json('recipients.0.phone'));
        $this->assertEquals('Parent One', $response->json('recipients.0.name'));
        $this->assertEquals(1, $response->json('valid_count'));
        $this->assertEquals(0, $response->json('invalid_count'));
        $this->assertEquals('parents_all', $response->json('recipient_type'));
    }

    /** @test */
    public function custom_numbers_are_normalized_deduplicated_and_invalid_reported(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($user)
            ->post('/sms/bulk/preview-recipients', [
                'recipient_type' => 'custom',
                'phone_numbers' => ['255712345678', '+255712345678', '0712345678', 'not-a-phone'],
            ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        // Same number in 3 formats dedupes to a single recipient.
        $this->assertCount(1, $response->json('recipients'));
        $this->assertEquals('255712345678', $response->json('recipients.0.phone'));
        $this->assertEquals(1, $response->json('valid_count'));
        $this->assertEquals(1, $response->json('invalid_count'));
        $this->assertContains('not-a-phone', $response->json('invalid_phones'));
    }

    /** @test */
    public function head_of_school_can_send_bulk_sms_and_track_campaign(): void
    {
        $this->activeProvider();

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => [
                    'batchId' => 'batch_abc',
                    'queued' => 1,
                    'duplicatesRemoved' => 0,
                    'senderId' => 'DARSMS',
                    'messageIds' => ['msg_xyz'],
                ],
            ], 202),
        ]);

        $user = $this->makeUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($user)
            ->post('/sms/bulk/send', [
                'recipients' => [
                    ['phone' => '255712345678', 'name' => 'Parent One'],
                ],
                'message' => 'Hello {parent_name}',
                'sender_id' => 'DARSMS',
                'campaign_name' => 'Test Campaign',
            ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals(1, $response->json('recipient_count'));
        $this->assertEquals('queued', $response->json('status'));
        $this->assertStringContainsString('queued', $response->json('message'));

        $this->assertDatabaseHas('sms_campaigns', [
            'name' => 'Test Campaign',
            'user_id' => $user->id,
            'sender_id' => 'DARSMS',
            'status' => 'queued',
        ]);
        $this->assertDatabaseHas('sms_messages', [
            'to' => '255712345678',
            'message' => 'Hello {parent_name}',
            'category' => 'bulk',
            'batch_id' => 'batch_abc',
            'provider_message_id' => 'msg_xyz',
        ]);
    }

    /** @test */
    public function send_requires_valid_recipients_and_message(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($user)
            ->postJson('/sms/bulk/send', [
                'recipients' => [],
                'message' => '',
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function send_fails_when_no_active_provider_is_configured(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($user)
            ->post('/sms/bulk/send', [
                'recipients' => [['phone' => '255712345678', 'name' => 'X']],
                'message' => 'Hi',
            ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function teacher_cannot_send_bulk_sms(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);

        $response = $this->actingAs($teacher)
            ->postJson('/sms/bulk/send', [
                'recipients' => [['phone' => '255712345678', 'name' => 'X']],
                'message' => 'Hi',
            ]);

        $response->assertStatus(302);
    }

    /** @test */
    public function teacher_cannot_preview_recipients(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);

        $response = $this->actingAs($teacher)
            ->postJson('/sms/bulk/preview-recipients', [
                'recipient_type' => 'parents_all',
            ]);

        $response->assertStatus(302);
    }
}
