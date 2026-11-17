<?php

namespace App\Services\Sms;

use App\Models\SmsPurchase;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * SMS credit purchase service (Admin only).
 *
 * Handles purchase initiation, history, and retry for DARSMS SMS credits.
 * Credits are never manually added — only the provider can confirm a
 * successful purchase and add credits.
 */
class SmsCreditService
{
    protected SmsProviderResolver $resolver;

    public function __construct(SmsProviderResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Check if the given user is authorized to purchase credits (Admin only).
     */
    public function canPurchase(User $user): bool
    {
        return $user->hasAnyRole([\App\Enums\UserRole::Admin]);
    }

    /**
     * Initiate an SMS credit purchase.
     *
     * Never manually increases the balance — the provider adds credits
     * after payment confirmation.
     */
    public function purchase(int $quantity, string $payerPhone, User $user): array
    {
        if (! $this->canPurchase($user)) {
            return [
                'success' => false,
                'error' => 'Unauthorized: only administrators can purchase SMS credits.',
                'code' => 'unauthorized',
            ];
        }

        $provider = $this->resolver->resolve();

        if (! $provider || ! $provider->supportsPurchases()) {
            return [
                'success' => false,
                'error' => 'Credit purchases not supported by this provider.',
                'code' => 'unsupported_operation',
            ];
        }

        $result = $provider->purchaseCredits($quantity, $payerPhone);

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Purchase failed.',
                'code' => 'provider_error',
                'raw' => $result['raw'] ?? null,
            ];
        }

        $purchase = SmsPurchase::create([
            'user_id' => $user->id,
            'provider_type' => $provider->type(),
            'provider_name' => $provider->name(),
            'provider_purchase_id' => $result['purchaseId'] ?? null,
            'transaction_ref' => $result['transactionRef'] ?? null,
            'quantity_sms' => $result['quantitySms'] ?? $quantity,
            'amount' => $result['amount'] ?? null,
            'unit_price' => $result['unitPrice'] ?? null,
            'provider_status' => $result['providerStatus'] ?? 'PENDING',
            'expires_at' => $result['expiresAt'] ?? null,
            'efd_receipt' => $result['efdReceipt'] ?? null,
            'payer_phone' => $payerPhone,
            'raw_response' => $result['raw'] ?? null,
        ]);

        return [
            'success' => true,
            'purchase_id' => $purchase->id,
            'provider_purchase_id' => $result['purchaseId'] ?? null,
            'transaction_ref' => $result['transactionRef'] ?? null,
            'amount' => $result['amount'] ?? null,
            'unit_price' => $result['unitPrice'] ?? null,
            'quantity_sms' => $result['quantitySms'] ?? $quantity,
            'provider_status' => $result['providerStatus'] ?? 'PENDING',
            'expires_at' => $result['expiresAt'] ?? null,
            'efd_receipt' => $result['efdReceipt'] ?? null,
            'message' => 'Purchase initiated. Credits will be added after payment confirmation.',
        ];
    }

    /**
     * Get purchase history with optional status filter and pagination.
     */
    public function getHistory(?string $status = null, int $page = 1, int $limit = 20, ?User $user = null): array
    {
        $provider = $this->resolver->resolve();

        if (! $provider || ! $provider->supportsPurchaseHistory()) {
            // Fall back to local database records.
            return $this->getLocalHistory($status, $page, $limit, $user);
        }

        $result = $provider->getPurchaseHistory($status, $page, $limit);

        if (! $result['success']) {
            return $this->getLocalHistory($status, $page, $limit, $user);
        }

        $purchases = array_map(function ($p) {
            return [
                'provider_purchase_id' => $p['purchaseId'] ?? null,
                'transaction_ref' => $p['transactionRef'] ?? null,
                'quantity_sms' => $p['quantitySms'] ?? null,
                'amount' => $p['amount'] ?? null,
                'unit_price' => $p['unitPrice'] ?? null,
                'provider_status' => $p['providerStatus'] ?? null,
                'expires_at' => $p['expiresAt'] ?? null,
                'efd_receipt' => $p['efdReceipt'] ?? null,
            ];
        }, $result['purchases']);

        return [
            'success' => true,
            'purchases' => $purchases,
            'pagination' => $result['pagination'] ?? [
                'page' => $page,
                'limit' => $limit,
                'total' => count($purchases),
            ],
            'source' => 'provider',
        ];
    }

    /**
     * Retry payment for an eligible purchase.
     */
    public function retry(string $providerPurchaseId, User $user, ?string $phone = null): array
    {
        if (! $this->canPurchase($user)) {
            return [
                'success' => false,
                'error' => 'Unauthorized.',
                'code' => 'unauthorized',
            ];
        }

        $provider = $this->resolver->resolve();

        if (! $provider || ! $provider->supportsPaymentRetry()) {
            return [
                'success' => false,
                'error' => 'Payment retry not supported by this provider.',
                'code' => 'unsupported_operation',
            ];
        }

        $result = $provider->retryPayment($providerPurchaseId, $phone);

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Retry failed.',
                'code' => 'provider_error',
            ];
        }

        // Update local record.
        $purchase = SmsPurchase::where('provider_purchase_id', $providerPurchaseId)->first();

        if ($purchase) {
            $purchase->update([
                'provider_status' => $result['providerStatus'] ?? $purchase->provider_status,
                'transaction_ref' => $result['transactionRef'] ?? $purchase->transaction_ref,
                'raw_response' => array_merge(
                    (array) $purchase->raw_response,
                    ['retry' => $result['raw'] ?? null]
                ),
            ]);
        }

        return [
            'success' => true,
            'purchase_id' => $result['purchaseId'] ?? $providerPurchaseId,
            'transaction_ref' => $result['transactionRef'] ?? null,
            'provider_status' => $result['providerStatus'] ?? null,
        ];
    }

    /**
     * Get current SMS balance/credits.
     */
    public function getBalance(): array
    {
        $provider = $this->resolver->resolve();

        if (! $provider || ! $provider->supportsBalance()) {
            return [
                'success' => false,
                'error' => 'Balance checking not supported.',
                'code' => 'unsupported_operation',
            ];
        }

        return $provider->checkBalance();
    }

    /**
     * Fallback: get purchase history from local database records.
     */
    protected function getLocalHistory(?string $status, int $page, int $limit, ?User $user): array
    {
        $query = SmsPurchase::query();

        if ($status && $status !== 'ALL') {
            $query->where('provider_status', $status);
        }

        if ($user && ! $user->hasAnyRole([\App\Enums\UserRole::Admin])) {
            $query->where('user_id', $user->id);
        }

        $total = $query->count();
        $purchases = $query->orderByDesc('created_at')
            ->forPage($page, $limit)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'provider_purchase_id' => $p->provider_purchase_id,
                'transaction_ref' => $p->transaction_ref,
                'quantity_sms' => $p->quantity_sms,
                'amount' => $p->amount,
                'unit_price' => $p->unit_price,
                'provider_status' => $p->provider_status,
                'expires_at' => $p->expires_at,
                'efd_receipt' => $p->efd_receipt,
                'payer_phone' => $p->payer_phone,
                'created_at' => $p->created_at,
                'user' => $p->user?->name,
            ])->toArray();

        return [
            'success' => true,
            'purchases' => $purchases,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
            'source' => 'local',
        ];
    }
}
