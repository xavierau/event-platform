<?php

namespace App\DataTransferObjects;

use Spatie\LaravelData\Data;

class CategoryData extends Data
{
    public function __construct(
        public readonly array $name, // e.g., ['en' => 'Name', 'zh-TW' => '名稱']
        public readonly string $slug,
        public readonly ?int $parent_id = null,
        public readonly bool $is_active = true,
        public readonly ?int $id = null, // For updates
    ) {}
}
