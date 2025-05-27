<?php

namespace App\Actions\Wishlist;

use App\DataTransferObjects\WishlistData;
use App\Models\Event;
use App\Models\User;

class RemoveFromWishlistAction
{
    public function execute(WishlistData $wishlistData): bool
    {
        // Find the user and event, throw ModelNotFoundException if not found
        $user = User::findOrFail($wishlistData->user_id);
        $event = Event::findOrFail($wishlistData->event_id);

        // Check if in wishlist
        if (!$user->hasInWishlist($event)) {
            return false;
        }

        // Remove from wishlist
        $user->removeFromWishlist($event);

        return true;
    }
}
