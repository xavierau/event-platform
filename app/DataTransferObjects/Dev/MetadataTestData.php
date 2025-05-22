<?php

namespace App\DataTransferObjects\Dev;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\DataCollection;

class MetadataTestData extends Data
{
    public function __construct(
        public readonly string $author,
        public readonly ?string $description, // Made nullable as per form default
        /** @var string[] */
        public readonly array $keywords,
        public readonly MetadataSettingsTestData $settings,
    ) {}

    public static function rules(): array
    {
        return [
            'author' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'keywords' => ['present', 'array'], // 'present' ensures the key exists, can be empty array
            'keywords.*' => ['string', 'distinct'], // Each keyword should be a string and distinct
            'settings' => ['required', 'array'], // Will be validated by MetadataSettingsTestData rules
        ];
    }
}
