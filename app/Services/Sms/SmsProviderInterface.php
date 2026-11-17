<?php

namespace App\Services\Sms;

/**
 * Provider-independent SMS interface.
 *
 * Every SMS provider (DARSMS, Twilio, Africa's Talking, etc.) implements
 * this contract. Capability methods tell the caller which operations a
 * provider supports so unsupported features are disabled gracefully.
 */
interface SmsProviderInterface
{
    /** Human-readable provider name (e.g. "DARSMS"). */
    public function name(): string;

    /** Provider type slug (e.g. "darsms", "twilio"). */
    public function type(): string;

    /** Whether this provider is the currently active one. */
    public function isActive(): bool;

    /** Configured base URL for the provider API. */
    public function baseUrl(): ?string;

    /** Capability flags. */
    public function supportsSend(): bool;
    public function supportsBulkSend(): bool;
    public function supportsDeliveryStatus(): bool;
    public function supportsBalance(): bool;
    public function supportsSenderIds(): bool;
    public function supportsPurchases(): bool;
    public function supportsPurchaseHistory(): bool;
    public function supportsPaymentRetry(): bool;
    public function supportsConnectionTest(): bool;

    /* ------------------------------------------------------------------ */
    /* Core operations                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Send a single SMS.
     *
     * @return array{success: bool, messageId?: string, batchId?: string, providerStatus?: string, error?: string, raw?: array}
     */
    public function send(string $to, string $message, ?string $senderId = null): array;

    /**
     * Send bulk SMS to multiple recipients.
     *
     * @param  list<string>  $to
     * @return array{success: bool, messageId?: string, batchId?: string, queued?: int, providerStatus?: string, error?: string, raw?: array}
     */
    public function sendBulk(array $to, string $message, ?string $senderId = null): array;

    /** Check delivery status of a single message. */
    public function checkDeliveryStatus(string $messageId): array;

    /** Check available SMS balance/credits. */
    public function checkBalance(): array;

    /** Retrieve approved sender IDs. */
    public function getSenderIds(): array;

    /** Initiate an SMS credit purchase. */
    public function purchaseCredits(int $quantity, string $payerPhone): array;

    /** Retrieve purchase history with pagination. */
    public function getPurchaseHistory(?string $status = null, int $page = 1, int $limit = 20): array;

    /** Retry payment for an eligible purchase. */
    public function retryPayment(string $purchaseId, ?string $phone = null): array;

    /** Test provider connection using a safe, read-only endpoint. */
    public function testConnection(): array;
}
