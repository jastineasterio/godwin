<?php

namespace App\Jobs\Sms;

use App\Models\SmsCampaign;
use App\Models\SmsDeliveryEvent;
use App\Models\SmsMessage;
use App\Services\Sms\SmsMessageService;
use App\Services\Sms\SmsProviderResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send a single SMS via the active provider.
 *
 * This job is dispatched per-recipient for bulk campaigns so that each
 * message is tracked independently and failures don't block other
 * recipients.
 *
 * No provider credentials are stored in the job payload — the provider
 * is resolved from the encrypted config at job execution time.
 *
 * Rate limiting:
 *   - Shared DARSMS rate limits are enforced in the provider. When a send is
 *     throttled the job is released back to the queue with a delay, capped by
 *     $tries so there is no uncontrolled retry loop.
 *   - Ambiguous transport timeouts (provider may already have accepted the
 *     message) are recorded as "submission_unknown" and are NOT automatically
 *     retried.
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10; // seconds, exponential

    /**
     * @param  array{to: string, message: string, sender_id?: string, user_id?: int, category?: string, metadata?: array}  $data
     */
    public function __construct(
        public int $campaignId,
        public string $to,
        public string $message,
        public array $data = []
    ) {
        $this->queue = 'sms';
        //
    }

    public function handle(SmsProviderResolver $resolver, SmsMessageService $messageService): void
    {
        $campaign = SmsCampaign::find($this->campaignId);

        $result = $messageService->send(
            $this->to,
            $this->message,
            [
                'sender_id' => $this->data['sender_id'] ?? null,
                'user' => $this->data['user_id'] ? User::find($this->data['user_id']) : null,
                'campaign' => $campaign,
                'category' => $this->data['category'] ?? 'bulk',
                'metadata' => $this->data['metadata'] ?? [],
            ]
        );

        $job = $this->job;

        // Shared rate limit throttled the send: retry later, capped by tries.
        if ($result['rate_limited'] ?? false) {
            $attempts = $job ? $job->attempts() : 1;

            if ($attempts >= $this->tries) {
                $this->recordRateLimitedFailure($campaign, $result);

                return;
            }

            if ($job) {
                $job->release($this->backoff * (2 ** ($attempts - 1)));
            }

            return;
        }

        // Update campaign counters.
        if ($campaign) {
            $campaign->increment('sent_count');

            if ($result['success']) {
                if ($result['status'] === 'delivered') {
                    $campaign->increment('delivered_count');
                }
            } elseif (! ($result['ambiguous'] ?? false)) {
                // Ambiguous timeouts are not definite failures: do not count
                // them as failed; they await investigation / manual resend.
                $campaign->increment('failed_count');
            }

            // Mark campaign completed when all messages are processed.
            $processed = $campaign->messages()->count();
            if ($processed >= $campaign->total_recipients) {
                $campaign->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }
        }

        if (! $result['success'] && ! ($result['ambiguous'] ?? false)) {
            Log::warning('SMS send failed for job', [
                'to' => $this->to,
                'error' => $result['error'] ?? 'Unknown',
                'campaign_id' => $this->campaignId,
                'ambiguous' => $result['ambiguous'] ?? false,
            ]);
        } elseif ($result['ambiguous'] ?? false) {
            Log::warning('SMS send submission unknown (ambiguous timeout) for job', [
                'to' => $this->to,
                'error' => $result['error'] ?? 'Unknown',
                'campaign_id' => $this->campaignId,
            ]);
        }
    }

    protected function recordRateLimitedFailure(SmsCampaign $campaign, array $result): void
    {
        $message = SmsMessage::where('to', $this->to)
            ->where('campaign_id', $this->campaignId)
            ->whereNull('provider_message_id')
            ->whereIn('status', ['submission_unknown', 'failed'])
            ->latest()
            ->first();

        if (! $message) {
            $message = SmsMessage::create([
                'campaign_id' => $campaign?->id,
                'user_id' => null,
                'to' => $this->to,
                'message' => $this->message,
                'category' => $this->data['category'] ?? 'bulk',
                'sender_id' => $this->data['sender_id'] ?? null,
                'provider_message_id' => null,
                'batch_id' => null,
                'provider_type' => 'darsms',
                'provider_name' => 'DARSMS',
                'provider_status' => null,
                'status' => 'failed',
                'sent_at' => null,
                'delivered_at' => null,
                'error_message' => $result['error'] ?? 'Rate limit exceeded. Retries exhausted.',
                'idempotency_key' => null,
                'metadata' => [],
            ]);
        } else {
            $message->update([
                'status' => 'failed',
                'error_message' => $result['error'] ?? 'Rate limit exceeded. Retries exhausted.',
            ]);
        }

        if ($campaign) {
            $campaign->increment('failed_count');
        }

        Log::warning('SMS send rate-limit retries exhausted', [
            'to' => $this->to,
            'campaign_id' => $this->campaignId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        // Record the failure in the database for investigation.
        $message = SmsMessage::where('to', $this->to)
            ->where('campaign_id', $this->campaignId)
            ->whereNull('provider_message_id')
            ->first();

        if ($message) {
            $message->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }

        if ($campaign = SmsCampaign::find($this->campaignId)) {
            $campaign->increment('failed_count');
        }
    }
}
