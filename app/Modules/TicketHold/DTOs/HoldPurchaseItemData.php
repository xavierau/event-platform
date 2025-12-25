<?php

namespace App\Modules\TicketHold\DTOs;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class HoldPurchaseItemData extends Data
{
    public function __construct(
        #[Required]
        #[Exists('ticket_definitions', 'id')]
        public int $ticket_definition_id,

        #[Required]
        #[Min(1)]
        public int $quantity,
    ) {}
}
