<?php

namespace Tests\Feature;

use App\Jobs\Sms\CheckSmsDeliveryStatusJob;
use App\Jobs\Sms\ProcessDeliveryStatusJob;
use App\Jobs\Sms\SendSmsJob;
use App\Models\SmsMessage;
use App\Models\SmsProviderConfig;
use App\Models\User;
use App\Enums\UserRole;
use App\Services\Sms\Providers\DarsmsProvider;
use App\Services\Sms\SmsDeliveryTrackingService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Queue and scheduler compatibility tests.
 *
 * Verifies every SMS job is assigned to the `sms` queue and that the
 * scheduled delivery poller only touches trackable messages.
 */
class SmsQueueSchedulerTest extends TestCase
{
    use RefreshDatabase;
    public function test_send_sms_job_uses_sms_queue(): void
    {
        $this->assertSame('sms', (new SendSmsJob(1, '255712345678', 'x'))->queue);
    }

    public function test_process_delivery_status_job_uses_sms_queue(): void
    {
        $this->assertSame('sms', (new ProcessDeliveryStatusJob())->queue);
    }

    public function test_check_sms_delivery_status_job_uses_sms_queue(): void
    {
        $this->assertSame('sms', (new CheckSmsDeliveryStatusJob(1))->queue);
    }

    public function test_scheduled_delivery_poll_registered_every_five_minutes_on_sms_queue_without_overlapping(): void
    {
        // Boot the schedule so the bootstrap/app.php registration runs.
        $this->artisan('schedule:run');

        $schedule = $this->app->make(Schedule::class);
        $event = $this->findScheduledEvent($schedule, 'sms.delivery-status-poll');

        $this->assertNotNull($event, 'ProcessDeliveryStatusJob must be registered on the scheduler');
        // Every five minutes.
        $this->assertSame('*/5 * * * *', $event->expression);
        // withoutOverlapping prevents concurrent duplicate polling runs.
        $this->assertTrue($event->withoutOverlapping, 'Scheduled delivery poll must use withoutOverlapping');
        // The job itself targets the `sms` queue (set in its constructor).
        $this->assertSame('sms', (new ProcessDeliveryStatusJob())->queue);
    }

    protected function findScheduledEvent(Schedule $schedule, string $description)
    {
        foreach ($schedule->events() as $event) {
            if (is_string($event->description) && $event->description === $description) {
                return $event;
            }
        }

        return null;
    }

    public function test_check_pending_only_polls_trackable_messages(): void
    {
        $this->seedProviderConfig();

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => ['messageId' => 'msg_1', 'status' => 'DELIVERED'],
            ], 200),
        ]);

        $trackable = SmsMessage::create([
            'to' => '255712345678',
            'message' => 'Trackable',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => 'msg_1',
            'status' => 'queued',
        ]);

        $alreadyDelivered = SmsMessage::create([
            'to' => '255712345679',
            'message' => 'Delivered',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => 'msg_2',
            'status' => 'delivered',
        ]);

        $noProviderId = SmsMessage::create([
            'to' => '255712345680',
            'message' => 'No provider id',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => null,
            'status' => 'queued',
        ]);

        $failed = SmsMessage::create([
            'to' => '255712345681',
            'message' => 'Failed',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => 'msg_4',
            'status' => 'failed',
        ]);

        $service = app(SmsDeliveryTrackingService::class);
        $result = $service->checkPending();

        $this->assertEquals(1, $result['checked']);
        $this->assertEquals(1, $result['updated']);

        $trackable->refresh();
        $this->assertEquals('delivered', $trackable->status);

        // Delivered, failed, and no-provider-id messages must not be polled again.
        $this->assertDatabaseHas('sms_messages', ['id' => $alreadyDelivered->id, 'status' => 'delivered']);
        $this->assertDatabaseHas('sms_messages', ['id' => $failed->id, 'status' => 'failed']);
        $this->assertDatabaseHas('sms_messages', ['id' => $noProviderId->id, 'status' => 'queued']);
    }

    public function test_delivery_event_recorded_when_status_changes(): void
    {
        $this->seedProviderConfig();

        $message = SmsMessage::create([
            'to' => '255712345678',
            'message' => 'Event test',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => 'msg_event',
            'status' => 'queued',
        ]);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => ['messageId' => 'msg_event', 'status' => 'SENT'],
            ], 200),
        ]);

        app(SmsDeliveryTrackingService::class)->checkPending();

        $this->assertDatabaseHas('sms_delivery_events', [
            'sms_message_id' => $message->id,
            'status' => 'sent',
            'provider_status' => 'SENT',
        ]);
    }

    public function test_no_duplicate_delivery_event_when_status_unchanged(): void
    {
        $this->seedProviderConfig();

        $message = SmsMessage::create([
            'to' => '255712345678',
            'message' => 'Unchanged test',
            'provider_type' => 'darsms',
            'provider_name' => 'DARSMS',
            'provider_message_id' => 'msg_unchanged',
            'status' => 'queued',
        ]);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => ['messageId' => 'msg_unchanged', 'status' => 'QUEUED'],
            ], 200),
        ]);

        app(SmsDeliveryTrackingService::class)->checkPending();

        $this->assertDatabaseMissing('sms_delivery_events', [
            'sms_message_id' => $message->id,
        ]);
    }

    protected function seedProviderConfig(): void
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

        $user = User::create([
            'name' => 'Scheduler User',
            'email' => 'scheduler@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::Admin,
            'status' => 'active',
        ]);
    }
}
