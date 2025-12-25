<?php

namespace App\Modules\TicketHold\Actions\Links;

use App\Models\User;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Models\PurchaseLink;
use Illuminate\Support\Facades\DB;

class RevokePurchaseLinkAction
{
    /**
     * Revoke a purchase link, preventing further use.
     */
    public function execute(PurchaseLink $link, User $revokedBy): PurchaseLink
    {
        return DB::transaction(function () use ($link, $revokedBy) {
            // Only revoke if link is currently active
            if ($link->status === LinkStatusEnum::ACTIVE) {
                $link->update([
                    'status' => LinkStatusEnum::REVOKED,
                    'revoked_at' => now(),
                    'revoked_by' => $revokedBy->id,
                ]);
            }

            return $link->fresh()->load('ticketHold', 'revokedByUser');
        });
    }
}
