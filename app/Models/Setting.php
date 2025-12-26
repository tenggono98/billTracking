<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a setting value by key
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        // Try to decode JSON, if fails return as string
        $decoded = json_decode($setting->value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value;
    }

    /**
     * Set a setting value by key
     */
    public static function set(string $key, mixed $value): void
    {
        $setting = static::firstOrNew(['key' => $key]);
        
        // Encode as JSON if it's an array or object
        $setting->value = is_array($value) || is_object($value) 
            ? json_encode($value) 
            : $value;
        
        $setting->save();
    }
}
