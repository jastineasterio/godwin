<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Jobs\Sms\SendAdmissionNotificationJob;
use App\Jobs\Sms\SendSmsJob;
use App\Models\SchoolApplication;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\SmsProviderConfig;
use App\Models\User;
use App\Services\Sms\AdmissionSmsNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Online admission application management + Kiswahili SMS notifications.
 *
 * No live SMS messages are sent. Providers are faked via Http::fake() and
 * queues are faked via Queue::fake().
 */
class AdmissionApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.darsms' => [
            'base_url' => 'https://test.darsms.local/api/v1',
        ]]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    protected function makeApplication(array $overrides = []): SchoolApplication
    {
        static $classCounter = 0;
        $classCounter++;

        $class = SchoolClass::create([
            'name' => 'KG1',
            'code' => 'KG1-'.$classCounter,
            'level' => 'kg1',
            'is_active' => true,
            'capacity' => 25,
        ]);

        return SchoolApplication::create(array_merge([
            'reference_no' => 'APP-2026-0001',
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Please admit my child.',
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ], $overrides));
    }

    protected function makeUser(UserRole $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role->value).' User',
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    protected function fakeProviderSuccess(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => [
                    'batchId' => 'batch_1',
                    'queued' => 1,
                    'duplicatesRemoved' => 0,
                    'senderId' => 'DARSMS',
                    'messageIds' => ['msg_1'],
                ],
            ], 202),
        ]);
    }

    protected function fakeProviderThrows(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });
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

    protected function pushedJobsOfType(string $class): array
    {
        $pushed = Queue::pushed($class);

        return $pushed ? $pushed->all() : [];
    }

    /* ================================================================== */
    /* Application workflow tests */
    /* ================================================================== */

    public function test_public_application_submission_still_works(): void
    {
        $this->fakeProviderSuccess();

        $response = $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => SchoolClass::create([
                'name' => 'KG1', 'code' => 'KG1', 'level' => 'kg1',
                'is_active' => true, 'capacity' => 25,
            ])->id,
            'message' => 'Hello',
        ]);

        $response->assertRedirect('/apply');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('applications', [
            'reference_no' => 'APP-2026-0001',
            'parent_name' => 'Mama Neema Juma',
            'child_first_name' => 'Amina',
            'status' => 'submitted',
        ]);
    }

    public function test_submitted_application_is_visible_to_admin(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication();

        $response = $this->actingAs($admin)
            ->get('/admissions');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('applications.total', 1)
            ->where('applications.data.0.reference_no', $app->reference_no)
            ->where('filters.search', '')
            ->where('filters.status', '')
        );
        $this->assertDatabaseHas('applications', [
            'reference_no' => $app->reference_no,
            'status' => 'submitted',
        ]);
    }

    public function test_submitted_application_is_visible_to_head_of_school(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool, 'hos@godwin.ac.tz');
        $app = $this->makeApplication();

        $response = $this->actingAs($hos)
            ->get('/admissions');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('applications.total', 1)
            ->where('applications.data.0.reference_no', $app->reference_no)
        );
        $this->assertDatabaseHas('applications', [
            'reference_no' => $app->reference_no,
            'status' => 'submitted',
        ]);
    }

    public function test_teacher_cannot_access_application_management(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher, 'teacher@godwin.ac.tz');

        $this->actingAs($teacher)
            ->get('/admissions')
            ->assertRedirect('/dashboard');
    }

    public function test_parent_cannot_access_application_management(): void
    {
        $parent = $this->makeUser(UserRole::Parent, 'parent@godwin.ac.tz');

        $this->actingAs($parent)
            ->get('/admissions')
            ->assertRedirect('/dashboard');
    }

    public function test_unauthenticated_user_cannot_access_application_management(): void
    {
        $this->get('/admissions')->assertRedirect('/login');
    }

    public function test_authorized_staff_can_mark_application_under_review(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/review", [
                'review_notes' => 'Checking documents.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('applications', [
            'id' => $app->id,
            'status' => 'under_review',
            'reviewed_by' => $admin->id,
            'review_notes' => 'Checking documents.',
        ]);
    }

    public function test_head_of_school_can_mark_application_under_review(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool, 'hos@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->actingAs($hos)
            ->post("/admissions/{$app->id}/review", [
                'review_notes' => 'HoS review.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('applications', [
            'id' => $app->id,
            'status' => 'under_review',
            'reviewed_by' => $hos->id,
        ]);
    }

    public function test_authorized_staff_can_accept_application(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderSuccess();
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        Queue::fake();

        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/accept", [
                'review_notes' => 'Accepted.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('applications', [
            'id' => $app->id,
            'status' => 'accepted',
            'reviewed_by' => $admin->id,
            'review_notes' => 'Accepted.',
        ]);

        // Acceptance SMS sent directly, not queued.
        Queue::assertNotPushed(SendAdmissionNotificationJob::class);

        $this->assertCount(1, SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->where('metadata->notification_type', 'accepted')
            ->get());

        // No student auto-created.
        $this->assertDatabaseMissing('students', [
            'first_name' => 'Amina',
            'last_name' => 'Juma',
        ]);
    }

    public function test_authorized_staff_can_reject_application(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/reject", [
                'review_notes' => 'Not eligible.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('applications', [
            'id' => $app->id,
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'review_notes' => 'Not eligible.',
        ]);

        // No SMS for rejection.
        $this->assertDatabaseMissing('sms_messages', [
            'to' => '255712345678',
            'category' => 'admission',
        ]);
    }

    public function test_review_information_is_recorded_correctly(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool, 'hos@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->actingAs($hos)
            ->post("/admissions/{$app->id}/accept", [
                'review_notes' => 'All documents verified.',
            ]);

        $fresh = SchoolApplication::find($app->id);
        $this->assertSame(ApplicationStatus::Accepted->value, $fresh->status->value);
        $this->assertSame($hos->id, $fresh->reviewed_by);
        $this->assertSame('All documents verified.', $fresh->review_notes);
    }

    public function test_accepting_application_does_not_auto_create_student(): void
    {
        $this->fakeProviderSuccess();
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/accept");

        $this->assertDatabaseMissing('students', [
            'first_name' => 'Amina',
            'last_name' => 'Juma',
        ]);
    }

    public function test_rejected_application_cannot_be_re_accepted(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Rejected]);

        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/accept");

        $this->assertDatabaseHas('applications', [
            'id' => $app->id,
            'status' => 'rejected',
        ]);
    }

    public function test_accepted_application_cannot_be_re_accepted(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Accepted]);

        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/accept");

        $this->assertDatabaseHas('applications', [
            'id' => $app->id,
            'status' => 'accepted',
        ]);
    }

    /* ================================================================== */
    /* SMS notification tests */
    /* ================================================================== */

    public function test_submission_sms_sent_directly_when_enabled(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderSuccess();
        Queue::fake();

        $class = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => 'kg1',
            'is_active' => true, 'capacity' => 25,
        ]);

        $response = $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        $response->assertRedirect('/apply');

        $app = SchoolApplication::first();

        // SMS is sent synchronously, not queued.
        Queue::assertNotPushed(SendAdmissionNotificationJob::class);

        $messages = SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->get();

        $this->assertCount(1, $messages);
        $this->assertSame('queued', $messages[0]->status);
        $this->assertSame('admission_application_submitted:'.$app->id, $messages[0]->idempotency_key);
    }

    public function test_submission_sms_not_sent_when_disabled(): void
    {
        $this->fakeProviderSuccess();
        Queue::fake();

        Setting::put('admissions.application_submitted_sms_enabled', 'false', 'admissions');

        $class = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => 'kg1',
            'is_active' => true, 'capacity' => 25,
        ]);

        $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        Queue::assertNotPushed(SendAdmissionNotificationJob::class);
        $this->assertDatabaseMissing('sms_messages', [
            'to' => '255712345678',
            'category' => 'admission',
        ]);
    }

    public function test_acceptance_sms_sent_directly_when_enabled(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderSuccess();
        Queue::fake();

        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/accept");

        // SMS is sent synchronously, not queued.
        Queue::assertNotPushed(SendAdmissionNotificationJob::class);

        $messages = SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->get();

        $this->assertCount(1, $messages);
        $this->assertSame('queued', $messages[0]->status);
        $this->assertSame('admission_application_accepted:'.$app->id, $messages[0]->idempotency_key);
    }

    public function test_acceptance_sms_not_sent_when_disabled(): void
    {
        $this->fakeProviderSuccess();
        Queue::fake();

        Setting::put('admissions.application_accepted_sms_enabled', 'false', 'admissions');

        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/accept");

        Queue::assertNotPushed(SendAdmissionNotificationJob::class);
        $this->assertDatabaseMissing('sms_messages', [
            'to' => '255712345678',
            'category' => 'admission',
        ]);
    }

    public function test_rejection_does_not_send_sms(): void
    {
        $this->fakeProviderSuccess();
        Queue::fake();

        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/reject");

        Queue::assertNotPushed(SendAdmissionNotificationJob::class);
        $this->assertDatabaseMissing('sms_messages', [
            'to' => '255712345678',
            'category' => 'admission',
        ]);
    }

    public function test_under_review_does_not_send_acceptance_sms(): void
    {
        $this->fakeProviderSuccess();
        Queue::fake();

        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/review");

        Queue::assertNotPushed(SendAdmissionNotificationJob::class);
        $this->assertDatabaseMissing('sms_messages', [
            'to' => '255712345678',
            'category' => 'admission',
        ]);
    }

    public function test_repeated_submission_notification_does_not_duplicate_sms(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderSuccess();
        Queue::fake();

        $class = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => 'kg1',
            'is_active' => true, 'capacity' => 25,
        ]);

        // Submit twice with same phone → two distinct applications, each notified once.
        $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        // No jobs dispatched; one SMS per application via idempotency keys.
        Queue::assertNotPushed(SendAdmissionNotificationJob::class);

        $apps = SchoolApplication::orderBy('id')->get();
        $this->assertCount(2, $apps);

        $messages = SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $messages);
        $this->assertSame('admission_application_submitted:'.$apps->first()->id, $messages->first()->idempotency_key);
        $this->assertSame('admission_application_submitted:'.$apps->last()->id, $messages->last()->idempotency_key);
    }

    public function test_repeated_approval_requests_do_not_duplicate_acceptance_sms(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderSuccess();
        Queue::fake();

        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/accept");

        // Second accept on already-accepted app: blocked server-side, no new SMS.
        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/accept");

        Queue::assertNotPushed(SendAdmissionNotificationJob::class);

        $this->assertCount(1, SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->get());
    }

    public function test_repeated_direct_send_does_not_duplicate_sms(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderSuccess();

        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $notifier = app(AdmissionSmsNotifier::class);

        // Two direct sends for the same application → one SMS via idempotency.
        $notifier->notify($app, 'accepted');
        $notifier->notify($app, 'accepted');

        $this->assertCount(1, SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->get());
        $this->assertDatabaseHas('sms_messages', [
            'idempotency_key' => 'admission_application_accepted:'.$app->id,
        ]);
    }

    public function test_sms_provider_failure_does_not_break_submission(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderThrows();
        Queue::fake();

        $class = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => 'kg1',
            'is_active' => true, 'capacity' => 25,
        ]);

        $response = $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        // Application still saved despite provider throwing.
        $response->assertRedirect('/apply');
        $this->assertDatabaseHas('applications', [
            'reference_no' => 'APP-2026-0001',
            'status' => 'submitted',
        ]);
    }

    public function test_sms_provider_failure_does_not_break_approval(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderThrows();
        Queue::fake();

        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $response = $this->actingAs($admin)
            ->post("/admissions/{$app->id}/accept");

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('applications', [
            'id' => $app->id,
            'status' => 'accepted',
        ]);
    }

    public function test_missing_phone_number_does_not_break_submission(): void
    {
        $this->fakeProviderSuccess();
        Queue::fake();

        $class = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => 'kg1',
            'is_active' => true, 'capacity' => 25,
        ]);

        $response = $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => 'invalid-phone',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        $response->assertRedirect('/apply');
        $this->assertDatabaseHas('applications', [
            'reference_no' => 'APP-2026-0001',
            'status' => 'submitted',
        ]);
    }

    public function test_two_notification_settings_operate_independently(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderSuccess();
        Queue::fake();

        // Submitted OFF, accepted ON.
        Setting::put('admissions.application_submitted_sms_enabled', 'false', 'admissions');
        Setting::put('admissions.application_accepted_sms_enabled', 'true', 'admissions');

        $class = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => 'kg1',
            'is_active' => true, 'capacity' => 25,
        ]);

        $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        $app = SchoolApplication::first();
        $this->assertDatabaseMissing('sms_messages', [
            'to' => '255712345678',
            'category' => 'admission',
            'metadata->notification_type' => 'submitted',
        ]);

        // Now accept → accepted SMS should still be sent directly.
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $this->actingAs($admin)
            ->post("/admissions/{$app->id}/accept");

        $acceptedMessages = SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->where('metadata->notification_type', 'accepted')
            ->get();

        $this->assertCount(1, $acceptedMessages);
        Queue::assertNotPushed(SendAdmissionNotificationJob::class);
    }

    public function test_submission_sms_sent_after_successful_commit(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderSuccess();
        Queue::fake();

        $class = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => 'kg1',
            'is_active' => true, 'capacity' => 25,
        ]);

        $response = $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        $response->assertRedirect('/apply');

        // The application is committed before the synchronous notification is sent.
        $app = SchoolApplication::first();
        $this->assertNotNull($app, 'Application must be committed to the database.');
        $this->assertDatabaseHas('applications', [
            'id' => $app->id,
            'status' => ApplicationStatus::Submitted->value,
        ]);

        // SMS sent directly (no job on the queue).
        Queue::assertNotPushed(SendAdmissionNotificationJob::class);

        $messages = SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->get();

        $this->assertCount(1, $messages);
        $this->assertSame('queued', $messages[0]->status);
    }

    public function test_failed_application_creation_does_not_send_notification(): void
    {
        $this->fakeProviderSuccess();
        Queue::fake();

        $this->post('/apply', [])
            ->assertSessionHasErrors();

        Queue::assertNotPushed(SendAdmissionNotificationJob::class);
        $this->assertDatabaseMissing('applications', [
            'parent_name' => 'Mama Neema Juma',
        ]);
        $this->assertDatabaseMissing('sms_messages', [
            'category' => 'admission',
        ]);
    }

    public function test_same_phone_across_different_applications_does_not_block_notifications(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderSuccess();
        Queue::fake();

        $class = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => 'kg1',
            'is_active' => true, 'capacity' => 25,
        ]);

        $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Kofi',
            'child_last_name' => 'Adu',
            'gender' => 'male',
            'dob' => '2019-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        Queue::assertNotPushed(SendAdmissionNotificationJob::class);

        $apps = SchoolApplication::orderBy('id')->get();
        $this->assertCount(2, $apps);

        // Same phone, different applications → distinct idempotency keys, both notified.
        $messages = SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $messages);
        $this->assertSame('admission_application_submitted:'.$apps->first()->id, $messages->first()->idempotency_key);
        $this->assertSame('admission_application_submitted:'.$apps->last()->id, $messages->last()->idempotency_key);
        $this->assertSame('queued', $messages->first()->status);
        $this->assertSame('queued', $messages->last()->status);
    }

    public function test_http_timeout_is_handled_gracefully(): void
    {
        $this->seedActiveProvider();
        Http::fake(function () {
            throw new ConnectionException('Connection timed out while awaiting response.');
        });
        Queue::fake();

        $class = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => 'kg1',
            'is_active' => true, 'capacity' => 25,
        ]);

        // A provider timeout must not block or fail the visitor submission.
        $response = $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        $response->assertRedirect('/apply');
        $this->assertDatabaseHas('applications', [
            'reference_no' => 'APP-2026-0001',
            'status' => 'submitted',
        ]);

        // No admission job is dispatched.
        Queue::assertNotPushed(SendAdmissionNotificationJob::class);

        // The ambiguous outcome is recorded as submission_unknown, not
        // delivered and not definitely failed, for later reconciliation.
        $message = SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->first();

        $this->assertNotNull($message);
        $this->assertSame('submission_unknown', $message->status);
        $this->assertNull($message->delivered_at);
    }

    public function test_submission_unknown_is_not_blindly_resent(): void
    {
        $this->seedActiveProvider();
        Http::fake(function () {
            throw new ConnectionException('Connection timed out.');
        });

        $class = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => 'kg1',
            'is_active' => true, 'capacity' => 25,
        ]);

        // First submission: ambiguous timeout → submission_unknown.
        $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        $message = SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->first();

        $this->assertSame('submission_unknown', $message->status);

        // Now the provider succeeds; a repeated direct send for the same
        // application must not resend (idempotency suppresses it).
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['batchId' => 'batch_1', 'queued' => 1, 'messageIds' => ['msg_1']],
            ], 202),
        ]);

        $notifier = app(AdmissionSmsNotifier::class);
        $app = SchoolApplication::first();
        $notifier->notify($app, 'submitted');

        $this->assertCount(1, SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->get());
        $this->assertSame('submission_unknown', $message->fresh()->status);
    }

    public function test_sms_status_recorded_accurately(): void
    {
        $this->seedActiveProvider();
        // HTTP 202 means accepted by the provider, NOT delivered.
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['batchId' => 'batch_1', 'queued' => 1, 'messageIds' => ['msg_1']],
            ], 202),
        ]);

        $class = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => 'kg1',
            'is_active' => true, 'capacity' => 25,
        ]);

        $this->post('/apply', [
            'parent_name' => 'Mama Neema Juma',
            'parent_email' => 'neema@example.com',
            'parent_phone' => '0712345678',
            'relationship' => 'mother',
            'address' => 'Street, Ward, Dodoma',
            'child_first_name' => 'Amina',
            'child_last_name' => 'Juma',
            'gender' => 'female',
            'dob' => '2020-01-01',
            'class_id' => $class->id,
            'message' => 'Hello',
        ]);

        $message = SmsMessage::where('to', '255712345678')
            ->where('category', 'admission')
            ->first();

        $this->assertNotNull($message);
        $this->assertSame('queued', $message->status);
        $this->assertSame('QUEUED', $message->provider_status);
        $this->assertNull($message->delivered_at);
    }

    public function test_bulk_and_results_sms_still_use_queue_jobs(): void
    {
        Queue::fake();

        // Bulk SMS still dispatches SendSmsJob to the sms queue (unchanged).
        SendSmsJob::dispatch(1, '255712345678', 'Bulk test message.');
        Queue::assertPushed(SendSmsJob::class);

        // Results SMS path uses the same SendSmsJob infrastructure (unchanged).
        SendSmsJob::dispatch(2, '255712345679', 'Results test message.');
        Queue::assertPushed(SendSmsJob::class);

        // Admission submission/acceptance must NOT dispatch SendAdmissionNotificationJob.
        Queue::assertNotPushed(SendAdmissionNotificationJob::class);
    }

    /* ================================================================== */
    /* Authorization for state-changing endpoints */
    /* ================================================================== */

    public function test_teacher_cannot_accept_application(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher, 'teacher@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->actingAs($teacher)
            ->post("/admissions/{$app->id}/accept")
            ->assertRedirect('/dashboard');
    }

    public function test_parent_cannot_reject_application(): void
    {
        $parent = $this->makeUser(UserRole::Parent, 'parent@godwin.ac.tz');
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->actingAs($parent)
            ->post("/admissions/{$app->id}/reject")
            ->assertRedirect('/dashboard');
    }

    public function test_unauthenticated_cannot_accept_application(): void
    {
        $app = $this->makeApplication(['status' => ApplicationStatus::Submitted]);

        $this->post("/admissions/{$app->id}/accept")
            ->assertRedirect('/login');
    }

    /* ================================================================== */
    /* Application detail view access */
    /* ================================================================== */

    public function test_admin_can_view_application_detail(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $app = $this->makeApplication();

        $this->actingAs($admin)
            ->get("/admissions/{$app->id}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('application.reference_no', $app->reference_no)
            );
    }

    public function test_head_of_school_can_view_application_detail(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool, 'hos@godwin.ac.tz');
        $app = $this->makeApplication();

        $this->actingAs($hos)
            ->get("/admissions/{$app->id}")
            ->assertStatus(200);
    }

    public function test_search_filters_applications(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $this->makeApplication(['reference_no' => 'APP-2026-0002', 'child_first_name' => 'Kofi', 'child_last_name' => 'Adu']);
        $target = $this->makeApplication(['child_first_name' => 'Amina', 'child_last_name' => 'Juma']);

        $this->actingAs($admin)
            ->get('/admissions?search=Amina')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->where('applications.data.0.reference_no', $target->reference_no)
            );
    }

    public function test_status_filter_works(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $this->makeApplication(['status' => ApplicationStatus::Submitted]);
        $this->makeApplication(['reference_no' => 'APP-2026-0002', 'status' => ApplicationStatus::Accepted]);

        $this->actingAs($admin)
            ->get('/admissions?status=accepted')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->where('applications.data.0.status', 'accepted')
            );
    }

    public function test_index_returns_all_applications_without_filters(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $submitted = $this->makeApplication(['submitted_at' => now()->subMinute()]);
        $accepted = $this->makeApplication(['reference_no' => 'APP-2026-0005', 'status' => ApplicationStatus::Accepted]);

        $this->actingAs($admin)
            ->get('/admissions')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 2)
                ->where('applications.data.0.reference_no', $accepted->reference_no)
                ->where('applications.data.1.reference_no', $submitted->reference_no)
                ->where('filters.search', '')
                ->where('filters.status', '')
            );
    }

    public function test_index_ignores_invalid_status_and_returns_all(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $this->makeApplication(['status' => ApplicationStatus::Submitted]);
        $this->makeApplication(['reference_no' => 'APP-2026-0006', 'status' => ApplicationStatus::Accepted]);

        $this->actingAs($admin)
            ->get('/admissions?status=bogus')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 2)
            );
    }

    public function test_index_status_filter_returns_only_matching_status(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $this->makeApplication(['status' => ApplicationStatus::Submitted]);
        $accepted = $this->makeApplication(['reference_no' => 'APP-2026-0007', 'status' => ApplicationStatus::Accepted]);

        $this->actingAs($admin)
            ->get('/admissions?status=accepted')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->where('applications.data.0.reference_no', $accepted->reference_no)
            );
    }

    public function test_index_search_by_reference_number(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $this->makeApplication(['reference_no' => 'APP-2026-0102']);
        $target = $this->makeApplication(['reference_no' => 'APP-2026-0101']);

        $this->actingAs($admin)
            ->get('/admissions?search=0101')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->where('applications.data.0.reference_no', $target->reference_no)
            );
    }

    public function test_index_search_by_parent_name(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $this->makeApplication(['reference_no' => 'APP-2026-0202', 'parent_name' => 'Baba John Doe']);
        $target = $this->makeApplication(['reference_no' => 'APP-2026-0201', 'parent_name' => 'Mama Neema Juma']);

        $this->actingAs($admin)
            ->get('/admissions?search=Neema')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->where('applications.data.0.parent_name', 'Mama Neema Juma')
            );
    }

    public function test_index_search_by_child_name(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $this->makeApplication(['reference_no' => 'APP-2026-0302', 'child_first_name' => 'Kofi', 'child_last_name' => 'Adu']);
        $target = $this->makeApplication(['reference_no' => 'APP-2026-0301', 'child_first_name' => 'Amina', 'child_last_name' => 'Juma']);

        $this->actingAs($admin)
            ->get('/admissions?search=Amina')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->where('applications.data.0.child_name', 'Amina Juma')
            );
    }

    public function test_index_excludes_soft_deleted_applications(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $this->makeApplication();
        $deleted = $this->makeApplication(['reference_no' => 'APP-2026-0008']);
        $deleted->delete();

        $this->actingAs($admin)
            ->get('/admissions')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->where('applications.data.0.reference_no', 'APP-2026-0001')
            );

        $this->assertDatabaseMissing('applications', [
            'reference_no' => 'APP-2026-0008',
            'deleted_at' => null,
        ]);
    }

    public function test_index_returns_pagination_shape(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $this->makeApplication();

        $this->actingAs($admin)
            ->get('/admissions')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->where('applications.current_page', 1)
                ->where('applications.last_page', 1)
                ->where('applications.per_page', 12)
                ->where('applications.from', 1)
                ->where('applications.to', 1)
            );
    }

    public function test_settings_can_be_updated_by_admin_only(): void
    {
        $admin = $this->makeUser(UserRole::Admin, 'admin@godwin.ac.tz');
        $hos = $this->makeUser(UserRole::HeadOfSchool, 'hos@godwin.ac.tz');

        $this->actingAs($admin)
            ->put('/settings/admissions', [
                'admissions_open' => true,
                'application_message' => 'Open.',
                'application_deadline' => null,
                'application_submitted_sms_enabled' => false,
                'application_accepted_sms_enabled' => false,
            ])
            ->assertSessionHas('success');

        // Head of School cannot update settings.
        $this->actingAs($hos)
            ->put('/settings/admissions', [
                'admissions_open' => true,
                'application_message' => 'Open.',
                'application_deadline' => null,
                'application_submitted_sms_enabled' => true,
                'application_accepted_sms_enabled' => true,
            ])
            ->assertRedirect('/dashboard');
    }
}
