<?php

namespace App\Models\Rbac;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RbacSetting extends Model
{
    protected $table      = 'rbac_settings';
    protected $primaryKey = 'key';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = ['key', 'value'];

    private const CACHE_TTL = 60; // seconds — short so toggles propagate quickly

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("rbac.setting.{$key}", self::CACHE_TTL, function () use ($key, $default) {
            $row = static::find($key);
            return $row ? $row->value : $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("rbac.setting.{$key}");
    }
}
