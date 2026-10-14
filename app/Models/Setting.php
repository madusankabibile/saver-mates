<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Site-wide switches the admin panel controls (registration, announcement
 * banner). Read on every page, so the whole table is cached as one array.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'registration_open' => '1',
        'announcement' => '',
        'announcement_tone' => 'info',
    ];

    public static function cached(): array
    {
        return Cache::rememberForever('site-settings', function () {
            if (! Schema::hasTable('settings')) {
                return self::DEFAULTS;
            }

            return array_merge(self::DEFAULTS, self::query()->pluck('value', 'key')->all());
        });
    }

    public static function get(string $key, $default = null)
    {
        return self::cached()[$key] ?? $default;
    }

    public static function flag(string $key): bool
    {
        return (bool) self::get($key);
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            self::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget('site-settings');
    }
}
