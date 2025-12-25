<?php

namespace App\Modules\TicketHold\DTOs;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class HoldPurchaseRequestData extends Data
{
    public function __construct(
        #[Required]
        public string $link_code,

        /** @var array<HoldPurchaseItemData> */
        #[Required]
        public array $items,

        #[Nullable]
        public ?string $coupon_code = null,
    ) {}

    public static function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_definition_id' => ['required', 'exists:ticket_definitions,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
