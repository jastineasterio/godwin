<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SMS provider configuration.
 *
 * Credentials are encrypted at rest using Laravel's encrypt()/decrypt().
 * Only one provider can be active at a time.
 */
class SmsProviderConfig extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $hidden = [
        'api_key_encrypted',
        'api_secret_encrypted',
        'password_encrypted',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'provider_type',
        'base_url',
        'auth_header_name',
        'api_key',
        'api_secret',
        'api_key_encrypted',
        'api_secret_encrypted',
        'username',
        'password',
        'password_encrypted',
        'sender_id',
        'is_active',
        'active_slot',
        'timeout_seconds',
        'capabilities',
        'endpoint_config',
        'last_tested_at',
        'last_test_result',
        'last_test_message',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'active_slot' => 'integer',
            'capabilities' => 'array',
            'endpoint_config' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    /**
     * Boot the model: keep active_slot in sync with is_active / soft-delete state.
     * active_slot = id when active and not soft-deleted, otherwise NULL.
     * The unique index on active_slot enforces one active provider at DB level.
     */
    protected static function booted(): void
    {
        static::saving(function (self $config) {
            $config->active_slot = $config->computeActiveSlot();
        });

        static::deleting(function (self $config) {
            if (! is_null($config->deleted_at)) {
                return;
            }
            // Soft-delete: release the active slot if this was the active provider.
            if ($config->is_active) {
                self::where('id', $config->id)
                    ->update(['is_active' => false, 'active_slot' => null]);
            }
        });

        static::restored(function (self $config) {
            $config->active_slot = $config->computeActiveSlot();
            $config->saveQuietly();
        });
    }

    /**
     * Compute the active_slot value: 1 when active and not deleted, else NULL.
     * The unique index on active_slot enforces one active provider at DB level.
     */
    protected function computeActiveSlot(): ?int
    {
        if ($this->is_active && is_null($this->deleted_at)) {
            return 1;
        }

        return null;
    }

    /**
     * Set the raw API key (encrypted at rest).
     */
    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key_encrypted'] = $value ? encrypt($value) : null;
    }

    /** Get the decrypted API key. Never return this to the frontend. */
    public function decryptedApiKey(): ?string
    {
        $value = $this->attributes['api_key_encrypted'] ?? null;

        return $value ? decrypt($value) : null;
    }

    /** Set the raw API secret (encrypted at rest). */
    public function setApiSecretAttribute(?string $value): void
    {
        $this->attributes['api_secret_encrypted'] = $value ? encrypt($value) : null;
    }

    /** Get the decrypted API secret. Never return this to the frontend. */
    public function decryptedApiSecret(): ?string
    {
        $value = $this->attributes['api_secret_encrypted'] ?? null;

        return $value ? decrypt($value) : null;
    }

    /** Set the raw password (encrypted at rest). */
    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password_encrypted'] = $value ? encrypt($value) : null;
    }

    /** Get the decrypted password. Never return this to the frontend. */
    public function decryptedPassword(): ?string
    {
        $value = $this->attributes['password_encrypted'] ?? null;

        return $value ? decrypt($value) : null;
    }

    /** Masked API key for display (shows only last 4 chars). */
    public function maskedApiKey(): ?string
    {
        $value = $this->decryptedApiKey();

        if (! $value) {
            return null;
        }

        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return str_repeat('*', strlen($value) - 4).substr($value, -4);
    }

    /** Whether credentials are configured. */
    public function hasCredentials(): bool
    {
        return (bool) $this->attributes['api_key_encrypted'];
    }

    /* ------------------------------------------------------------------ */
    /* Scopes */
    /* ------------------------------------------------------------------ */

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('provider_type', $type);
    }

    /* ------------------------------------------------------------------ */
    /* Relationships */
    /* ------------------------------------------------------------------ */

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class, 'provider_type', 'provider_type');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(SmsPurchase::class, 'provider_type', 'provider_type');
    }
}
