<?php

namespace App\DataTransferObjects\Tag;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Rule;

class TagData extends Data
{
    public function __construct(
        public readonly ?int $id,
        #[Rule(['required', 'array'])]
        public readonly array $name, // e.g., ['en' => 'Tag Name', 'zh-TW' => '標籤名稱']
        #[Rule(['required', 'string', 'max:255'])] // Slug uniqueness will be handled in Action/Service
        public readonly string $slug,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'], // Example: English is required
            'name.zh-TW' => ['nullable', 'string', 'max:255'],
            'name.zh-CN' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'], // Basic validation, uniqueness in action
        ];
    }
}
