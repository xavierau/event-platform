<?php

namespace App\Modules\TicketHold\Actions\Holds;

use App\Modules\TicketHold\DTOs\TicketHoldData;
use App\Modules\TicketHold\Exceptions\InsufficientInventoryException;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Support\Facades\DB;

class UpdateTicketHoldAction
{
    public function __construct(
        private ValidateHoldAvailabilityAction $validateAvailability
    ) {}

    /**
     * Update an existing ticket hold.
     *
     * @throws InsufficientInventoryException
     */
    public function execute(TicketHold $hold, TicketHoldData $data): TicketHold
    {
        return DB::transaction(function () use ($hold, $data) {
            // Validate inventory availability for all allocations (excluding current hold)
            foreach ($data->allocations as $allocation) {
                $this->validateAvailability->execute(
                    $allocation->ticket_definition_id,
                    $allocation->allocated_quantity,
                    $data->event_occurrence_id,
                    $hold->id
                );
            }

            // Update the hold
            $hold->update([
                'name' => $data->name,
                'description' => $data->description,
                'internal_notes' => $data->internal_notes,
                'expires_at' => $data->expires_at,
            ]);

            // Get existing allocation IDs
            $existingAllocationIds = $hold->allocations->pluck('ticket_definition_id', 'id')->toArray();
            $newTicketDefinitionIds = collect($data->allocations)->pluck('ticket_definition_id')->toArray();

            // Delete allocations that are no longer in the list
            foreach ($existingAllocationIds as $allocationId => $ticketDefId) {
                if (! in_array($ticketDefId, $newTicketDefinitionIds)) {
                    HoldTicketAllocation::find($allocationId)?->delete();
                }
            }

            // Update or create allocations
            foreach ($data->allocations as $allocationData) {
                $existingAllocation = $hold->allocations
                    ->where('ticket_definition_id', $allocationData->ticket_definition_id)
                    ->first();

                if ($existingAllocation) {
                    // Update existing allocation
                    $existingAllocation->update([
                        'allocated_quantity' => $allocationData->allocated_quantity,
                        'pricing_mode' => $allocationData->pricing_mode,
                        'custom_price' => $allocationData->custom_price,
                        'discount_percentage' => $allocationData->discount_percentage,
                    ]);
                } else {
                    // Create new allocation
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
            }

            return $hold->fresh(['allocations.ticketDefinition', 'eventOccurrence.event']);
        });
    }
}
