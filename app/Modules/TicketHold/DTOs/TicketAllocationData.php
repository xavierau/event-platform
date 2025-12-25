<?php

namespace App\Modules\TicketHold\DTOs;

use App\Modules\TicketHold\Enums\PricingModeEnum;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class TicketAllocationData extends Data
{
    public function __construct(
        #[Required]
        #[Exists('ticket_definitions', 'id')]
        public int $ticket_definition_id,

        #[Required]
        #[Min(1)]
        public int $allocated_quantity,

        #[Required]
        public PricingModeEnum $pricing_mode,

        #[Nullable]
        #[Min(0)]
        public ?int $custom_price, // In cents

        #[Nullable]
        #[Min(0)]
        #[Max(100)]
        public ?int $discount_percentage,
    ) {}

    public static function rules(): array
    {
        return [
            'custom_price' => ['required_if:pricing_mode,fixed', 'nullable', 'integer', 'min:0'],
            'discount_percentage' => ['required_if:pricing_mode,percentage_discount', 'nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
