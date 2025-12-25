<?php

namespace App\Modules\TicketHold\DTOs;

use App\Modules\TicketHold\Enums\HoldStatusEnum;
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

class TicketHoldData extends Data
{
    public function __construct(
        #[Required]
        #[Exists('event_occurrences', 'id')]
        public int $event_occurrence_id,

        #[Nullable]
        #[Exists('organizers', 'id')]
        public ?int $organizer_id,

        #[Required]
        #[Min(1)]
        #[Max(255)]
        public string $name,

        #[Nullable]
        #[Max(5000)]
        public ?string $description,

        #[Nullable]
        #[Max(5000)]
        public ?string $internal_notes,

        /** @var array<TicketAllocationData> */
        #[Required]
        public array $allocations,

        #[Nullable]
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?Carbon $expires_at,

        public int|Optional $id = new Optional,
        public HoldStatusEnum|Optional $status = new Optional,
    ) {}

    public static function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.ticket_definition_id' => ['required', 'exists:ticket_definitions,id'],
            'allocations.*.allocated_quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
