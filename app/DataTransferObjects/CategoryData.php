<?php

namespace App\DataTransferObjects;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation;
use Illuminate\Http\UploadedFile;

class CategoryData extends Data
{
    public function __construct(
        public readonly array $name, // e.g., ['en' => 'Name', 'zh-TW' => '名稱']
        public readonly string $slug,
        public readonly ?int $parent_id = null,
        public readonly bool $is_active = true,
        public readonly ?int $id = null, // For updates

        #[Validation\Rule(['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:2048'])] // Max 2MB
        public readonly ?UploadedFile $uploaded_icon = null, // For icon upload

        public readonly bool $remove_icon = false, // Flag to remove current icon

        // Media relationship data (for displaying existing icons)
        public readonly ?array $media = null,
    ) {}

    public static function rules(): array
    {
        $rules = [];
        $requiredLocale = config('app.locale', 'en');
        $otherLocales = array_diff(config('translatable.locales', ['en', 'zh-TW', 'zh-CN']), [$requiredLocale]);

        // Name field validation (translatable)
        $rules['name'] = ['required', 'array'];
        $rules["name.{$requiredLocale}"] = ['required', 'string', 'max:255'];

        // Optional locale validation for name
        foreach ($otherLocales as $locale) {
            $rules["name.{$locale}"] = ['nullable', 'string', 'max:255'];
        }

        // Slug validation
        $rules['slug'] = ['required', 'string', 'max:255'];

        // Parent category validation (exclude self to prevent circular reference)
        $rules['parent_id'] = ['nullable', 'integer', 'exists:categories,id'];

        // Active status validation
        $rules['is_active'] = ['required', 'boolean'];

        // ID validation (for updates)
        $rules['id'] = ['nullable', 'integer'];

        // Media validation (not required, just for structure)
        $rules['media'] = ['nullable', 'array'];

        // Remove icon flag validation
        $rules['remove_icon'] = ['nullable', 'boolean'];

        return $rules;
    }
}
