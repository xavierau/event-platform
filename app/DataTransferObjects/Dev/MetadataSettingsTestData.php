<?php

namespace App\DataTransferObjects\Dev;

use Spatie\LaravelData\Data;
use Illuminate\Validation\Rule;

class MetadataSettingsTestData extends Data
{
    public function __construct(
        public readonly bool $isVisible,
        public readonly int $rating,
    ) {}

    public static function rules(): array
    {
        return [
            'isVisible' => ['required', 'boolean'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }

    // Optional: You can add casts if needed, for example, if isVisible comes as "1" or "0"
    // public static function prepareForPipeline(array $payload): array
    // {
    //     if (isset($payload['isVisible'])) {
    //         $payload['isVisible'] = filter_var($payload['isVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    //     }
    //     return $payload;
    // }
}
