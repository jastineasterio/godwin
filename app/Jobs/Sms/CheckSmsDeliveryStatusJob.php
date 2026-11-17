<?php

namespace App\Jobs\Sms;

use App\Models\SmsMessage;
use App\Services\Sms\SmsDeliveryTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Check delivery status for a single SMS message.
 *
 * Dispatched after a message is sent, or scheduled periodically for
 * bulk status updates.
 */
class CheckSmsDeliveryStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $smsMessageId
    ) {
        $this->queue = 'sms';
        //
    }

    public function handle(SmsDeliveryTrackingService $trackingService): void
    {
        $message = SmsMessage::find($this->smsMessageId);

        if (! $message) {
            return;
        }

        // Only check messages still in a trackable state.
        if (! in_array($message->status, ['queued', 'sent'], true)) {
            return;
        }

        $trackingService->checkOne($message);
    }
}
