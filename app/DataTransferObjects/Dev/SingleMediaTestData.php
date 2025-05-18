<?php

namespace App\DataTransferObjects\Dev;

use Spatie\LaravelData\Data;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File as FileValidationRule;

class SingleMediaTestData extends Data
{
    public function __construct(
        public readonly string $someOtherField,
        public readonly MetadataTestData $metadata,
        public readonly ?UploadedFile $singleFile, // Nullable if no file is uploaded
    ) {}

    public static function rules(): array
    {
        return [
            'someOtherField' => ['required', 'string', 'max:255'],
            'metadata' => ['required', 'array'], // Validated by MetadataTestData rules
            'singleFile' => [
                'nullable',
                FileValidationRule::types(['jpg', 'jpeg', 'png', 'gif', 'webp'])
                    ->max(5 * 1024), // Max 5MB, matching frontend
            ],
        ];
    }
}
