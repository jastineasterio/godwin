<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value system settings (school profile, admission status, contacts...).
 * Reads are cached to avoid a query on every public page render.
 */
class Setting extends Model
{
    public const CACHE_KEY = 'godwin.settings.all';

    /** @var array<int, string> */
    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
    ];

    /**
     * Fetch a setting value with an optional default.
     *
     * Setting::get('admissions.open', 'false') === 'true' during intake season.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->pluck('value', 'key')->all();
        });

        $value = $settings[$key] ?? $default;

        return match (static::detectType($value)) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? $value + 0 : $value,
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }

    /** Write (upsert) a setting and flush the cache. */
    public static function put(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_scalar($value) || $value === null ? $value : json_encode($value), 'group' => $group]
        );

        Cache::forget(self::CACHE_KEY);
    }

    /** Heuristic type detection for cached scalar values. */
    protected static function detectType(mixed $value): string
    {
        if (is_bool($value) || in_array($value, ['true', 'false'], true)) {
            return 'boolean';
        }
        if (is_numeric($value)) {
            return 'number';
        }
        if (is_string($value) && str_starts_with(trim($value), '[') || is_string($value) && str_starts_with(trim($value), '{')) {
            return 'json';
        }

        return 'string';
    }
}
