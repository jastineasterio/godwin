<?php

namespace App\Jobs\Sms;

use App\Enums\ApplicationStatus;
use App\Models\SchoolApplication;
use App\Models\Setting;
use App\Services\Sms\SmsMessageService;
use App\Services\Sms\SmsRecipientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send a single Kiswahili admission notification SMS (submission or
 * acceptance) via the existing SmsMessageService infrastructure.
 *
 * This job is deliberately separate from SendSmsJob (which is
 * campaign-based). It receives only the application ID and notification
 * type, reloads the application at execution time, re-reads the relevant
 * notification setting, normalizes the parent phone, and uses a
 * deterministic idempotency key so repeated sends can never duplicate.
 *
 * No provider credentials are stored in the payload — the provider is
 * resolved from encrypted config at job execution time.
 *
 * Failures are logged with a stable error category. Permanent failures
 * (no provider, disabled notification, invalid phone, provider rejection)
 * are logged and finished without retrying. Transient failures (provider
 * unavailable, rate limits) are thrown so the queue retries them with the
 * configured backoff.
 */
class SendAdmissionNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10; // seconds, fixed between retry attempts

    public function __construct(
        public int $applicationId,
        public string $notificationType = 'submitted',
    ) {
        $this->queue = 'sms';
    }

    public function handle(SmsMessageService $messageService, SmsRecipientService $recipientService): void
    {
        $application = SchoolApplication::find($this->applicationId);

        if (! $application) {
            Log::warning('Admission notification job: application not found', [
                'application_id' => $this->applicationId,
                'notification_type' => $this->notificationType,
                'error_category' => 'application_not_found',
                'queue' => $this->queue,
            ]);

            return;
        }

        // Re-read the relevant notification setting at execution time so an
        // admin toggle flipped after dispatch is honoured.
        $enabled = $this->isEnabled($application);

        if (! $enabled) {
            Log::info('Admission notification job: SMS disabled, skipping', [
                'application_id' => $application->id,
                'reference_no' => $application->reference_no,
                'notification_type' => $this->notificationType,
                'error_category' => 'sms_disabled',
                'queue' => $this->queue,
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        $phone = $recipientService->normalize($application->parent_phone);

        if (! $phone) {
            Log::warning('Admission notification job: no valid phone number, skipping', [
                'application_id' => $application->id,
                'reference_no' => $application->reference_no,
                'parent_phone' => $application->parent_phone,
                'notification_type' => $this->notificationType,
                'error_category' => 'missing_phone',
                'queue' => $this->queue,
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        $message = $this->messageFor($this->notificationType);

        if (strlen($message) > 115) {
            Log::warning('Admission notification job: message exceeds 115 characters, skipping', [
                'application_id' => $application->id,
                'reference_no' => $application->reference_no,
                'notification_type' => $this->notificationType,
                'error_category' => 'message_too_long',
                'length' => strlen($message),
                'queue' => $this->queue,
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        $idempotencyKey = $this->idempotencyKey($application->id);

        $result = $messageService->send(
            $phone,
            $message,
            [
                'idempotency_key' => $idempotencyKey,
                'user' => null,
                'campaign' => null,
                'category' => 'admission',
                'sender_id' => null,
                'metadata' => [
                    'application_id' => $application->id,
                    'reference_no' => $application->reference_no,
                    'notification_type' => $this->notificationType,
                ],
            ]
        );

        $this->logSendResult($application, $result);

        // A missing provider is a configuration error: log it clearly and fail
        // the job so it is retried the configured number of times, then
        // recorded as a failed job for the operator (idempotency prevents any
        // duplicate SMS when it is retried after the provider is configured).
        if (($result['code'] ?? null) === 'no_provider') {
            throw new \RuntimeException($result['error'] ?? 'No active SMS provider configured.');
        }

        // Permanent failures (provider rejection, disabled notification,
        // invalid phone) are logged above and the job finishes without
        // retrying. Transient failures (provider unavailable, rate limits,
        // server errors) are thrown so the queue retries them with backoff.
        if ($this->isTransientFailure($result)) {
            throw new \RuntimeException($result['error'] ?? 'SMS provider failure.');
        }
    }

    /**
     * Log the provider send result with a stable error category.
     *
     * Never logs API keys, authorization headers or full provider payloads.
     * HTTP 202 (accepted/queued by the provider) is logged as accepted — not
     * delivered.
     */
    protected function logSendResult(SchoolApplication $application, array $result): void
    {
        $context = [
            'application_id' => $application->id,
            'reference_no' => $application->reference_no,
            'notification_type' => $this->notificationType,
            'queue' => $this->queue,
            'attempt' => $this->attempts(),
            'provider_status' => $result['providerStatus'] ?? null,
            'idempotent' => $result['idempotent'] ?? false,
            'status_code' => $result['status_code'] ?? null,
        ];

        if ($result['idempotent'] ?? false) {
            // An existing record was found for this idempotency key.
            // `submission_unknown` is never treated as delivered: it is
            // flagged as unresolved and left for manual reconciliation.
            if (($result['status'] ?? null) === 'submission_unknown') {
                Log::warning('Admission notification job: unresolved submission requires manual reconciliation', array_merge($context, [
                    'error_category' => 'submission_unknown',
                    'status' => 'submission_unknown',
                ]));

                return;
            }

            Log::info('Admission notification job: duplicate send suppressed by idempotency', array_merge($context, [
                'error_category' => 'idempotent',
            ]));

            return;
        }

        if (! $result['success']) {
            $context['error'] = $result['error'] ?? 'Unknown';
            $context['code'] = $result['code'] ?? null;

            if (($result['code'] ?? null) === 'no_provider') {
                Log::error('Admission notification job: no active SMS provider configured', array_merge($context, [
                    'error_category' => 'no_provider',
                ]));

                return;
            }

            if ($this->isTransientFailure($result)) {
                Log::warning('Admission notification job: transient provider failure, will retry', array_merge($context, [
                    'error_category' => 'connection_failure',
                ]));

                return;
            }

            Log::warning('Admission notification job: provider rejected SMS', array_merge($context, [
                'error_category' => 'provider_rejection',
            ]));

            return;
        }

        // Provider accepted the message (HTTP 202 / QUEUED / SENT). This is
        // acceptance by the provider, not delivery confirmation.
        Log::info('Admission notification job: SMS accepted by provider', array_merge($context, [
            'error_category' => 'accepted',
        ]));
    }

    /**
     * Determine whether a provider result is a transient failure that should
     * be retried by the queue (connection errors, DNS failures, timeouts,
     * rate limits, server errors). Permanent errors are not retried.
     */
    protected function isTransientFailure(array $result): bool
    {
        if (($result['rate_limited'] ?? false) === true) {
            return true;
        }

        if (($result['ambiguous'] ?? false) === true) {
            return true;
        }

        $statusCode = (int) ($result['status_code'] ?? 0);

        // Only positive server errors (5xx) are retried; 4xx provider
        // rejections and unknown errors are permanent for this message.
        return $statusCode > 0 && $statusCode >= 500;
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    /** Whether the notification type is currently enabled via settings. */
    protected function isEnabled(SchoolApplication $application): bool
    {
        $key = $this->notificationType === 'accepted'
            ? 'admissions.application_accepted_sms_enabled'
            : 'admissions.application_submitted_sms_enabled';

        return (bool) Setting::get($key, true);
    }

    /** Exact Kiswahili message for the notification type. */
    protected function messageFor(string $type): string
    {
        return match ($type) {
            'accepted' => 'Hongera! Maombi ya mtoto wako yamekubaliwa God-Win. Tunafurahi kumpokea shuleni kwetu!',
            default => 'Asante kwa kuchagua God-Win! Tumepokea maombi yako. Tutakutumia ujumbe baada ya kuyapitia.',
        };
    }

    /** Deterministic idempotency key based on application ID and type. */
    protected function idempotencyKey(int $applicationId): string
    {
        return "admission_application_{$this->notificationType}:{$applicationId}";
    }
}
