<?php

namespace App\Modules\TicketHold\Actions\Holds;

use App\Models\Booking;
use App\Models\TicketDefinition;
use App\Modules\TicketHold\Exceptions\InsufficientInventoryException;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use Illuminate\Support\Facades\DB;

class ValidateHoldAvailabilityAction
{
    /**
     * Validate that sufficient inventory exists for a new hold allocation.
     *
     * IMPORTANT: This method uses pessimistic locking to prevent race conditions.
     * It should be called within a database transaction for the lock to be effective.
     *
     * @param  int|null  $excludeHoldId  Hold ID to exclude from calculation (for updates)
     *
     * @throws InsufficientInventoryException
     */
    public function execute(
        int $ticketDefinitionId,
        int $requestedQuantity,
        int $eventOccurrenceId,
        ?int $excludeHoldId = null
    ): void {
        // Lock the ticket definition row to prevent concurrent modifications
        $ticketDefinition = TicketDefinition::lockForUpdate()->findOrFail($ticketDefinitionId);

        // If unlimited inventory, always available
        if (is_null($ticketDefinition->total_quantity)) {
            return;
        }

        // Get total inventory
        $totalInventory = $ticketDefinition->total_quantity;

        // Get booked quantity (confirmed bookings) with lock
        $bookedQuantity = Booking::where('ticket_definition_id', $ticketDefinitionId)
            ->whereIn('status', ['confirmed', 'pending_confirmation'])
            ->lockForUpdate()
            ->sum('quantity');

        // Get held quantity (excluding current hold if updating) with lock
        $heldQuery = HoldTicketAllocation::where('ticket_definition_id', $ticketDefinitionId)
            ->whereHas('ticketHold', function ($q) use ($eventOccurrenceId) {
                $q->where('event_occurrence_id', $eventOccurrenceId)
                    ->active();
            })
            ->lockForUpdate();

        if ($excludeHoldId) {
            $heldQuery->where('ticket_hold_id', '!=', $excludeHoldId);
        }

        $heldQuantity = $heldQuery->sum(DB::raw('allocated_quantity - purchased_quantity'));

        // Calculate available
        $available = $totalInventory - $bookedQuantity - $heldQuantity;

        if ($requestedQuantity > $available) {
            throw new InsufficientInventoryException(
                "Insufficient inventory for ticket '{$ticketDefinition->name}'. ".
                "Requested: {$requestedQuantity}, Available: {$available}"
            );
        }
    }
}
