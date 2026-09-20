<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const KEY_BENGKEL_PERCENTAGE = 'bengkel_percentage';

    protected $fillable = ['key', 'value'];

    /**
     * Ambil nilai setting dengan cache ringan (hindari query berulang).
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting.{$key}", function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.{$key}");
    }

    public static function bengkelPercentage(): float
    {
        return (float) static::get(self::KEY_BENGKEL_PERCENTAGE, 20);
    }
}
