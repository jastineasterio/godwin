<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Jobs\Sms\SendAdmissionNotificationJob;
use App\Models\SchoolApplication;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\SmsProviderConfig;
use App\Services\Sms\SmsMessageService;
use App\Services\Sms\SmsRecipientService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Tests\TestCase;

/**
 * SendAdmissionNotificationJob behaviour tests.
 *
 * No live SMS is sent. The DARSMS provider is faked via Http::fake() and
 * the job is executed directly (the suite uses the sync queue connection).
 * Log entries are captured through a Log listener so error categories can
 * be asserted without touching the real log file.
 */
class SmsAdmissionNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_uses_sms_queue_with_controlled_retries(): void
    {
        $job = new SendAdmissionNotificationJob(1, 'submitted');

        $this->assertSame('sms', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame(10, $job->backoff);
    }

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

    protected function fakeProviderAccepted(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => [
                    'batchId' => 'batch_1',
                    'queued' => 1,
                    'messageIds' => ['msg_1'],
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

    /**
     * Run a callback while capturing log entries, then return them.
     */
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

    public function test_handle_skips_when_submission_sms_disabled(): void
    {
        $this->seedActiveProvider();
        Setting::put('admissions.application_submitted_sms_enabled', 'false', 'admissions');

        $entries = $this->withLogsCaptured(fn () => $this->runJob($this->makeApplication(), 'submitted'));

        $this->assertTrue($this->hasLogEntry($entries, 'info', 'SMS disabled, skipping', 'sms_disabled'));
        $this->assertCount(0, $this->admissionMessages());
    }

    public function test_handle_skips_when_acceptance_sms_disabled(): void
    {
        $this->seedActiveProvider();
        Setting::put('admissions.application_accepted_sms_enabled', 'false', 'admissions');

        $this->withLogsCaptured(fn () => $this->runJob($this->makeApplication(), 'accepted'));

        $this->assertCount(0, $this->admissionMessages());
    }

    public function test_handle_skips_when_phone_number_invalid(): void
    {
        $this->seedActiveProvider();

        $application = $this->makeApplication(['parent_phone' => 'invalid-phone']);

        $entries = $this->withLogsCaptured(fn () => $this->runJob($application));

        $this->assertTrue($this->hasLogEntry($entries, 'warning', 'no valid phone number, skipping', 'missing_phone'));
        $this->assertCount(0, $this->admissionMessages());
    }

    public function test_handle_throws_when_no_active_provider(): void
    {
        // No SmsProviderConfig exists in the database.
        $application = $this->makeApplication();

        $entries = $this->withLogsCaptured(fn () => $this->expectExceptionAndRun($application));

        $this->assertTrue($this->hasLogEntry($entries, 'error', 'no active SMS provider configured', 'no_provider'));
    }

    protected function expectExceptionAndRun(SchoolApplication $application): void
    {
        $this->expectException(\RuntimeException::class);

        $this->runJob($application);
    }

    public function test_handle_retries_on_transient_provider_failure(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderFails();

        $application = $this->makeApplication();

        $entries = $this->withLogsCaptured(fn () => $this->expectExceptionAndRun($application));

        $this->assertTrue($this->hasLogEntry($entries, 'warning', 'transient provider failure, will retry', 'connection_failure'));
    }

    public function test_handle_does_not_throw_on_provider_rejection(): void
    {
        $this->seedActiveProvider();
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'message' => 'Invalid phone number.',
            ], 400),
        ]);

        $this->withLogsCaptured(fn () => $this->runJob($this->makeApplication()));

        $this->assertCount(1, $this->admissionMessages());
        $this->assertSame('failed', $this->admissionMessages()[0]->status);
    }

    public function test_handle_accepts_202_as_accepted_not_delivered(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderAccepted();

        $application = $this->makeApplication();

        $entries = $this->withLogsCaptured(fn () => $this->runJob($application));

        $this->assertTrue($this->hasLogEntry($entries, 'info', 'SMS accepted by provider', 'accepted'));

        $messages = $this->admissionMessages();

        $this->assertCount(1, $messages);
        $this->assertSame('queued', $messages[0]->status);
        $this->assertSame('QUEUED', $messages[0]->provider_status);
        $this->assertNull($messages[0]->delivered_at);
    }

    public function test_repeated_handle_calls_do_not_duplicate_sms(): void
    {
        $this->seedActiveProvider();
        $this->fakeProviderAccepted();

        $application = $this->makeApplication();

        $sendCount = 0;
        Http::fake(function () use (&$sendCount) {
            $sendCount++;

            return Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => [
                    'batchId' => 'batch_'.$sendCount,
                    'queued' => 1,
                    'messageIds' => ['msg_'.$sendCount],
                ],
            ], 202);
        });

        $this->withLogsCaptured(fn () => $this->runJob($application));
        $this->withLogsCaptured(fn () => $this->runJob($application));

        $this->assertSame(1, $sendCount, 'Provider must be called once thanks to idempotency');
        $this->assertCount(1, $this->admissionMessages());
    }

    public function test_job_rechecks_sms_switch_at_execution_time(): void
    {
        $this->seedActiveProvider();
        Setting::put('admissions.application_submitted_sms_enabled', 'false', 'admissions');

        $application = $this->makeApplication();

        $this->withLogsCaptured(fn () => $this->runJob($application, 'submitted'));

        $this->assertCount(0, $this->admissionMessages());

        // Flipping the switch on enables sending for the same application.
        Setting::put('admissions.application_submitted_sms_enabled', 'true', 'admissions');
        $this->fakeProviderAccepted();

        $this->withLogsCaptured(fn () => $this->runJob($application, 'submitted'));

        $this->assertCount(1, $this->admissionMessages());
        $this->assertSame('queued', $this->admissionMessages()[0]->status);
    }

    protected function messageFor(string $type): string
    {
        $job = app(SendAdmissionNotificationJob::class, [
            'applicationId' => 1,
            'notificationType' => $type,
        ]);

        $method = new \ReflectionMethod($job, 'messageFor');
        $method->setAccessible(true);

        return $method->invoke($job, $type);
    }

    public function test_admission_messages_match_approved_kiswahili_text(): void
    {
        $this->assertSame(
            'Asante kwa kuchagua God-Win! Tumepokea maombi yako. Tutakutumia ujumbe baada ya kuyapitia.',
            $this->messageFor('submitted'),
        );

        $this->assertSame(
            'Hongera! Maombi ya mtoto wako yamekubaliwa God-Win. Tunafurahi kumpokea shuleni kwetu!',
            $this->messageFor('accepted'),
        );
    }

    public function test_admission_messages_do_not_exceed_115_character_limit(): void
    {
        $this->assertLessThanOrEqual(115, strlen($this->messageFor('submitted')));
        $this->assertLessThanOrEqual(115, strlen($this->messageFor('accepted')));
    }
}
