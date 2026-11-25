<?php

namespace App\Services\Sms;

use App\Models\SmsProviderConfig;
use App\Services\Sms\Providers\DarsmsProvider;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the active SMS provider and instantiates it from encrypted config.
 *
 * Provider switching is done by activating/deactivating SmsProviderConfig records.
 * Only one provider config can be active at a time.
 */
class SmsProviderResolver
{
    /** @var array<string, class-string<SmsProviderInterface>> */
    protected array $providers = [
        'darsms' => DarsmsProvider::class,
    ];

    protected ?SmsProviderInterface $resolved = null;

    protected ?SmsProviderConfig $config = null;

    public function resolve(): ?SmsProviderInterface
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $config = SmsProviderConfig::active()->first();

        if (! $config) {
            Log::warning('No active SMS provider configured.');

            return null;
        }

        $this->config = $config;
        $this->resolved = $this->instantiate($config);

        return $this->resolved;
    }

    /**
     * Get the active provider config model (may be null).
     */
    public function getConfig(): ?SmsProviderConfig
    {
        $this->resolve();

        return $this->config;
    }

    /**
     * Get all registered provider types with their capabilities.
     */
    public function availableProviders(): array
    {
        return [
            'darsms' => [
                'name' => 'DARSMS',
                'class' => DarsmsProvider::class,
                'capabilities' => [
                    'send' => true,
                    'bulk_send' => true,
                    'delivery_status' => true,
                    'balance' => true,
                    'sender_ids' => true,
                    'purchases' => true,
                    'purchase_history' => true,
                    'payment_retry' => true,
                    'connection_test' => true,
                ],
            ],
        ];
    }

    /**
     * Register a custom provider class for a given type.
     */
    public function register(string $type, string $class): void
    {
        $this->providers[$type] = $class;
    }

    /**
     * Instantiate a provider from its config, decrypting credentials.
     *
     * API key and secret are decrypted on-the-fly and never persisted
     * in the returned object beyond the request lifecycle.
     */
    protected function instantiate(SmsProviderConfig $config): SmsProviderInterface
    {
        $type = $config->provider_type;
        $class = $this->providers[$type] ?? DarsmsProvider::class;

        $providerConfig = [
            'base_url' => $config->base_url,
            'auth_header_name' => $config->auth_header_name ?? 'X-API-Key',
            'api_key' => $config->decryptedApiKey(),
            'api_secret' => $config->decryptedApiSecret(),
            'sender_id' => $config->sender_id,
            'is_active' => $config->is_active,
            'connect_timeout' => $config->connect_timeout_seconds ?? 5,
            'timeout' => $config->timeout_seconds ?? 10,
        ];

        return new $class($providerConfig);
    }
}
