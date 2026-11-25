<?php

namespace App\Services\Sms;

use App\Models\SchoolApplication;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Sends admission notification SMS (submission / acceptance) directly and
 * synchronously through the active DARSMS provider — no Laravel queues,
 * no background jobs, and no queue workers.
 *
 * The notifier is called after the application database transaction has
 * committed. Provider/network failures are caught and logged; they never
 * roll back a saved application or an accepted admission. Idempotency is
 * enforced by the shared SmsMessageService using a deterministic key per
 * application and notification type, so repeated clicks never duplicate SMS.
 */
class AdmissionSmsNotifier
{
    public function __construct(
        protected SmsMessageService $messageService,
        protected SmsRecipientService $recipientService,
    ) {}

    /**
     * Send the admission notification for the given application and type.
     *
     * @param  'submitted'|'accepted'  $notificationType
     */
    public function notify(SchoolApplication $application, string $notificationType): void
    {
        $enabledKey = $notificationType === 'accepted'
            ? 'admissions.application_accepted_sms_enabled'
            : 'admissions.application_submitted_sms_enabled';

        if (! (bool) Setting::get($enabledKey, true)) {
            Log::info('Admission SMS disabled, skipping', [
                'application_id' => $application->id,
                'reference_no' => $application->reference_no,
                'notification_type' => $notificationType,
                'error_category' => 'sms_disabled',
            ]);

            return;
        }

        $phone = $this->recipientService->normalize($application->parent_phone);

        if (! $phone) {
            Log::warning('Admission SMS: no valid phone number, skipping', [
                'application_id' => $application->id,
                'reference_no' => $application->reference_no,
                'parent_phone' => $application->parent_phone,
                'notification_type' => $notificationType,
                'error_category' => 'missing_phone',
            ]);

            return;
        }

        $message = $this->messageFor($notificationType);

        if (strlen($message) > 115) {
            Log::warning('Admission SMS: message exceeds 115 characters, skipping', [
                'application_id' => $application->id,
                'reference_no' => $application->reference_no,
                'notification_type' => $notificationType,
                'error_category' => 'message_too_long',
                'length' => strlen($message),
            ]);

            return;
        }

        $idempotencyKey = "admission_application_{$notificationType}:{$application->id}";

        try {
            $result = $this->messageService->send(
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
                        'notification_type' => $notificationType,
                    ],
                ]
            );

            $this->logSendResult($application, $notificationType, $result);
        } catch (\Throwable $e) {
            // A provider/network failure must never roll back a saved
            // application or an accepted admission.
            Log::error('Admission SMS send failed', [
                'application_id' => $application->id,
                'reference_no' => $application->reference_no,
                'notification_type' => $notificationType,
                'error_category' => 'send_exception',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log the provider send result with a stable error category.
     *
     * Never logs API keys, authorization headers or full provider payloads.
     * HTTP 202 (accepted/queued by the provider) is logged as accepted — not
     * delivered.
     */
    protected function logSendResult(SchoolApplication $application, string $notificationType, array $result): void
    {
        $context = [
            'application_id' => $application->id,
            'reference_no' => $application->reference_no,
            'notification_type' => $notificationType,
            'provider_status' => $result['providerStatus'] ?? null,
            'idempotent' => $result['idempotent'] ?? false,
            'status_code' => $result['status_code'] ?? null,
        ];

        if ($result['idempotent'] ?? false) {
            if (($result['status'] ?? null) === 'submission_unknown') {
                Log::warning('Admission SMS: unresolved submission requires manual reconciliation', array_merge($context, [
                    'error_category' => 'submission_unknown',
                    'status' => 'submission_unknown',
                ]));

                return;
            }

            Log::info('Admission SMS: duplicate send suppressed by idempotency', array_merge($context, [
                'error_category' => 'idempotent',
            ]));

            return;
        }

        if (! $result['success']) {
            $context['error'] = $result['error'] ?? 'Unknown';
            $context['code'] = $result['code'] ?? null;

            if (($result['code'] ?? null) === 'no_provider') {
                Log::error('Admission SMS: no active SMS provider configured', array_merge($context, [
                    'error_category' => 'no_provider',
                ]));

                return;
            }

            Log::warning('Admission SMS: provider rejected SMS', array_merge($context, [
                'error_category' => 'provider_rejection',
            ]));

            return;
        }

        Log::info('Admission SMS: accepted by provider', array_merge($context, [
            'error_category' => 'accepted',
        ]));
    }

    /** Exact Kiswahili message for the notification type. */
    protected function messageFor(string $type): string
    {
        return match ($type) {
            'accepted' => 'Hongera! Maombi ya mtoto wako yamekubaliwa God-Win. Tunafurahi kumpokea shuleni kwetu!',
            default => 'Asante kwa kuchagua God-Win! Tumepokea maombi yako. Tutakutumia ujumbe baada ya kuyapitia.',
        };
    }
}
