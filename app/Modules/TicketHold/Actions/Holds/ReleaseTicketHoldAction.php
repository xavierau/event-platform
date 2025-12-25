<?php

namespace App\Modules\TicketHold\Actions\Holds;

use App\Models\User;
use App\Modules\TicketHold\Enums\HoldStatusEnum;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Support\Facades\DB;

class ReleaseTicketHoldAction
{
    /**
     * Release a ticket hold, making held tickets available for public sale.
     * Also revokes all associated purchase links.
     */
    public function execute(TicketHold $hold, User $releasedBy): TicketHold
    {
        return DB::transaction(function () use ($hold, $releasedBy) {
            // Release the hold
            $hold->update([
                'status' => HoldStatusEnum::RELEASED,
                'released_at' => now(),
                'released_by' => $releasedBy->id,
            ]);

            // Revoke all active purchase links
            $hold->purchaseLinks()
                ->where('status', LinkStatusEnum::ACTIVE)
                ->update([
                    'status' => LinkStatusEnum::REVOKED,
                    'revoked_at' => now(),
                    'revoked_by' => $releasedBy->id,
                ]);

            return $hold->fresh(['allocations.ticketDefinition', 'purchaseLinks']);
        });
    }
}
