<?php

namespace App\DataTransferObjects;

use App\Enums\TicketDefinitionStatus;
use App\Models\TicketDefinition;
use App\Rules\MaxPerOrderGreaterThanMinRule;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Attributes\Validation;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

#[MapInputName(SnakeCaseMapper::class)]
class TicketDefinitionData extends Data
{
    public function __construct(
        #[Validation\Rule(['nullable', 'integer'])]
        public readonly ?int $id,

        #[Validation\Rule(['required', 'array'])]
        public readonly array $name,

        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $description,

        #[Validation\Rule(['required', 'integer', 'min:0'])]
        public readonly int $price,

        #[Validation\Rule(['required', 'string', 'size:3'])]
        public readonly ?string $currency,

        #[Validation\Rule(['nullable', 'date'])]
        public readonly ?string $availability_window_start,

        #[Validation\Rule(['nullable', 'date', 'after_or_equal:availability_window_start'])]
        public readonly ?string $availability_window_end,

        #[Validation\Rule(['nullable', 'date'])]
        public readonly ?string $availability_window_start_utc,

        #[Validation\Rule(['nullable', 'date', 'after_or_equal:availability_window_start_utc'])]
        public readonly ?string $availability_window_end_utc,

        #[Validation\Rule(['nullable', 'integer', 'min:0'])]
        public readonly ?int $total_quantity,

        #[Validation\Rule(['required', 'integer', 'min:1'])]
        public readonly int $min_per_order = 1,

        #[Validation\Rule(['nullable', 'integer', 'min:1'])]
        public readonly ?int $max_per_order = null,

        #[Validation\Rule(['required', new Enum(TicketDefinitionStatus::class)])]
        public readonly TicketDefinitionStatus $status = TicketDefinitionStatus::ACTIVE,

        #[Validation\Rule([
            'nullable',
            'array',
            'event_occurrence_ids.*' => ['integer', 'exists:event_occurrences,id']
        ])]
        public readonly ?array $event_occurrence_ids = null,

        public readonly ?string $timezone = null,

        public readonly ?array $membership_discounts = null,

        public readonly ?Carbon $created_at = null,
        public readonly ?Carbon $updated_at = null
    ) {}

    public static function rules(ValidationContext $context): array
    {
        $rules = [
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'array'],
            'description' => ['nullable', 'array'],
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'availability_window_start' => ['nullable', 'date', 'sometimes'],
            'availability_window_end' => ['nullable', 'date', 'after_or_equal:availability_window_start', 'sometimes'],
            'availability_window_start_utc' => ['nullable', 'date', 'sometimes'],
            'availability_window_end_utc' => ['nullable', 'date', 'after_or_equal:availability_window_start_utc', 'sometimes'],
            'total_quantity' => ['nullable', 'integer', 'min:0'],
            'min_per_order' => ['nullable', 'integer', 'min:1'],
            'max_per_order' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', new Enum(TicketDefinitionStatus::class)],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'event_occurrence_ids' => ['nullable', 'array'],
            'event_occurrence_ids.*' => ['integer', 'exists:event_occurrences,id'],
            'membership_discounts' => ['nullable', 'array'],
            'membership_discounts.*.membership_level_id' => ['required', 'integer', 'exists:membership_levels,id'],
            'membership_discounts.*.discount_type' => ['required', 'string', 'in:percentage,fixed'],
            'membership_discounts.*.discount_value' => ['required', 'integer', 'min:0'],
        ];
        $availableLocales = config('app.available_locales', ['en' => 'English']);
        $primaryLocale = config('app.locale', 'en');

        $rules["name.{$primaryLocale}"] = ['required', 'string', 'max:255'];
        foreach (array_keys($availableLocales) as $localeCode) {
            if ($localeCode !== $primaryLocale) {
                $rules["name.{$localeCode}"] = ['nullable', 'string', 'max:255'];
            }
        }

        if (!empty($context->payload['max_per_order']) && !empty($context->payload['min_per_order'])) {
            $rules['max_per_order'][] = 'gte:min_per_order';
        }
        return $rules;
    }


    public static function fromModel(TicketDefinition $ticketDefinition): self
    {
        $dtoTimezone = $ticketDefinition->timezone ?? config('app.timezone', 'UTC');

        // Get membership discounts using direct query (relationship method has issues)
        $membershipDiscounts = \Illuminate\Support\Facades\DB::table('ticket_definition_membership_discounts')
            ->where('ticket_definition_id', $ticketDefinition->id)
            ->select('membership_level_id', 'discount_type', 'discount_value')
            ->get()
            ->map(function ($discount) {
                return [
                    'membership_level_id' => $discount->membership_level_id,
                    'discount_type' => $discount->discount_type,
                    'discount_value' => $discount->discount_value,
                ];
            })
            ->toArray();

        return new self(
            id: $ticketDefinition->id,
            name: $ticketDefinition->getTranslations('name'),
            description: $ticketDefinition->getTranslations('description'),
            price: $ticketDefinition->price,
            currency: $ticketDefinition->currency,
            availability_window_start: $ticketDefinition->availability_window_start,
            availability_window_end: $ticketDefinition->availability_window_end,
            availability_window_start_utc: $ticketDefinition->availability_window_start_utc,
            availability_window_end_utc: $ticketDefinition->availability_window_end_utc,
            total_quantity: $ticketDefinition->total_quantity,
            min_per_order: $ticketDefinition->min_per_order,
            max_per_order: $ticketDefinition->max_per_order,
            status: $ticketDefinition->status,
            timezone: $ticketDefinition->timezone,
            created_at: $ticketDefinition->created_at,
            updated_at: $ticketDefinition->updated_at,
            event_occurrence_ids: $ticketDefinition->eventOccurrences()->pluck('id')->toArray(),
            membership_discounts: $membershipDiscounts
        );
    }
}
