<?php

namespace Tests\Feature;

use App\Models\SmsProviderConfig;
use App\Services\Sms\SmsMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SMS provider balance caching.
 *
 * Verifies the balance endpoint is not called on every dashboard render:
 * successful results are cached, failures are retried.
 */
class SmsBalanceCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_balance_is_cached_and_not_refetched(): void
    {
        SmsProviderConfig::create([
            'name' => 'Cached DARSMS',
            'provider_type' => 'darsms',
            'base_url' => 'https://dev.darsms.co.tz/api/v1',
            'auth_header_name' => 'X-API-Key',
            'api_key' => 'cached-key',
            'sender_id' => 'DARSMS',
            'is_active' => true,
        ]);

        Http::fake([
            '*' => Http::response(['success' => true, 'message' => 'OK', 'data' => ['balance' => 42]], 200),
        ]);

        $service = app(SmsMessageService::class);

        $first = $service->checkBalance();
        $second = $service->checkBalance();

        $this->assertTrue($first['success']);
        $this->assertSame(42, $first['balance']);
        $this->assertSame(42, $second['balance']);

        // The provider endpoint must be hit exactly once across both calls.
        Http::assertSentCount(1);
    }

    public function test_failed_balance_is_not_cached_and_is_retried(): void
    {
        SmsProviderConfig::create([
            'name' => 'Uncached DARSMS',
            'provider_type' => 'darsms',
            'base_url' => 'https://dev.darsms.co.tz/api/v1',
            'auth_header_name' => 'X-API-Key',
            'api_key' => 'uncached-key',
            'sender_id' => 'DARSMS',
            'is_active' => true,
        ]);

        Http::fake([
            '*' => Http::response(['success' => false, 'message' => 'Provider unavailable.'], 502),
        ]);

        $service = app(SmsMessageService::class);

        $first = $service->checkBalance();
        $second = $service->checkBalance();

        $this->assertFalse($first['success']);
        $this->assertFalse($second['success']);

        // Failures are never cached: every call retries the provider.
        Http::assertSentCount(2);
    }
}
