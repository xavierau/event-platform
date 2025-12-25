<?php

namespace App\Modules\TicketHold\DTOs;

use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class PurchaseLinkData extends Data
{
    public function __construct(
        #[Required]
        #[Exists('ticket_holds', 'id')]
        public int $ticket_hold_id,

        #[Nullable]
        #[Max(255)]
        public ?string $name,

        #[Nullable]
        #[Exists('users', 'id')]
        public ?int $assigned_user_id,

        #[Required]
        public QuantityModeEnum $quantity_mode,

        #[Nullable]
        #[Min(1)]
        public ?int $quantity_limit,

        #[Nullable]
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?Carbon $expires_at,

        #[Nullable]
        #[Max(5000)]
        public ?string $notes,

        #[Nullable]
        public ?array $metadata,

        public int|Optional $id = new Optional,
        public string|Optional $code = new Optional,
        public LinkStatusEnum|Optional $status = new Optional,
    ) {}

    public static function rules(): array
    {
        return [
            'quantity_limit' => ['required_unless:quantity_mode,unlimited', 'nullable', 'integer', 'min:1'],
        ];
    }
}
