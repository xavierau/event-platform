<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class SiteSetting extends Model
{
    use HasFactory, HasTranslations;

    public array $translatable = ['value'];

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 'value' is handled by HasTranslations, but if you had non-translatable JSON fields, you would cast them here.
        ];
    }

    /**
     * Retrieve a site setting value by its key.
     *
     * @param string $key The key of the setting.
     * @param mixed $default Optional default value if the key is not found.
     * @return mixed The value of the setting or the default value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if ($setting) {
            return $setting->value; // This will return the translated value for the current locale
        }

        return $default;
    }
}
