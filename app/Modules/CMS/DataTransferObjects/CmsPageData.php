<?php

namespace App\Modules\CMS\DataTransferObjects;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class CmsPageData extends Data
{
    public function __construct(
        #[Required, ArrayType]
        public readonly array $title,

        #[Required]
        public readonly string $slug,

        #[Required, ArrayType]
        public readonly array $content,

        #[Nullable, ArrayType]
        public readonly ?array $meta_description,

        #[Nullable, ArrayType]
        public readonly ?array $meta_keywords,

        #[Nullable]
        public readonly ?bool $is_published,

        #[Nullable]
        public readonly ?string $published_at,

        #[Nullable]
        public readonly ?int $author_id,

        #[Nullable]
        public readonly ?int $sort_order,

        #[Nullable]
        public readonly ?UploadedFile $featured_image,

        #[Nullable]
        public readonly ?array $gallery_images,
    ) {}

    public static function rules(): array
    {
        return [
            'title.en' => ['required', 'string', 'max:255'],
            'title.zh-TW' => ['nullable', 'string', 'max:255'],
            'title.zh-CN' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:cms_pages,slug'],
            'content.en' => ['required', 'string'],
            'content.zh-TW' => ['nullable', 'string'],
            'content.zh-CN' => ['nullable', 'string'],
            'meta_description.en' => ['nullable', 'string', 'max:160'],
            'meta_description.zh-TW' => ['nullable', 'string', 'max:160'],
            'meta_description.zh-CN' => ['nullable', 'string', 'max:160'],
            'meta_keywords.en' => ['nullable', 'string', 'max:255'],
            'meta_keywords.zh-TW' => ['nullable', 'string', 'max:255'],
            'meta_keywords.zh-CN' => ['nullable', 'string', 'max:255'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'author_id' => ['nullable', 'integer', 'exists:users,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'featured_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'mimes:jpeg,png,webp', 'max:2048'],
        ];
    }
}
