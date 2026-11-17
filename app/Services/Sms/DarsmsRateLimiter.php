<?php

namespace App\Services\Sms;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Shared DARSMS API rate limiter.
 *
 * Limits are keyed by the API key (hashed, never the raw key) so that every
 * web request and queue worker using the same DARSMS API key shares a single
 * counter. The counters live in the application's default cache store, which
 * in this project is the database cache store (see CACHE_STORE=database) and
 * is therefore shared across queue workers and web requests.
 *
 * Limits:
 *   - 180 requests per minute for ordinary DARSMS API requests.
 *   -  10 requests per minute for payment operations (purchases / payment retry).
 *
 * Because the production cache store is the database (not Redis), the
 * check-then-hit sequence is not a single atomic operation across processes.
 * This is the safest implementation available without a distributed cache
 * backend; see the final report for the limitation.
 */
class DarsmsRateLimiter
{
    public const ORDINARY_LIMIT_PER_MINUTE = 180;

    public const PAYMENT_LIMIT_PER_MINUTE = 10;

    public const WINDOW_SECONDS = 60;

    public function ordinaryKey(string $apiKey): string
    {
        return 'darsms:rate:ordinary:'.hash('sha256', $apiKey);
    }

    public function paymentKey(string $apiKey): string
    {
        return 'darsms:rate:payment:'.hash('sha256', $apiKey);
    }

    /** Determine whether another ordinary request is allowed right now. */
    public function ordinaryAllowed(string $apiKey): bool
    {
        return ! $this->tooMany($this->ordinaryKey($apiKey), self::ORDINARY_LIMIT_PER_MINUTE);
    }

    /** Determine whether another payment request is allowed right now. */
    public function paymentAllowed(string $apiKey): bool
    {
        return ! $this->tooMany($this->paymentKey($apiKey), self::PAYMENT_LIMIT_PER_MINUTE);
    }

    /** Record one ordinary request hit against the shared counter. */
    public function hitOrdinary(string $apiKey): int
    {
        return $this->hit($this->ordinaryKey($apiKey));
    }

    /** Record one payment request hit against the shared counter. */
    public function hitPayment(string $apiKey): int
    {
        return $this->hit($this->paymentKey($apiKey));
    }

    public function remaining(string $key, int $limit): int
    {
        return RateLimiter::remaining($key, $limit);
    }

    public function availableIn(string $key): int
    {
        return (int) RateLimiter::availableIn($key);
    }

    public function clear(string $key): void
    {
        RateLimiter::clear($key);
    }

    /**
     * Register the standard DARSMS limiters so they can be referenced by name
     * elsewhere in the application (e.g. middleware).
     */
    public static function registerLimiters(): void
    {
        RateLimiter::for('darsms:ordinary', function () {
            return Limit::perMinute(self::ORDINARY_LIMIT_PER_MINUTE);
        });

        RateLimiter::for('darsms:payment', function () {
            return Limit::perMinute(self::PAYMENT_LIMIT_PER_MINUTE);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Internals */
    /* ------------------------------------------------------------------ */

    protected function tooMany(string $key, int $maxAttempts): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    protected function hit(string $key): int
    {
        return RateLimiter::hit($key, self::WINDOW_SECONDS);
    }
}
