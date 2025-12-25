<?php

namespace App\Modules\TicketHold\Actions\Holds;

use App\Models\User;
use App\Modules\TicketHold\DTOs\TicketHoldData;
use App\Modules\TicketHold\Enums\HoldStatusEnum;
use App\Modules\TicketHold\Exceptions\InsufficientInventoryException;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Support\Facades\DB;

class CreateTicketHoldAction
{
    public function __construct(
        private ValidateHoldAvailabilityAction $validateAvailability
    ) {}

    /**
     * Create a new ticket hold with allocations.
     *
     * @throws InsufficientInventoryException
     */
    public function execute(TicketHoldData $data, User $creator): TicketHold
    {
        return DB::transaction(function () use ($data, $creator) {
            // Validate inventory availability for all allocations
            foreach ($data->allocations as $allocation) {
                $this->validateAvailability->execute(
                    $allocation->ticket_definition_id,
                    $allocation->allocated_quantity,
                    $data->event_occurrence_id
                );
            }

            // Create the hold
            $hold = TicketHold::create([
                'event_occurrence_id' => $data->event_occurrence_id,
                'organizer_id' => $data->organizer_id,
                'created_by' => $creator->id,
                'name' => $data->name,
                'description' => $data->description,
                'internal_notes' => $data->internal_notes,
                'status' => HoldStatusEnum::ACTIVE,
                'expires_at' => $data->expires_at,
            ]);

            // Create allocations
            foreach ($data->allocations as $allocationData) {
                HoldTicketAllocation::create([
                    'ticket_hold_id' => $hold->id,
                    'ticket_definition_id' => $allocationData->ticket_definition_id,
                    'allocated_quantity' => $allocationData->allocated_quantity,
                    'purchased_quantity' => 0,
                    'pricing_mode' => $allocationData->pricing_mode,
                    'custom_price' => $allocationData->custom_price,
                    'discount_percentage' => $allocationData->discount_percentage,
                ]);
            }

            return $hold->load('allocations.ticketDefinition', 'eventOccurrence.event');
        });
    }
}
