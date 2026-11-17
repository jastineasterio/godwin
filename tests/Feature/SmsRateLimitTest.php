<?php

namespace Tests\Feature;

use App\Services\Sms\DarsmsRateLimiter;
use App\Services\Sms\Providers\DarsmsProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * DARSMS rate limiting tests.
 *
 * Uses mocked HTTP requests and the array cache store. No live SMS is sent.
 */
class SmsRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        DarsmsRateLimiter::registerLimiters();
    }

    /* ---------------------- Rate limiter service ---------------------- */

    public function test_ordinary_limit_allows_180_requests_per_minute(): void
    {
        $rateLimiter = new DarsmsRateLimiter();
        $apiKey = 'ak_ordinary_test';

        for ($i = 0; $i < 180; $i++) {
            $this->assertTrue($rateLimiter->ordinaryAllowed($apiKey), "Request #{$i} should be allowed");
            $rateLimiter->hitOrdinary($apiKey);
        }

        $this->assertFalse($rateLimiter->ordinaryAllowed($apiKey), 'The 181st ordinary request should be rejected');
    }

    public function test_payment_limit_allows_10_requests_per_minute(): void
    {
        $rateLimiter = new DarsmsRateLimiter();
        $apiKey = 'ak_payment_test';

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($rateLimiter->paymentAllowed($apiKey), "Payment request #{$i} should be allowed");
            $rateLimiter->hitPayment($apiKey);
        }

        $this->assertFalse($rateLimiter->paymentAllowed($apiKey), 'The 11th payment request should be rejected');
    }

    public function test_payment_limit_is_stricter_than_ordinary_limit(): void
    {
        $rateLimiter = new DarsmsRateLimiter();
        $apiKey = 'ak_strict_test';

        // Payment limit exhausted after 10.
        for ($i = 0; $i < 10; $i++) {
            $rateLimiter->hitPayment($apiKey);
        }

        $this->assertFalse($rateLimiter->paymentAllowed($apiKey));

        // Ordinary limit still has headroom for the same key.
        $this->assertTrue($rateLimiter->ordinaryAllowed($apiKey));
    }

    public function test_rate_limits_are_keyed_by_api_key(): void
    {
        $rateLimiter = new DarsmsRateLimiter();

        // Exhaust key A.
        for ($i = 0; $i < 10; $i++) {
            $rateLimiter->hitPayment('ak_a');
        }

        $this->assertFalse($rateLimiter->paymentAllowed('ak_a'));
        // Key B is independent.
        $this->assertTrue($rateLimiter->paymentAllowed('ak_b'));
    }

    public function test_rate_limit_releases_after_window_with_fake_clock(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));

        $rateLimiter = new DarsmsRateLimiter();
        $apiKey = 'ak_window_test';

        for ($i = 0; $i < 10; $i++) {
            $rateLimiter->hitPayment($apiKey);
        }

        $this->assertFalse($rateLimiter->paymentAllowed($apiKey));

        // Advance past the 60s window.
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:01:05'));

        $this->assertTrue($rateLimiter->paymentAllowed($apiKey));

        Carbon::setTestNow(null);
    }

    /* ---------------------- Provider integration ---------------------- */

    public function test_provider_send_respects_shared_rate_limit_without_http_calls_when_throttled(): void
    {
        $sent = 0;

        Http::fake(function () use (&$sent) {
            $sent++;

            return Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['messageIds' => ['msg_'.$sent], 'batchId' => 'batch_1'],
            ], 202);
        });

        $apiKey = 'ak_provider_test';
        $rateLimiter = new DarsmsRateLimiter();

        // One ordinary send reaches the provider.
        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => $apiKey,
        ], $rateLimiter);

        $result = $provider->send('255712345678', 'Hello');
        $this->assertTrue($result['success']);
        $this->assertSame(1, $sent);

        // Drain the rest of the ordinary limit directly on the shared counter
        // (no HTTP calls).
        for ($i = 1; $i < 180; $i++) {
            $rateLimiter->hitOrdinary($apiKey);
        }

        // A new provider instance sharing the same API key and rate limiter
        // must be rejected locally without reaching the HTTP client.
        $throttledProvider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => $apiKey,
        ], $rateLimiter);

        $result = $throttledProvider->send('255712345678', 'Hello');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['rate_limited'] ?? false);
        $this->assertEquals(429, $result['status_code']);
        $this->assertSame(1, $sent, 'No further HTTP calls should be made once throttled');
    }

    public function test_provider_payment_endpoint_uses_stricter_limit(): void
    {
        $sent = 0;

        Http::fake(function () use (&$sent) {
            $sent++;

            return Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => ['purchaseId' => 'purch_'.$sent],
            ], 200);
        });

        $rateLimiter = new DarsmsRateLimiter();
        $apiKey = 'ak_payment_provider_test';

        // Drain payment limit without HTTP calls.
        for ($i = 0; $i < 10; $i++) {
            $rateLimiter->hitPayment($apiKey);
        }

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => $apiKey,
        ], $rateLimiter);

        $result = $provider->purchaseCredits(100, '255712345678');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['rate_limited'] ?? false);
        $this->assertEquals(0, $sent, 'Payment requests must not reach HTTP once payment limit is exhausted');
    }

    public function test_payment_consumes_both_rate_limits(): void
    {
        $sent = 0;

        Http::fake(function () use (&$sent) {
            $sent++;

            return Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['messageIds' => ['msg_'.$sent], 'batchId' => 'batch_1'],
            ], 202);
        });

        $rateLimiter = new DarsmsRateLimiter();
        $apiKey = 'ak_both_limits_test';

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => $apiKey,
        ], $rateLimiter);

        // 170 ordinary sends plus 10 payments = 180 requests against the
        // overall allowance, while the payment allowance is fully consumed.
        for ($i = 0; $i < 170; $i++) {
            $this->assertTrue(
                $provider->send('255712345678', 'Hello')['success'],
                "Ordinary send #{$i} should be allowed"
            );
        }

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue(
                $provider->purchaseCredits(100, '255712345678')['success'],
                "Payment request #{$i} should be allowed"
            );
        }

        $this->assertSame(180, $sent, 'Every request including payments must reach the provider before throttling');

        // Overall allowance exhausted by the combined traffic: ordinary send rejected.
        $result = $provider->send('255712345678', 'Hello');
        $this->assertFalse($result['success']);
        $this->assertTrue($result['rate_limited'] ?? false);
        $this->assertSame(429, $result['raw']['status_code'] ?? null);
        $this->assertSame(180, $sent, 'Throttled ordinary request must not reach DARSMS');

        // Payment allowance exhausted too: payment rejected by the overall limit.
        $result = $provider->purchaseCredits(100, '255712345678');
        $this->assertFalse($result['success']);
        $this->assertTrue($result['rate_limited'] ?? false);
        $this->assertSame(180, $result['raw']['limit'] ?? null);
        $this->assertSame(180, $sent, 'Throttled payment request must not reach DARSMS');
    }

    public function test_throttled_payment_does_not_reach_provider(): void
    {
        $sent = 0;

        Http::fake(function () use (&$sent) {
            $sent++;

            return Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => ['purchaseId' => 'purch_'.$sent],
            ], 200);
        });

        $rateLimiter = new DarsmsRateLimiter();
        $apiKey = 'ak_throttled_payment_test';

        // Exhaust the payment limit directly on the shared counter.
        for ($i = 0; $i < 10; $i++) {
            $rateLimiter->hitPayment($apiKey);
        }

        // Ordinary allowance still has headroom, but payments are stricter.
        $this->assertTrue($rateLimiter->ordinaryAllowed($apiKey));
        $this->assertFalse($rateLimiter->paymentAllowed($apiKey));

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => $apiKey,
        ], $rateLimiter);

        $result = $provider->purchaseCredits(100, '255712345678');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['rate_limited'] ?? false);
        $this->assertSame(429, $result['raw']['status_code'] ?? null);
        $this->assertSame(10, $result['raw']['limit'] ?? null);
        $this->assertSame(0, $sent, 'Throttled payment must not reach DARSMS');
    }

    public function test_overall_limit_blocks_payments(): void
    {
        $sent = 0;

        Http::fake(function () use (&$sent) {
            $sent++;

            return Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['messageIds' => ['msg_'.$sent], 'batchId' => 'batch_1'],
            ], 202);
        });

        $rateLimiter = new DarsmsRateLimiter();
        $apiKey = 'ak_overall_blocks_payment';

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => $apiKey,
        ], $rateLimiter);

        // Exhaust the overall allowance with ordinary sends only.
        for ($i = 0; $i < 180; $i++) {
            $this->assertTrue($provider->send('255712345678', 'Hello')['success']);
        }

        // Payment requests are blocked by the overall limit even though the
        // payment counter itself was never touched.
        $result = $provider->purchaseCredits(100, '255712345678');
        $this->assertFalse($result['success']);
        $this->assertTrue($result['rate_limited'] ?? false);
        $this->assertSame(180, $result['raw']['limit'] ?? null);
        $this->assertSame(180, $sent, 'Throttled payment must not reach DARSMS');
    }

    public function test_rate_limited_result_propagates_through_parse_send_response(): void
    {
        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => 'ak_propagate_test',
        ]);

        $method = new \ReflectionMethod($provider, 'parseSendResponse');
        $method->setAccessible(true);

        $result = $method->invoke($provider, [
            'success' => false,
            'message' => 'Rate limit exceeded.',
            'status_code' => 429,
            'rate_limited' => true,
            'retry_after' => 45,
        ], '255712345678');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['rate_limited']);
        $this->assertEquals(429, $result['status_code']);
        $this->assertEquals(45, $result['retry_after']);
        $this->assertNull($result['messageId']);
    }

    public function test_no_api_key_means_no_rate_limiting(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'message' => 'OK', 'data' => ['balance' => 5]], 200),
        ]);

        $provider = new DarsmsProvider([
            'base_url' => 'https://test.darsms.local/api/v1',
            'api_key' => null,
        ]);

        $result = $provider->checkBalance();

        $this->assertTrue($result['success']);
    }
}
