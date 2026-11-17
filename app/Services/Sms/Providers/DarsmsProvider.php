<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\DarsmsRateLimiter;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DARSMS SMS provider implementation.
 *
 * Documented API base URL (configurable): https://dev.darsms.co.tz/api/v1
 *
 * Endpoints used:
 *   POST /integrations/sms/send          - Send / bulk SMS
 *   GET  /integrations/sender-ids         - Retrieve approved sender IDs
 *   GET  /integrations/balance            - Check SMS balance
 *   GET  /integrations/sms/status/{id}    - Check message delivery status
 *   POST /integrations/purchases          - Purchase SMS credits
 *   GET  /integrations/purchases          - Purchase history
 *   POST /integrations/purchases/{id}/pay - Retry payment
 *
 * Authentication: X-API-Key header (API key required, API secret optional).
 * Response envelope: { success, message, data, details? }
 */
class DarsmsProvider implements SmsProviderInterface
{
    /** Default development base URL — overridable via config. */
    public const DEFAULT_BASE_URL = 'https://dev.darsms.co.tz/api/v1';

    /** Rate limits: 180 req/min per API key, 10 req/min for payment endpoints. */
    public const RATE_LIMIT_PER_MINUTE = 180;

    public const PAYMENT_RATE_LIMIT_PER_MINUTE = 10;

    /** Provider statuses returned by DARSMS. */
    public const STATUS_QUEUED = 'QUEUED';

    public const STATUS_SENT = 'SENT';

    public const STATUS_DELIVERED = 'DELIVERED';

    public const STATUS_FAILED = 'FAILED';

    /** Purchase statuses. */
    public const PURCHASE_PENDING = 'PENDING';

    public const PURCHASE_SUCCESS = 'SUCCESS';

    public const PURCHASE_FAILED = 'FAILED';

    public const PURCHASE_CANCELLED = 'CANCELLED';

    /** @var array<string, mixed> */
    protected array $config;

    /** @var Factory|mixed */
    protected $http;

    protected ?DarsmsRateLimiter $rateLimiter = null;

    /**
     * @param  array<string, mixed>  $config  Provider configuration (base_url, api_key, api_secret, sender_id, etc.)
     * @param  DarsmsRateLimiter|null  $rateLimiter  Shared rate limiter (resolved from the container when omitted).
     */
    public function __construct(array $config = [], ?DarsmsRateLimiter $rateLimiter = null)
    {
        $this->config = array_merge([
            'base_url' => self::DEFAULT_BASE_URL,
            'api_key' => null,
            'api_secret' => null,
            'sender_id' => null,
            'timeout' => 30,
        ], $config);

        $this->http = Http::timeout((int) $this->config['timeout']);
        $this->rateLimiter = $rateLimiter ?? app(DarsmsRateLimiter::class);
    }

    /* ------------------------------------------------------------------ */
    /* Identity */
    /* ------------------------------------------------------------------ */

    public function name(): string
    {
        return 'DARSMS';
    }

    public function type(): string
    {
        return 'darsms';
    }

    public function isActive(): bool
    {
        return (bool) ($this->config['is_active'] ?? false);
    }

    public function baseUrl(): ?string
    {
        return $this->config['base_url'] ?? null;
    }

    /* ------------------------------------------------------------------ */
    /* Capabilities */
    /* ------------------------------------------------------------------ */

    public function supportsSend(): bool
    {
        return true;
    }

    public function supportsBulkSend(): bool
    {
        return true;
    }

    public function supportsDeliveryStatus(): bool
    {
        return true;
    }

    public function supportsBalance(): bool
    {
        return true;
    }

    public function supportsSenderIds(): bool
    {
        return true;
    }

    public function supportsPurchases(): bool
    {
        return true;
    }

    public function supportsPurchaseHistory(): bool
    {
        return true;
    }

    public function supportsPaymentRetry(): bool
    {
        return true;
    }

    public function supportsConnectionTest(): bool
    {
        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Operations */
    /* ------------------------------------------------------------------ */

    /**
     * Send a single SMS via DARSMS.
     */
    public function send(string $to, string $message, ?string $senderId = null): array
    {
        $result = $this->post('/integrations/sms/send', [
            'senderId' => $senderId ?? $this->config['sender_id'] ?? 'DARSMS',
            'to' => [$to],
            'message' => $message,
        ]);

        return $this->parseSendResponse($result, $to);
    }

    /**
     * Send bulk SMS via DARSMS.
     */
    public function sendBulk(array $to, string $message, ?string $senderId = null): array
    {
        // Deduplicate recipients before submission to avoid duplicate SMS.
        $to = array_values(array_unique($to));

        $result = $this->post('/integrations/sms/send', [
            'senderId' => $senderId ?? $this->config['sender_id'] ?? 'DARSMS',
            'to' => $to,
            'message' => $message,
        ]);

        return $this->parseSendResponse($result, implode(',', $to));
    }

    /**
     * Check delivery status of a message.
     */
    public function checkDeliveryStatus(string $messageId): array
    {
        $result = $this->get("/integrations/sms/status/{$messageId}");

        if (! $result['success'] ?? false) {
            return [
                'success' => false,
                'messageId' => $messageId,
                'providerStatus' => null,
                'error' => $result['message'] ?? 'Unknown error',
                'raw' => $result,
            ];
        }

        $data = $result['data'] ?? [];

        return [
            'success' => true,
            'messageId' => $data['messageId'] ?? $messageId,
            'providerStatus' => $data['status'] ?? null,
            'sentAt' => $data['sentAt'] ?? null,
            'deliveredAt' => $data['deliveredAt'] ?? null,
            'errorMessage' => $data['errorMessage'] ?? null,
            'raw' => $data,
        ];
    }

    /**
     * Check available SMS balance.
     */
    public function checkBalance(): array
    {
        $result = $this->get('/integrations/balance');

        if (! $result['success'] ?? false) {
            return [
                'success' => false,
                'balance' => null,
                'error' => $result['message'] ?? 'Unknown error',
                'rate_limited' => $result['rate_limited'] ?? false,
                'retry_after' => $result['retry_after'] ?? null,
                'raw' => $result,
            ];
        }

        $data = $result['data'] ?? [];

        return [
            'success' => true,
            'balance' => $data['balance'] ?? $data['available'] ?? $data['credits'] ?? null,
            'currency' => $data['currency'] ?? null,
            'rate_limited' => false,
            'ambiguous' => false,
            'raw' => $data,
        ];
    }

    /**
     * Retrieve approved sender IDs.
     */
    public function getSenderIds(): array
    {
        $result = $this->get('/integrations/sender-ids');

        if (! $result['success'] ?? false) {
            return [
                'success' => false,
                'senderIds' => [],
                'error' => $result['message'] ?? 'Unknown error',
                'rate_limited' => $result['rate_limited'] ?? false,
                'retry_after' => $result['retry_after'] ?? null,
                'raw' => $result,
            ];
        }

        $data = $result['data'] ?? [];
        $senderIds = is_array($data) ? $data : (isset($data['sender_ids']) ? $data['sender_ids'] : []);

        return [
            'success' => true,
            'senderIds' => is_array($senderIds) ? array_values($senderIds) : [],
            'rate_limited' => false,
            'ambiguous' => false,
            'raw' => $data,
        ];
    }

    /**
     * Purchase SMS credits.
     */
    public function purchaseCredits(int $quantity, string $payerPhone): array
    {
        $result = $this->post('/integrations/purchases', [
            'quantitySms' => $quantity,
            'payerPhone' => $payerPhone,
        ]);

        if (! $result['success'] ?? false) {
            return [
                'success' => false,
                'error' => $result['message'] ?? 'Unknown error',
                'rate_limited' => $result['rate_limited'] ?? false,
                'retry_after' => $result['retry_after'] ?? null,
                'raw' => $result,
            ];
        }

        $data = $result['data'] ?? [];

        return [
            'success' => true,
            'purchaseId' => $data['purchaseId'] ?? null,
            'transactionRef' => $data['transactionRef'] ?? null,
            'amount' => $data['amount'] ?? null,
            'unitPrice' => $data['unitPrice'] ?? null,
            'quantitySms' => $data['quantitySms'] ?? $quantity,
            'providerStatus' => $data['providerStatus'] ?? self::PURCHASE_PENDING,
            'expiresAt' => $data['expiresAt'] ?? null,
            'efdReceipt' => $data['efdReceipt'] ?? null,
            'rate_limited' => false,
            'ambiguous' => false,
            'raw' => $data,
        ];
    }

    /**
     * Retrieve purchase history with pagination.
     */
    public function getPurchaseHistory(?string $status = null, int $page = 1, int $limit = 20): array
    {
        $query = [
            'status' => $status ?? 'ALL',
            'page' => max(1, $page),
            'limit' => max(1, min(100, $limit)),
        ];

        $result = $this->get('/integrations/purchases', $query);

        if (! $result['success'] ?? false) {
            return [
                'success' => false,
                'purchases' => [],
                'pagination' => [],
                'error' => $result['message'] ?? 'Unknown error',
                'rate_limited' => $result['rate_limited'] ?? false,
                'retry_after' => $result['retry_after'] ?? null,
                'raw' => $result,
            ];
        }

        $data = $result['data'] ?? [];
        $purchases = is_array($data) ? $data : (isset($data['purchases']) ? $data['purchases'] : []);

        return [
            'success' => true,
            'purchases' => is_array($purchases) ? array_values($purchases) : [],
            'pagination' => isset($data['pagination']) ? $data['pagination'] : [
                'page' => $page,
                'limit' => $limit,
                'total' => count($purchases),
            ],
            'rate_limited' => false,
            'ambiguous' => false,
            'raw' => $data,
        ];
    }

    /**
     * Retry payment for an eligible purchase.
     */
    public function retryPayment(string $purchaseId, ?string $phone = null): array
    {
        $body = $phone ? ['phone' => $phone] : [];

        $result = $this->post("/integrations/purchases/{$purchaseId}/pay", $body);

        if (! $result['success'] ?? false) {
            return [
                'success' => false,
                'purchaseId' => $purchaseId,
                'error' => $result['message'] ?? 'Unknown error',
                'rate_limited' => $result['rate_limited'] ?? false,
                'retry_after' => $result['retry_after'] ?? null,
                'raw' => $result,
            ];
        }

        $data = $result['data'] ?? [];

        return [
            'success' => true,
            'purchaseId' => $data['purchaseId'] ?? $purchaseId,
            'transactionRef' => $data['transactionRef'] ?? null,
            'providerStatus' => $data['providerStatus'] ?? null,
            'rate_limited' => false,
            'ambiguous' => false,
            'raw' => $data,
        ];
    }

    /**
     * Test provider connection using the balance endpoint (safe, read-only).
     */
    public function testConnection(): array
    {
        if (! $this->config['api_key']) {
            return [
                'success' => false,
                'connected' => false,
                'reason' => 'configuration_incomplete',
                'message' => 'API key is not configured.',
            ];
        }

        try {
            $result = $this->get('/integrations/balance');

            if ($result['success'] ?? false) {
                return [
                    'success' => true,
                    'connected' => true,
                    'reason' => 'success',
                    'message' => 'Connected successfully.',
                    'balance' => $result['data']['balance'] ?? null,
                ];
            }

            $message = $result['message'] ?? '';
            $statusCode = $result['status_code'] ?? 0;

            return match (true) {
                $statusCode === 401 || str_contains($message, 'API key') => [
                    'success' => false,
                    'connected' => false,
                    'reason' => 'invalid_api_key',
                    'message' => 'Invalid API key.',
                ],
                $statusCode === 403 || str_contains($message, 'inactive') => [
                    'success' => false,
                    'connected' => false,
                    'reason' => 'account_inactive',
                    'message' => 'Account inactive.',
                ],
                $statusCode === 429 => [
                    'success' => false,
                    'connected' => false,
                    'reason' => 'rate_limited',
                    'message' => 'Rate limit exceeded.',
                ],
                default => [
                    'success' => false,
                    'connected' => false,
                    'reason' => 'provider_error',
                    'message' => $message ?: 'Provider error.',
                ],
            };
        } catch (ConnectionException $e) {
            Log::warning('DARSMS connection test failed: '.$e->getMessage());

            return [
                'success' => false,
                'connected' => false,
                'reason' => 'provider_unavailable',
                'message' => 'Provider unavailable.',
                'ambiguous' => true,
            ];
        } catch (\Throwable $e) {
            Log::warning('DARSMS connection test failed: '.$e->getMessage());

            return [
                'success' => false,
                'connected' => false,
                'reason' => 'error',
                'message' => 'Connection test failed.',
                'ambiguous' => true,
            ];
        }
    }

    /**
     * Enforce the shared per-API-key rate limit before an outbound request.
     *
     * Payment endpoints use the stricter payment limit. Returns a normalized
     * rate-limited result array when throttled, or null when the request may
     * proceed. The caller records the hit via recordRateLimitHit() once the
     * request is actually sent.
     */
    protected function enforceRateLimit(string $path): ?array
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (! $apiKey) {
            return null;
        }

        $isPayment = $this->isPaymentEndpoint($path);

        // Every request, including payment requests, counts against the
        // overall 180 requests/minute allowance.
        if (! $this->rateLimiter->ordinaryAllowed($apiKey)) {
            return $this->rateLimitedResponse(
                $this->rateLimiter->ordinaryKey($apiKey),
                self::RATE_LIMIT_PER_MINUTE
            );
        }

        // Payment endpoints additionally enforce the stricter 10/minute limit.
        if ($isPayment && ! $this->rateLimiter->paymentAllowed($apiKey)) {
            return $this->rateLimitedResponse(
                $this->rateLimiter->paymentKey($apiKey),
                self::PAYMENT_RATE_LIMIT_PER_MINUTE
            );
        }

        return null;
    }

    /** Record the hit for a request that passed the rate limit check. */
    protected function recordRateLimitHit(string $path): void
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (! $apiKey) {
            return;
        }

        // Every request consumes the overall allowance...
        $this->rateLimiter->hitOrdinary($apiKey);

        // ...and payments additionally consume the stricter payment allowance.
        if ($this->isPaymentEndpoint($path)) {
            $this->rateLimiter->hitPayment($apiKey);
        }
    }

    protected function isPaymentEndpoint(string $path): bool
    {
        return str_starts_with($path, '/integrations/purchases');
    }

    protected function rateLimitedResponse(string $key, int $limit): array
    {
        $retryAfter = max(1, $this->rateLimiter->availableIn($key));

        return [
            'success' => false,
            'message' => 'Rate limit exceeded. Try again in '.$retryAfter.'s.',
            'status_code' => 429,
            'rate_limited' => true,
            'retry_after' => $retryAfter,
            'limit' => $limit,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Internal HTTP helpers */
    /* ------------------------------------------------------------------ */

    /**
     * GET request to the provider API.
     *
     * Never logs the API key or full response body containing credentials.
     */
    protected function get(string $path, array $query = []): array
    {
        $rateLimited = $this->enforceRateLimit($path);

        if ($rateLimited) {
            return $rateLimited;
        }

        $this->recordRateLimitHit($path);

        try {
            $response = $this->http
                ->withHeaders([
                    'X-API-Key' => $this->config['api_key'],
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl().$path, $query);

            return $this->parseResponse($response);
        } catch (ConnectionException $e) {
            Log::warning('DARSMS GET connection error: '.$e->getMessage(), ['path' => $path]);

            return [
                'success' => false,
                'message' => 'Provider unavailable.',
                'status_code' => 0,
                'ambiguous' => true,
            ];
        } catch (\Throwable $e) {
            Log::warning('DARSMS GET error: '.$e->getMessage(), ['path' => $path]);

            return [
                'success' => false,
                'message' => 'Request failed.',
                'status_code' => 0,
            ];
        }
    }

    /**
     * POST request to the provider API.
     */
    protected function post(string $path, array $body = []): array
    {
        $rateLimited = $this->enforceRateLimit($path);

        if ($rateLimited) {
            return $rateLimited;
        }

        $this->recordRateLimitHit($path);

        try {
            $response = $this->http
                ->withHeaders([
                    'X-API-Key' => $this->config['api_key'],
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl().$path, $body);

            return $this->parseResponse($response);
        } catch (ConnectionException $e) {
            Log::warning('DARSMS POST connection error: '.$e->getMessage(), ['path' => $path]);

            return [
                'success' => false,
                'message' => 'Provider unavailable.',
                'status_code' => 0,
                'ambiguous' => true,
            ];
        } catch (\Throwable $e) {
            Log::warning('DARSMS POST error: '.$e->getMessage(), ['path' => $path]);

            return [
                'success' => false,
                'message' => 'Request failed.',
                'status_code' => 0,
            ];
        }
    }

    /**
     * Parse an HTTP client response into a normalized array.
     *
     * Never logs the API key. Only logs safe status codes and error classes.
     */
    protected function parseResponse(Response $response): array
    {
        $status = $response->status();
        $body = $response->json() ?? [];
        $success = $response->successful() && ($body['success'] ?? false);

        return [
            'success' => $success,
            'message' => $body['message'] ?? '',
            'data' => $body['data'] ?? null,
            'details' => $body['details'] ?? null,
            'status_code' => $status,
        ];
    }

    /**
     * Parse a send response into a normalized result.
     *
     * HTTP 202 means accepted/queued by the provider — NOT delivered.
     */
    protected function parseSendResponse(array $result, string $recipients): array
    {
        if (! $result['success'] ?? false) {
            return [
                'success' => false,
                'recipients' => $recipients,
                'messageId' => null,
                'batchId' => null,
                'providerStatus' => null,
                'error' => $result['message'] ?? 'Send failed.',
                'status_code' => $result['status_code'] ?? null,
                'rate_limited' => $result['rate_limited'] ?? false,
                'ambiguous' => $result['ambiguous'] ?? false,
                'retry_after' => $result['retry_after'] ?? null,
                'raw' => $result,
            ];
        }

        $data = $result['data'] ?? [];

        return [
            'success' => true,
            'recipients' => $recipients,
            'messageId' => $data['messageIds'][0] ?? $data['messageId'] ?? null,
            'messageIds' => is_array($data['messageIds'] ?? []) ? $data['messageIds'] : [],
            'batchId' => $data['batchId'] ?? null,
            'queued' => $data['queued'] ?? 0,
            'duplicatesRemoved' => $data['duplicatesRemoved'] ?? 0,
            'senderId' => $data['senderId'] ?? null,
            'providerStatus' => self::STATUS_QUEUED,
            'error' => null,
            'rate_limited' => false,
            'ambiguous' => false,
            'raw' => $data,
        ];
    }
}
