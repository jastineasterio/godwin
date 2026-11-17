<?php

namespace App\Services\Sms;

use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Delivery tracking service for SMS messages.
 *
 * Polls provider delivery status for messages that support tracking.
 * Designed to be called from a scheduled command or queue job, never
 * blocking the Head of School's web request.
 */
class SmsDeliveryTrackingService
{
    protected SmsProviderResolver $resolver;

    public function __construct(SmsProviderResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Check delivery status for all messages that are still in a
     * trackable state (queued, sent) and have a provider message ID.
     */
    public function checkPending(?User $user = null): array
    {
        $provider = $this->resolver->resolve();

        if (! $provider || ! $provider->supportsDeliveryStatus()) {
            return [
                'success' => false,
                'checked' => 0,
                'updated' => 0,
                'error' => 'Delivery status tracking not supported.',
            ];
        }

        $query = SmsMessage::whereIn('status', ['queued', 'sent'])
            ->whereNotNull('provider_message_id');

        if ($user) {
            $query->where('user_id', $user->id);
        }

        $messages = $query->limit(100)->get();
        $checked = 0;
        $updated = 0;

        foreach ($messages as $message) {
            $checked++;

            try {
                $result = $provider->checkDeliveryStatus($message->provider_message_id);

                if ($result['success']) {
                    $previousStatus = $message->status;

                    $message->update([
                        'provider_status' => $result['providerStatus'] ?? null,
                        'status' => $this->mapStatus($result['providerStatus'] ?? null),
                        'sent_at' => $result['sentAt'] ?? $message->sent_at,
                        'delivered_at' => $result['deliveredAt'] ?? null,
                        'error_message' => $result['errorMessage'] ?? null,
                    ]);

                    if ($message->status !== $previousStatus) {
                        $updated++;

                        $this->recordEvent($message, $message->status, $result['providerStatus'] ?? null, $result['errorMessage'] ?? null);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('SMS delivery status check failed for message #' . $message->id, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'checked' => $checked,
            'updated' => $updated,
        ];
    }

    /**
     * Check delivery status for a single message.
     */
    public function checkOne(SmsMessage $message): array
    {
        $provider = $this->resolver->resolve();

        if (! $provider || ! $provider->supportsDeliveryStatus()) {
            return [
                'success' => false,
                'error' => 'Delivery status tracking not supported.',
            ];
        }

        if (! $message->provider_message_id) {
            return [
                'success' => false,
                'error' => 'No provider message ID.',
            ];
        }

        $result = $provider->checkDeliveryStatus($message->provider_message_id);

        if ($result['success']) {
            $message->update([
                'provider_status' => $result['providerStatus'] ?? null,
                'status' => $this->mapStatus($result['providerStatus'] ?? null),
                'sent_at' => $result['sentAt'] ?? $message->sent_at,
                'delivered_at' => $result['deliveredAt'] ?? null,
                'error_message' => $result['errorMessage'] ?? null,
            ]);
        }

        return $result;
    }

    /**
     * Record a delivery status change in sms_delivery_events.
     */
    protected function recordEvent(SmsMessage $message, string $status, ?string $providerStatus, ?string $errorMessage): void
    {
        $message->deliveryEvents()->create([
            'status' => $status,
            'provider_status' => $providerStatus,
            'occurred_at' => now(),
            'error_message' => $errorMessage,
            'raw_data' => null,
        ]);
    }

    /**
     * Get delivery statistics for the dashboard.
     */
    public function getStatistics(?array $dateRange = null): array
    {
        $query = SmsMessage::query();

        if ($dateRange) {
            if (isset($dateRange['from'])) {
                $query->whereDate('created_at', '>=', $dateRange['from']);
            }
            if (isset($dateRange['to'])) {
                $query->whereDate('created_at', '<=', $dateRange['to']);
            }
        }

        $total = $query->count();
        $queued = $query->where('status', 'queued')->count();
        $sent = $query->where('status', 'sent')->count();
        $delivered = $query->where('status', 'delivered')->count();
        $failed = $query->where('status', 'failed')->count();

        return [
            'total' => $total,
            'queued' => $queued,
            'sent' => $sent,
            'delivered' => $delivered,
            'failed' => $failed,
            'deliveryRate' => $total > 0 ? round(($delivered / $total) * 100, 1) : 0,
        ];
    }

    private function mapStatus(?string $providerStatus): string
    {
        return match ($providerStatus) {
            'QUEUED' => 'queued',
            'SENT' => 'sent',
            'DELIVERED' => 'delivered',
            'FAILED' => 'failed',
            default => 'queued',
        };
    }
}
