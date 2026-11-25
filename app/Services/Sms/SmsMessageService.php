<?php

namespace App\Services\Sms;

use App\Enums\UserRole;
use App\Jobs\Sms\SendSmsJob;
use App\Models\SmsCampaign;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * High-level SMS message sending service.
 *
 * Coordinates provider calls, message persistence, idempotency, and
 * queue dispatch for bulk sending. Head of School users interact with
 * this service; they never see provider credentials.
 */
class SmsMessageService
{
    protected SmsProviderResolver $resolver;

    public function __construct(SmsProviderResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Send a single SMS to a recipient.
     *
     * Idempotency: if an $idempotencyKey is provided and a message with that
     * key already exists for the same recipient, the existing record is
     * returned instead of sending again.
     */
    public function send(
        string $to,
        string $message,
        array $options = []
    ): array {
        $provider = $this->resolver->resolve();

        if (! $provider) {
            return [
                'success' => false,
                'error' => 'No active SMS provider configured.',
                'code' => 'no_provider',
            ];
        }

        if (! $provider->supportsSend()) {
            return [
                'success' => false,
                'error' => 'Provider does not support sending SMS.',
                'code' => 'unsupported_operation',
            ];
        }

        $idempotencyKey = $options['idempotency_key'] ?? null;
        $user = $options['user'] ?? null;
        $campaign = $options['campaign'] ?? null;
        $category = $options['category'] ?? 'general';
        $metadata = $options['metadata'] ?? [];

        // Idempotency check — prevent duplicate sends.
        if ($idempotencyKey) {
            $existing = SmsMessage::where('idempotency_key', $idempotencyKey)
                ->where('to', $to)
                ->first();

            if ($existing) {
                if (in_array($existing->status, ['queued', 'sent', 'delivered'], true)) {
                    return [
                        'success' => true,
                        'messageId' => $existing->provider_message_id,
                        'batchId' => $existing->batch_id,
                        'idempotent' => true,
                        'status' => $existing->status,
                        'providerStatus' => $existing->provider_status,
                    ];
                }

                return [
                    'success' => false,
                    'messageId' => $existing->provider_message_id,
                    'batchId' => $existing->batch_id,
                    'idempotent' => true,
                    'status' => $existing->status,
                    'providerStatus' => $existing->provider_status,
                    'error' => $existing->error_message,
                    'code' => 'idempotent_existing',
                    'resend_required' => $existing->status === 'submission_unknown',
                ];
            }
        }

        $result = $provider->send($to, $message, $options['sender_id'] ?? null);

        // Rate limited by the shared provider limit: do not persist a message
        // record yet. The caller (job) retries later, so no duplicate record
        // or duplicate SMS is created.
        if ($result['rate_limited'] ?? false) {
            return [
                'success' => false,
                'code' => 'rate_limited',
                'rate_limited' => true,
                'messageId' => null,
                'batchId' => null,
                'idempotent' => false,
                'status' => null,
                'providerStatus' => null,
                'error' => $result['error'] ?? 'Rate limit exceeded.',
                'retry_after' => $result['retry_after'] ?? null,
            ];
        }

        $messageRecord = null;

        try {
            $messageRecord = SmsMessage::create([
                'campaign_id' => $campaign?->id,
                'user_id' => $user?->id,
                'to' => $to,
                'message' => $message,
                'category' => $category,
                'sender_id' => $result['senderId'] ?? $options['sender_id'] ?? null,
                'provider_message_id' => $result['messageId'] ?? null,
                'batch_id' => $result['batchId'] ?? null,
                'provider_type' => $provider->type(),
                'provider_name' => $provider->name(),
                'provider_status' => $result['providerStatus'] ?? null,
                'status' => $this->mapProviderStatus(
                    $result['providerStatus'] ?? null,
                    $result['success'],
                    $result['ambiguous'] ?? false
                ),
                'sent_at' => $result['success'] ? now() : null,
                'delivered_at' => null,
                'error_message' => $result['error'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'metadata' => array_merge($metadata, [
                    'provider_request' => [
                        'to' => $to,
                        'message' => $message,
                        'sender_id' => $options['sender_id'] ?? null,
                        'ambiguous' => $result['ambiguous'] ?? false,
                    ],
                ]),
            ]);
        } catch (\Throwable $e) {
            // Provider accepted the message but the local record could not be
            // persisted. Do not throw: the caller's batch must continue so one
            // database failure does not mark every recipient as failed.
            Log::error('SMS provider accepted but local recording failed', [
                'to' => $to,
                'provider_message_id' => $result['messageId'] ?? null,
                'batch_id' => $result['batchId'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => true,
                'messageId' => $result['messageId'] ?? null,
                'batchId' => $result['batchId'] ?? null,
                'idempotent' => false,
                'status' => 'submission_unknown',
                'providerStatus' => $result['providerStatus'] ?? null,
                'error' => 'Provider accepted the message but it could not be recorded locally.',
                'rate_limited' => $result['rate_limited'] ?? false,
                'ambiguous' => false,
                'internal_id' => null,
            ];
        }

        return [
            'success' => $result['success'],
            'messageId' => $result['messageId'] ?? null,
            'batchId' => $result['batchId'] ?? null,
            'idempotent' => false,
            'status' => $messageRecord->status,
            'providerStatus' => $result['providerStatus'] ?? null,
            'error' => $result['error'] ?? null,
            'rate_limited' => $result['rate_limited'] ?? false,
            'ambiguous' => $result['ambiguous'] ?? false,
            'status_code' => $result['status_code'] ?? null,
            'internal_id' => $messageRecord->id,
        ];
    }

    /**
     * Queue bulk SMS sending. Does not block the HTTP request.
     *
     * Each recipient gets its own SmsMessage record. The campaign is created
     * first so progress can be tracked.
     */
    public function sendBulk(
        array $recipients,
        string $message,
        array $options = []
    ): array {
        $provider = $this->resolver->resolve();

        if (! $provider) {
            return [
                'success' => false,
                'error' => 'No active SMS provider configured.',
                'code' => 'no_provider',
            ];
        }

        if (! $provider->supportsBulkSend()) {
            return [
                'success' => false,
                'error' => 'Provider does not support bulk SMS.',
                'code' => 'unsupported_operation',
            ];
        }

        $user = $options['user'] ?? null;
        $category = $options['category'] ?? 'bulk';
        $senderId = $options['sender_id'] ?? null;
        $metadata = $options['metadata'] ?? [];

        // Deduplicate recipients.
        $recipients = array_values(array_unique($recipients));

        $campaign = SmsCampaign::create([
            'user_id' => $user?->id,
            'name' => $options['campaign_name'] ?? 'Bulk SMS',
            'message' => $message,
            'category' => $category,
            'sender_id' => $senderId,
            'provider_type' => $provider->type(),
            'provider_name' => $provider->name(),
            'total_recipients' => count($recipients),
            'status' => 'pending',
            'sent_at' => null,
            'completed_at' => null,
        ]);

        // Dispatch individual send jobs so each message is tracked separately.
        foreach ($recipients as $to) {
            SendSmsJob::dispatch($campaign->id, $to, $message, [
                'sender_id' => $senderId,
                'user_id' => $user?->id,
                'category' => $category,
                'metadata' => $metadata,
            ])->onQueue('sms');
        }

        $campaign->update(['status' => 'queued']);

        return [
            'success' => true,
            'campaign_id' => $campaign->id,
            'recipient_count' => count($recipients),
            'status' => 'queued',
        ];
    }

    /**
     * Check delivery status for a message and update its record.
     */
    public function checkDeliveryStatus(SmsMessage $message): array
    {
        $provider = $this->resolver->resolve();

        if (! $provider || ! $provider->supportsDeliveryStatus()) {
            return [
                'success' => false,
                'error' => 'Delivery status tracking not supported.',
                'code' => 'unsupported_operation',
            ];
        }

        if (! $message->provider_message_id) {
            return [
                'success' => false,
                'error' => 'No provider message ID to track.',
                'code' => 'no_provider_id',
            ];
        }

        $result = $provider->checkDeliveryStatus($message->provider_message_id);

        if ($result['success']) {
            $message->update([
                'provider_status' => $result['providerStatus'] ?? null,
                'status' => $this->mapProviderStatus(
                    $result['providerStatus'] ?? null,
                    true
                ),
                'sent_at' => $result['sentAt'] ?? $message->sent_at,
                'delivered_at' => $result['deliveredAt'] ?? null,
                'error_message' => $result['errorMessage'] ?? $message->error_message,
            ]);
        }

        return $result;
    }

    /**
     * Check SMS balance/credits.
     *
     * Successful results are cached for a short TTL so the balance endpoint is
     * not called on every dashboard render. Failures are never cached so the
     * next request retries the provider.
     */
    public function checkBalance(): array
    {
        $provider = $this->resolver->resolve();

        if (! $provider || ! $provider->supportsBalance()) {
            return [
                'success' => false,
                'balance' => null,
                'error' => 'Balance checking not supported by this provider.',
                'code' => 'unsupported_operation',
            ];
        }

        $cacheKey = 'sms.provider.balance.'.$provider->type();

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $provider->checkBalance();

        // Never cache a failure: the next request should retry the provider so
        // a transient outage does not stick as "last known balance."
        if (($result['success'] ?? false) === true) {
            Cache::put($cacheKey, $result, 300);
        }

        return $result;
    }

    /**
     * Get available sender IDs.
     */
    public function getSenderIds(): array
    {
        $provider = $this->resolver->resolve();

        if (! $provider || ! $provider->supportsSenderIds()) {
            return [
                'success' => false,
                'senderIds' => [],
                'error' => 'Sender ID lookup not supported by this provider.',
                'code' => 'unsupported_operation',
            ];
        }

        return $provider->getSenderIds();
    }

    /**
     * Test the active provider connection.
     */
    public function testConnection(): array
    {
        $provider = $this->resolver->resolve();

        if (! $provider || ! $provider->supportsConnectionTest()) {
            return [
                'success' => false,
                'connected' => false,
                'reason' => 'no_provider',
                'message' => 'No active SMS provider or connection testing not supported.',
            ];
        }

        return $provider->testConnection();
    }

    /**
     * Get provider capability flags for the UI.
     */
    public function getCapabilities(): array
    {
        $provider = $this->resolver->resolve();

        if (! $provider) {
            return [];
        }

        return [
            'send' => $provider->supportsSend(),
            'bulk_send' => $provider->supportsBulkSend(),
            'delivery_status' => $provider->supportsDeliveryStatus(),
            'balance' => $provider->supportsBalance(),
            'sender_ids' => $provider->supportsSenderIds(),
            'purchases' => $provider->supportsPurchases(),
            'purchase_history' => $provider->supportsPurchaseHistory(),
            'payment_retry' => $provider->supportsPaymentRetry(),
            'connection_test' => $provider->supportsConnectionTest(),
        ];
    }

    /**
     * Check if the given user is authorized to manage SMS settings (Admin only).
     */
    public function canManageSettings(User $user): bool
    {
        return $user->hasAnyRole([UserRole::Admin]);
    }

    /**
     * Check if the given user can send SMS (Head of School or Admin).
     */
    public function canSend(User $user): bool
    {
        return $user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool]);
    }

    /**
     * Manually resend an SMS message (e.g. one stuck in submission_unknown
     * after an ambiguous provider timeout).
     *
     * Authorized users only. Preserves idempotency: a resend for the same
     * original message can only be recorded once, so repeated manual resend
     * attempts cannot duplicate the SMS. The resend is audited via metadata
     * and a delivery event.
     */
    public function resendMessage(SmsMessage $message, User $user, ?string $reason = null): array
    {
        if (! $this->canSend($user)) {
            return [
                'success' => false,
                'error' => 'Unauthorized: only administrators and heads of school can resend SMS.',
                'code' => 'unauthorized',
            ];
        }

        if (! in_array($message->status, ['submission_unknown', 'failed'], true)) {
            return [
                'success' => false,
                'error' => 'Message cannot be resent in its current status.',
                'code' => 'not_resendable',
            ];
        }

        $provider = $this->resolver->resolve();

        if (! $provider || ! $provider->supportsSend()) {
            return [
                'success' => false,
                'error' => 'No active SMS provider configured.',
                'code' => 'no_provider',
            ];
        }

        return DB::transaction(function () use ($message, $user, $reason) {
            // Re-check under lock so concurrent retry attempts cannot both proceed.
            $message = SmsMessage::where('id', $message->id)
                ->lockForUpdate()
                ->first();

            if (! $message || ! in_array($message->status, ['submission_unknown', 'failed'], true)) {
                return [
                    'success' => false,
                    'error' => 'Message cannot be resent in its current status.',
                    'code' => 'not_resendable',
                ];
            }

            // Idempotency guard for the retry record itself, checked under the
            // same lock so concurrent retries cannot both reach the provider.
            $resendKey = 'sms_resend:'.$message->id;
            $alreadyRetried = SmsMessage::where('idempotency_key', $resendKey)
                ->where('to', $message->to)
                ->exists();

            if ($alreadyRetried) {
                return [
                    'success' => true,
                    'idempotent' => true,
                    'messageId' => null,
                    'status' => null,
                    'error' => null,
                    'code' => 'idempotent',
                ];
            }

            $result = $this->send(
                $message->to,
                $message->message,
                [
                    'sender_id' => $message->sender_id,
                    'user' => $user,
                    'campaign' => $message->campaign,
                    'category' => $message->category,
                    'idempotency_key' => $resendKey,
                    'metadata' => array_merge($message->metadata ?? [], [
                        'resend_of' => $message->id,
                        'resend_reason' => $reason,
                        'resend_by' => $user->id,
                        'resend_at' => now()->toISOString(),
                    ]),
                ]
            );

            if ($result['success'] && ! ($result['rate_limited'] ?? false) && ! ($result['idempotent'] ?? false)) {
                $message->deliveryEvents()->create([
                    'status' => $result['status'] ?? $message->status,
                    'provider_status' => $result['providerStatus'] ?? null,
                    'occurred_at' => now(),
                    'error_message' => 'Manual resend: '.($reason ?? 'no reason provided'),
                    'raw_data' => ['resend_of' => $message->id, 'resend_by' => $user->id],
                ]);
            }

            return $result;
        });
    }

    /**
     * Reconcile a submission_unknown message using the provider's message
     * status lookup. DARSMS exposes GET /integrations/sms/status/{id}, so an
     * accepted-but-unconfirmed submission can often be resolved to queued,
     * sent, delivered or failed without resending anything.
     */
    public function reconcileSubmissionUnknown(SmsMessage $message): array
    {
        if ($message->status !== 'submission_unknown') {
            return [
                'success' => false,
                'error' => 'Message is not in submission_unknown state.',
                'code' => 'not_reconcilable',
                'status' => $message->status,
            ];
        }

        $provider = $this->resolver->resolve();

        if (! $provider || ! $provider->supportsDeliveryStatus()) {
            return [
                'success' => false,
                'error' => 'Delivery status tracking not supported.',
                'code' => 'unsupported_operation',
                'status' => 'submission_unknown',
            ];
        }

        if (! $message->provider_message_id) {
            return [
                'success' => false,
                'error' => 'No provider message ID available to verify the submission.',
                'code' => 'no_provider_id',
                'status' => 'submission_unknown',
                'reconcilable' => false,
            ];
        }

        $result = $provider->checkDeliveryStatus($message->provider_message_id);

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Unable to verify submission with provider.',
                'code' => 'provider_lookup_failed',
                'status' => 'submission_unknown',
            ];
        }

        $previousStatus = $message->status;

        $message->update([
            'provider_status' => $result['providerStatus'] ?? null,
            'status' => $this->mapProviderStatus($result['providerStatus'] ?? null, true),
            'sent_at' => $result['sentAt'] ?? $message->sent_at,
            'delivered_at' => $result['deliveredAt'] ?? null,
            'error_message' => $result['errorMessage'] ?? null,
        ]);

        if ($message->status !== $previousStatus) {
            $message->deliveryEvents()->create([
                'status' => $message->status,
                'provider_status' => $result['providerStatus'] ?? null,
                'occurred_at' => now(),
                'error_message' => $result['errorMessage'] ?? null,
                'raw_data' => ['reconciled' => true, 'provider_message_id' => $message->provider_message_id],
            ]);
        }

        return [
            'success' => true,
            'status' => $message->status,
            'provider_status' => $message->provider_status,
            'messageId' => $message->provider_message_id,
            'reconciled' => true,
        ];
    }

    /**
     * Map a DARSMS provider status to our internal status.
     *
     * Ambiguous (transport-timeout) sends are never mapped to delivered or
     * definitely failed: they become "submission_unknown" so they can be
     * investigated and manually resent.
     */
    private function mapProviderStatus(?string $providerStatus, bool $success, bool $ambiguous = false): string
    {
        if ($ambiguous) {
            return 'submission_unknown';
        }

        if (! $success) {
            return 'failed';
        }

        return match ($providerStatus) {
            'QUEUED' => 'queued',
            'SENT' => 'sent',
            'DELIVERED' => 'delivered',
            'FAILED' => 'failed',
            default => 'queued',
        };
    }

    /**
     * Generate a deterministic idempotency key for results SMS.
     */
    public static function resultsIdempotencyKey(
        int $studentId,
        int $parentId,
        int $examinationId,
        string $message
    ): string {
        return 'results_sms:'.sha1("{$studentId}:{$parentId}:{$examinationId}:".hash('sha256', $message));
    }
}
