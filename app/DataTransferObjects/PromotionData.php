<?php

namespace App\DataTransferObjects;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class PromotionData extends Data
{
    public function __construct(
        public readonly ?array $title,
        public readonly ?array $subtitle,
        public readonly string $url,
        public readonly bool $is_active = false,
        public readonly ?string $starts_at = null,
        public readonly ?string $ends_at = null,
        public readonly int $sort_order = 0,
        public readonly ?UploadedFile $uploaded_banner_image = null,
    ) {}

    public static function rules(): array
    {
        return [
            'title.en' => ['nullable', 'string', 'max:255'],
            'title.zh-TW' => ['nullable', 'string', 'max:255'],
            'title.zh-CH' => ['nullable', 'string', 'max:255'],
            'subtitle.en' => ['nullable', 'string', 'max:255'],
            'subtitle.zh-TW' => ['nullable', 'string', 'max:255'],
            'subtitle.zh-CH' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'sort_order' => ['integer'],
            'uploaded_banner_image' => ['nullable', 'file', 'image', 'max:10240', 'mimes:jpeg,png,webp'],
        ];
    }
}
