<?php

namespace App\Modules\CMS\DataTransferObjects;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Illuminate\Validation\Rule;

class CmsPageData extends Data
{
    public function __construct(
        public array $title,
        public array $content,
        public ?array $meta_description,
        public ?array $meta_keywords,
        public ?bool $is_published,
        public ?string $slug,
        #[Nullable]
        public readonly ?string $published_at,
        #[Nullable]
        public readonly ?int $author_id,
        public ?int $sort_order,
        #[Nullable]
        public readonly ?UploadedFile $featured_image,
        #[Nullable]
        public readonly ?array $gallery_images,
    ) {}

    public static function rules(): array
    {
        $rules = [];
        $locales = config('app.available_locales', ['en' => 'English']);
        $primaryLocale = array_key_first($locales);

        foreach (array_keys($locales) as $locale) {
            $titleRequired = $locale === $primaryLocale ? 'required' : 'nullable';
            $rules["title.{$locale}"] = [$titleRequired, 'string', 'max:255'];

            $contentRequired = $locale === $primaryLocale ? 'required' : 'nullable';
            $rules["content.{$locale}"] = [$contentRequired, 'string'];

            $rules["meta_description.{$locale}"] = ['nullable', 'string', 'max:160'];
            $rules["meta_keywords.{$locale}"] = ['nullable', 'string', 'max:255'];
        }

        $rules['slug'] = ['nullable', 'string', 'max:255', Rule::unique('cms_pages')->ignore(request()->route('cms_page'))];
        $rules['is_published'] = ['nullable', 'boolean'];
        $rules['published_at'] = ['nullable', 'date'];
        $rules['author_id'] = ['nullable', 'integer', 'exists:users,id'];
        $rules['sort_order'] = ['nullable', 'integer', 'min:0'];
        $rules['featured_image'] = ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'];
        $rules['gallery_images'] = ['nullable', 'array'];
        $rules['gallery_images.*'] = ['image', 'mimes:jpeg,png,webp', 'max:2048'];

        return $rules;
    }
}
