<?php

namespace App\Jobs\Sms;

use App\Services\Sms\SmsDeliveryTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Periodic job to check delivery status for all pending SMS messages.
 *
 * Meant to be scheduled via Laravel's scheduler (e.g. every 5 minutes).
 */
class ProcessDeliveryStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->queue = 'sms';
    }

    public function handle(SmsDeliveryTrackingService $trackingService): void
    {
        $result = $trackingService->checkPending();

        Log::info('SMS delivery status check completed', [
            'checked' => $result['checked'] ?? 0,
            'updated' => $result['updated'] ?? 0,
        ]);
    }
}
