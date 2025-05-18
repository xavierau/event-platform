<?php

namespace App\DataTransferObjects;

use App\Enums\TicketDefinitionStatus;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation;
use Spatie\LaravelData\Support\Validation\ValidationContext;

#[MapName(SnakeCaseMapper::class)]
class TicketDefinitionData extends Data
{
    public function __construct(
        #[Validation\Rule(['nullable', 'integer'])]
        public readonly ?int $id,

        #[Validation\Rule(['required', 'array'])]
        public readonly array $name, // Translatable

        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $description, // Translatable

        #[Validation\Rule(['required', 'integer', 'min:0'])]
        public readonly int $price, // In smallest currency unit (e.g., cents)

        // #[Validation\Rule(['required', 'string', 'size:3'])] // Model has currency, DTO can too if needed
        // public readonly ?string $currency, // Default could be from config('app.currency')

        #[Validation\Rule(['nullable', 'integer', 'min:0'])]
        public readonly ?int $totalQuantity,

        #[Validation\Rule(['nullable', 'date_format:Y-m-d\TH:i'])]
        public readonly ?string $availabilityWindowStart,

        #[Validation\Rule(['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:availability_window_start'])]
        public readonly ?string $availabilityWindowEnd,

        #[Validation\Rule(['required', 'integer', 'min:1'])]
        public readonly int $minPerOrder = 1,

        #[Validation\Rule(['nullable', 'integer', 'min:1'])]
        public readonly ?int $maxPerOrder,

        #[Validation\Rule(['required', new Enum(TicketDefinitionStatus::class)])]
        public readonly TicketDefinitionStatus $status = TicketDefinitionStatus::ACTIVE,

        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $metadata
    ) {}

    public static function rules(ValidationContext $context): array
    {
        $rules = [
            // 'currency' => ['sometimes', 'string', 'size:3'], // If re-enabled
        ];
        $availableLocales = config('app.available_locales', ['en' => 'English']);
        $primaryLocale = config('app.locale', 'en');

        $rules["name.{$primaryLocale}"] = ['required', 'string', 'max:255'];
        foreach ($availableLocales as $localeCode => $localeName) {
            if ($localeCode !== $primaryLocale) {
                $rules["name.{$localeCode}"] = ['nullable', 'string', 'max:255'];
                $rules["description.{$localeCode}"] = ['nullable', 'string'];
            }
        }

        if (!empty($context->payload['max_per_order']) && !empty($context->payload['min_per_order'])) {
            $rules['max_per_order'][] = 'gte:min_per_order';
        }

        return $rules;
    }
}
