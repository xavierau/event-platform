<?php

namespace App\Modules\TicketHold\Actions\Links;

use App\Modules\TicketHold\DTOs\PurchaseLinkData;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Exceptions\HoldNotActiveException;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Support\Facades\DB;

class CreatePurchaseLinkAction
{
    /**
     * Create a new purchase link for a ticket hold.
     *
     * @throws HoldNotActiveException
     */
    public function execute(PurchaseLinkData $data): PurchaseLink
    {
        return DB::transaction(function () use ($data) {
            // Validate the hold is active
            $hold = TicketHold::findOrFail($data->ticket_hold_id);

            if (! $hold->is_usable) {
                throw new HoldNotActiveException(
                    "Cannot create purchase link for hold '{$hold->name}'. Hold is not active or has expired."
                );
            }

            // Create the purchase link
            $link = PurchaseLink::create([
                'ticket_hold_id' => $data->ticket_hold_id,
                'code' => PurchaseLink::generateUniqueCode(),
                'name' => $data->name,
                'assigned_user_id' => $data->assigned_user_id,
                'quantity_mode' => $data->quantity_mode,
                'quantity_limit' => $data->quantity_limit,
                'quantity_purchased' => 0,
                'status' => LinkStatusEnum::ACTIVE,
                'expires_at' => $data->expires_at,
                'notes' => $data->notes,
                'metadata' => $data->metadata,
            ]);

            return $link->load('ticketHold.allocations.ticketDefinition');
        });
    }
}
