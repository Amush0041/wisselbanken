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

    /** @return list<int> */
    public static function enforcedOrgIds(): array
    {
        $raw = static::get('rbac_enforced_org_ids');
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            return [];
        }

        $ids = [];
        foreach ($decoded as $item) {
            if (is_int($item) && $item > 0) {
                $ids[] = $item;
            }
        }

        return array_values(array_unique($ids));
    }
}
