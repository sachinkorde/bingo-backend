<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'label'];

    /** Read a setting value (falls back to $default if not set). */
    public static function get(string $key, $default = null)
    {
        $row = static::where('key', $key)->first();

        return $row && $row->value !== null ? $row->value : $default;
    }

    /** Create or update a setting value. */
    public static function put(string $key, $value, ?string $label = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            array_filter([
                'value' => (string) $value,
                'label' => $label,
            ], fn ($v) => $v !== null)
        );
    }
}
