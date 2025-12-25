<?php

namespace App\Modules\TicketHold\Actions\Links;

use App\Modules\TicketHold\DTOs\PurchaseLinkData;
use App\Modules\TicketHold\Exceptions\LinkNotUsableException;
use App\Modules\TicketHold\Models\PurchaseLink;
use Illuminate\Support\Facades\DB;

class UpdatePurchaseLinkAction
{
    /**
     * Update an existing purchase link.
     *
     * Only allows updating: name, expires_at, notes, metadata
     * Other fields like quantity_mode and quantity_limit cannot be changed after creation.
     *
     * @throws LinkNotUsableException
     */
    public function execute(PurchaseLink $link, PurchaseLinkData $data): PurchaseLink
    {
        return DB::transaction(function () use ($link, $data) {
            // Check if link can still be modified
            if ($link->status->isUsable() === false && $link->quantity_purchased > 0) {
                throw new LinkNotUsableException(
                    "Cannot modify purchase link '{$link->name}' as it has already been used for purchases."
                );
            }

            // Update allowed fields only
            $link->update([
                'name' => $data->name,
                'expires_at' => $data->expires_at,
                'notes' => $data->notes,
                'metadata' => $data->metadata,
            ]);

            // Check and update expiration status if needed
            $link->checkAndUpdateExpiration();

            return $link->fresh()->load('ticketHold.allocations.ticketDefinition');
        });
    }
}
