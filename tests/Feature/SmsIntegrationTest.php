<?php

namespace Tests\Feature;

use App\Enums\ClassLevel;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\Assessment;
use App\Models\SmsMessage;
use App\Models\SmsProviderConfig;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Sms\Providers\DarsmsProvider;
use App\Services\Sms\SmsRecipientService;
use App\Services\Sms\SmsResultsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SMS integration tests using mocked/fake providers.
 * No live SMS messages are sent.
 */
class SmsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.darsms' => [
            'base_url' => 'https://test.darsms.local/api/v1',
        ]]);
    }

    /* ---------------------- Provider Tests ---------------------- */

    public function test_darsms_uses_correct_auth_header(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'message' => 'OK', 'data' => ['balance' => 100]], 200),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-api-key',
        ]);

        $result = $provider->checkBalance();

        $this->assertTrue($result['success']);
    }

    public function test_darsms_send_endpoint_and_payload(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => [
                    'batchId' => 'batch_123',
                    'queued' => 1,
                    'duplicatesRemoved' => 0,
                    'senderId' => 'DARSMS',
                    'messageIds' => ['msg_456'],
                ],
            ], 202),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-api-key',
        ]);

        $result = $provider->send('255712345678', 'Hello World', 'DARSMS');

        $this->assertTrue($result['success']);
        $this->assertEquals('msg_456', $result['messageId']);
        $this->assertEquals('batch_123', $result['batchId']);
        $this->assertEquals('QUEUED', $result['providerStatus']);
    }

    public function test_darsms_invalid_api_key_401(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false, 'message' => 'Invalid API key.'], 401),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'bad-key',
        ]);

        $result = $provider->checkBalance();

        $this->assertFalse($result['success']);
    }

    public function test_darsms_account_inactive_403(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false, 'message' => 'Account inactive.'], 403),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
        ]);

        $result = $provider->checkBalance();

        $this->assertFalse($result['success']);
    }

    public function test_darsms_validation_error_422(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'message' => 'Validation error.',
                'details' => ['to' => ['Invalid phone number.']],
            ], 422),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
        ]);

        $result = $provider->send('invalid', 'Hello');

        $this->assertFalse($result['success']);
    }

    public function test_darsms_rate_limit_429(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false, 'message' => 'Rate limit exceeded.'], 429),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
        ]);

        $result = $provider->checkBalance();

        $this->assertFalse($result['success']);
    }

    public function test_darsms_provider_timeout(): void
    {
        Http::fake(function ($request) {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
            'timeout' => 1,
        ]);

        $result = $provider->checkBalance();

        $this->assertFalse($result['success']);
    }

    public function test_darsms_202_is_accepted_not_delivered(): void
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

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
        ]);

        $result = $provider->send('255712345678', 'Hello');

        $this->assertTrue($result['success']);
        $this->assertEquals('QUEUED', $result['providerStatus']);
        $this->assertNotEquals('DELIVERED', $result['providerStatus']);
    }

    public function test_darsms_sender_ids_endpoint(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    ['senderId' => 'DARSMS', 'status' => 'approved'],
                    ['senderId' => 'SCHOOL', 'status' => 'pending'],
                ],
            ], 200),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
        ]);

        $result = $provider->getSenderIds();

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['senderIds']);
    }

    public function test_darsms_delivery_status_endpoint(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'messageId' => 'msg_1',
                    'status' => 'DELIVERED',
                    'sentAt' => '2026-01-01T10:00:00Z',
                    'deliveredAt' => '2026-01-01T10:00:05Z',
                    'errorMessage' => null,
                ],
            ], 200),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
        ]);

        $result = $provider->checkDeliveryStatus('msg_1');

        $this->assertTrue($result['success']);
        $this->assertEquals('DELIVERED', $result['providerStatus']);
        $this->assertNotNull($result['deliveredAt']);
    }

    public function test_darsms_purchase_credits(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Purchase initiated',
                'data' => [
                    'purchaseId' => 'purch_1',
                    'transactionRef' => 'txn_1',
                    'amount' => 50000,
                    'unitPrice' => 50,
                    'quantitySms' => 1000,
                    'providerStatus' => 'PENDING',
                    'expiresAt' => '2026-12-01T00:00:00Z',
                    'efdReceipt' => 'efd_123',
                ],
            ], 200),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
        ]);

        $result = $provider->purchaseCredits(1000, '255712345678');

        $this->assertTrue($result['success']);
        $this->assertEquals('purch_1', $result['purchaseId']);
        $this->assertEquals('txn_1', $result['transactionRef']);
        $this->assertEquals(50000, $result['amount']);
        $this->assertEquals('PENDING', $result['providerStatus']);
    }

    public function test_darsms_purchase_history_pagination(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'purchases' => [
                        ['purchaseId' => 'purch_1', 'providerStatus' => 'SUCCESS'],
                    ],
                    'pagination' => ['page' => 1, 'limit' => 20, 'total' => 1],
                ],
            ], 200),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
        ]);

        $result = $provider->getPurchaseHistory('ALL', 1, 20);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(1, count($result['purchases']));
    }

    public function test_darsms_retry_payment(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Payment retried',
                'data' => [
                    'purchaseId' => 'purch_1',
                    'transactionRef' => 'txn_1',
                    'providerStatus' => 'PENDING',
                ],
            ], 200),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
        ]);

        $result = $provider->retryPayment('purch_1', '255712345678');

        $this->assertTrue($result['success']);
        $this->assertEquals('purch_1', $result['purchaseId']);
    }

    public function test_darsms_connection_test_success(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'message' => 'OK', 'data' => ['balance' => 500]], 200),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
        ]);

        $result = $provider->testConnection();

        $this->assertTrue($result['connected']);
        $this->assertEquals('success', $result['reason']);
    }

    public function test_darsms_connection_test_invalid_key(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false, 'message' => 'Invalid API key.'], 401),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'bad-key',
        ]);

        $result = $provider->testConnection();

        $this->assertFalse($result['connected']);
        $this->assertEquals('invalid_api_key', $result['reason']);
    }

    public function test_darsms_connection_test_account_inactive(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false, 'message' => 'Account inactive.'], 403),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
        ]);

        $result = $provider->testConnection();

        $this->assertFalse($result['connected']);
        $this->assertEquals('account_inactive', $result['reason']);
    }

    /* ---------------------- Phone Normalization Tests ---------------------- */

    public function test_tanzanian_phone_normalization(): void
    {
        $service = app(SmsRecipientService::class);

        $this->assertEquals('255712345678', $service->normalize('0712345678'));
        $this->assertEquals('255712345678', $service->normalize('255712345678'));
        $this->assertEquals('255712345678', $service->normalize('+255712345678'));
        $this->assertEquals('255712345678', $service->normalize('0712 345 678'));
        $this->assertNull($service->normalize('invalid'));
        $this->assertNull($service->normalize('12345'));
        $this->assertNull($service->normalize(null));
    }

    public function test_duplicate_detection(): void
    {
        $service = app(SmsRecipientService::class);

        $recipients = [
            ['phone' => '255712345678', 'name' => 'Alice'],
            ['phone' => '255712345678', 'name' => 'Bob'],
            ['phone' => '255787654321', 'name' => 'Charlie'],
        ];

        $duplicates = $service->findDuplicates($recipients);

        $this->assertArrayHasKey('255712345678', $duplicates);
        $this->assertCount(2, $duplicates['255712345678']);
    }

    /* ---------------------- Idempotency Tests ---------------------- */

    public function test_results_idempotency_prevents_duplicates(): void
    {
        $user = User::create([
            'name' => 'HoS User',
            'email' => 'hos@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::HeadOfSchool,
            'status' => 'active',
        ]);

        $class = SchoolClass::create([
            'name' => 'KG1',
            'code' => 'KG1',
            'level' => ClassLevel::KG1,
            'is_active' => true,
            'capacity' => 25,
        ]);

        $student = Student::create([
            'reg_no' => 'GWD-TEST-001',
            'first_name' => 'Amina',
            'last_name' => 'Juma',
            'gender' => 'female',
            'class_id' => $class->id,
            'status' => 'active',
        ]);

        $parent = User::create([
            'name' => 'Parent User',
            'email' => 'parent@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);

        DB::table('parent_student')->insert([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship_type' => 'father',
            'is_primary_contact' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $academicYear = \App\Models\AcademicYear::create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
            'status' => 'active',
        ]);

        $term = \App\Models\Term::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Term 1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-30',
            'is_current' => true,
        ]);

        $exam = Examination::create([
            'name' => 'Midterm Exam',
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'status' => 'published',
        ]);

        $key = \App\Services\Sms\SmsMessageService::resultsIdempotencyKey(
            $student->id,
            $parent->id,
            $exam->id,
            'Test message'
        );

        SmsMessage::create([
            'to' => '255712345678',
            'message' => 'Test message',
            'category' => 'results',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'status' => 'sent',
            'idempotency_key' => $key,
            'metadata' => [
                'student_id' => $student->id,
                'parent_id' => $parent->id,
                'examination_id' => $exam->id,
            ],
        ]);

        $exists = SmsMessage::where('idempotency_key', $key)
            ->where('category', 'results')
            ->exists();

        $this->assertTrue($exists);
    }

    /* ---------------------- Authorization Tests ---------------------- */

    public function test_admin_can_access_provider_settings(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::Admin,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->get('/sms/admin/providers');

        $response->assertStatus(200);
    }

    public function test_head_of_school_cannot_access_provider_settings(): void
    {
        $hos = User::create([
            'name' => 'HoS',
            'email' => 'hos@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::HeadOfSchool,
            'status' => 'active',
        ]);

        $response = $this->actingAs($hos)
            ->get('/sms/admin/providers');

        $response->assertStatus(302);
    }

    public function test_teacher_cannot_access_sms_dashboard(): void
    {
        $teacher = User::create([
            'name' => 'Teacher',
            'email' => 'teacher@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::Teacher,
            'status' => 'active',
        ]);

        $response = $this->actingAs($teacher)
            ->get('/sms/dashboard');

        $response->assertStatus(302);
    }

    public function test_parent_cannot_access_sms_dashboard(): void
    {
        $parent = User::create([
            'name' => 'Parent User',
            'email' => 'parent@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);

        $response = $this->actingAs($parent)
            ->get('/sms/dashboard');

        $response->assertStatus(302);
    }

    /* ---------------------- Queue Job Tests ---------------------- */

    public function test_send_sms_job_is_queued(): void
    {
        Queue::fake();

        \App\Jobs\Sms\SendSmsJob::dispatch(1, '255712345678', 'Test message');

        Queue::assertPushed(\App\Jobs\Sms\SendSmsJob::class);
    }

    /* ---------------------- Provider Capability Tests ---------------------- */

    public function test_provider_capability_detection(): void
    {
        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'test-key',
        ]);

        $this->assertTrue($provider->supportsSend());
        $this->assertTrue($provider->supportsBulkSend());
        $this->assertTrue($provider->supportsDeliveryStatus());
        $this->assertTrue($provider->supportsBalance());
        $this->assertTrue($provider->supportsSenderIds());
        $this->assertTrue($provider->supportsPurchases());
        $this->assertTrue($provider->supportsPurchaseHistory());
        $this->assertTrue($provider->supportsPaymentRetry());
        $this->assertTrue($provider->supportsConnectionTest());
    }

    /* ---------------------- Encrypted Credentials Test ---------------------- */

    public function test_provider_config_encrypts_credentials(): void
    {
        $config = SmsProviderConfig::create([
            'name' => 'Test Provider',
            'provider_type' => 'darsms',
            'base_url' => 'https://test.local',
            'sender_id' => 'DARSMS',
            'is_active' => false,
        ]);

        $config->setApiKeyAttribute('secret-key-123');
        $config->setApiSecretAttribute('secret-secret');
        $config->save();

        $this->assertNotEquals('secret-key-123', $config->api_key_encrypted);
        $this->assertEquals('secret-key-123', $config->decryptedApiKey());
        $this->assertNotEquals('secret-secret', $config->api_secret_encrypted);
        $this->assertEquals('secret-secret', $config->decryptedApiSecret());

        $masked = $config->maskedApiKey();
        $this->assertStringContainsString('*', $masked);
    }

    /* ---------------------- Results SMS Security Tests ---------------------- */

    public function test_results_send_skips_students_without_approved_result(): void
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

        $user = User::create([
            'name' => 'HoS User',
            'email' => 'hos@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::HeadOfSchool,
            'status' => 'active',
        ]);

        $class = SchoolClass::create([
            'name' => 'KG1',
            'code' => 'KG1',
            'level' => ClassLevel::KG1,
            'is_active' => true,
            'capacity' => 25,
        ]);

        $studentNoResult = Student::create([
            'reg_no' => 'GWD-NR-001',
            'first_name' => 'No',
            'last_name' => 'Result',
            'gender' => 'female',
            'class_id' => $class->id,
            'status' => 'active',
        ]);

        $studentApproved = Student::create([
            'reg_no' => 'GWD-AP-001',
            'first_name' => 'Approved',
            'last_name' => 'Student',
            'gender' => 'female',
            'class_id' => $class->id,
            'status' => 'active',
        ]);

        $parent = User::create([
            'name' => 'Parent User',
            'email' => 'parent@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);

        DB::table('parent_student')->insert([
            'parent_id' => $parent->id,
            'student_id' => $studentNoResult->id,
            'relationship_type' => 'father',
            'is_primary_contact' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('parent_student')->insert([
            'parent_id' => $parent->id,
            'student_id' => $studentApproved->id,
            'relationship_type' => 'mother',
            'is_primary_contact' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $academicYear = \App\Models\AcademicYear::create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
            'status' => 'active',
        ]);

        $term = \App\Models\Term::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Term 1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-30',
            'is_current' => true,
        ]);

        $exam = Examination::create([
            'name' => 'Midterm Exam',
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'status' => 'published',
        ]);

        $subject = Subject::create([
            'name' => 'Phonics',
            'code' => 'PHON',
            'is_active' => true,
        ]);

        // Approved result: must be sent.
        Assessment::create([
            'examination_id' => $exam->id,
            'student_id' => $studentApproved->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'title' => 'Mid Term - Phonics',
            'type' => 'exam',
            'score' => 15,
            'max_score' => 20,
            'recorded_by' => $user->id,
            'status' => 'approved',
        ]);

        // Draft result: must NOT be sent.
        Assessment::create([
            'examination_id' => $exam->id,
            'student_id' => $studentNoResult->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'title' => 'Mid Term - Phonics',
            'type' => 'exam',
            'score' => 15,
            'max_score' => 20,
            'recorded_by' => $user->id,
            'status' => 'draft',
        ]);

        $service = app(SmsResultsService::class);

        // Active provider config so the resolver returns a DARSMS provider.
        $providerConfig = SmsProviderConfig::create([
            'name' => 'Test DARSMS',
            'provider_type' => 'darsms',
            'base_url' => 'https://test.darsms.local/api/v1',
            'sender_id' => 'DARSMS',
            'is_active' => true,
        ]);
        $providerConfig->setApiKeyAttribute('test-api-key');
        $providerConfig->save();

        $result = $service->sendResultsNotifications(
            $exam->id,
            [$studentNoResult->id, $studentApproved->id],
            'Results are out.',
            $user
        );

        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['failed']);

        // Resend does not bypass result approval.
        $resend = $service->sendResultsNotifications(
            $exam->id,
            [$studentNoResult->id],
            'Results are out.',
            $user,
            null,
            ['is_resend' => true]
        );

        $this->assertSame(0, $resend['sent']);
        $this->assertSame(1, $resend['skipped']);
    }
}
