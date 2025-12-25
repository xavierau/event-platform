<?php

declare(strict_types=1);

namespace App\Modules\TemporaryRegistration\DataTransferObjects;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;

class TemporaryRegistrationPageData extends Data
{
    public function __construct(
        public readonly array $title,
        public readonly ?array $description,
        public readonly int $membership_level_id,
        public readonly bool $use_slug = false,
        public readonly ?string $slug = null,
        public readonly ?string $expires_at = null,
        public readonly ?int $duration_days = null,
        public readonly ?int $max_registrations = null,
        public readonly bool $is_active = true,
        public readonly ?array $metadata = null,
        #[Nullable]
        public readonly ?UploadedFile $banner_image = null,
    ) {}

    public static function rules(): array
    {
        $rules = [];
        $locales = config('app.available_locales', ['en' => 'English']);
        $primaryLocale = array_key_first($locales);

        foreach (array_keys($locales) as $locale) {
            $titleRequired = $locale === $primaryLocale ? 'required' : 'nullable';
            $rules["title.{$locale}"] = [$titleRequired, 'string', 'max:255'];
            $rules["description.{$locale}"] = ['nullable', 'string'];
        }

        $rules['membership_level_id'] = ['required', 'integer', 'exists:membership_levels,id'];
        $rules['use_slug'] = ['boolean'];
        $rules['slug'] = [
            'nullable',
            'string',
            'max:255',
            'alpha_dash',
            Rule::unique('temporary_registration_pages')->ignore(request()->route('temporary_registration_page')),
        ];
        $rules['expires_at'] = ['nullable', 'date', 'after:now'];
        $rules['duration_days'] = ['nullable', 'integer', 'min:1', 'max:365'];
        $rules['max_registrations'] = ['nullable', 'integer', 'min:1'];
        $rules['is_active'] = ['boolean'];
        $rules['metadata'] = ['nullable', 'array'];
        $rules['banner_image'] = ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'];

        return $rules;
    }
}
