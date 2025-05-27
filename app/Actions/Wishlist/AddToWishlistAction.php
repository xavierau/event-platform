<?php

namespace App\Actions\Wishlist;

use App\DataTransferObjects\WishlistData;
use App\Models\Event;
use App\Models\User;

class AddToWishlistAction
{
    public function execute(WishlistData $wishlistData): bool
    {
        // Find the user and event, throw ModelNotFoundException if not found
        $user = User::findOrFail($wishlistData->user_id);
        $event = Event::findOrFail($wishlistData->event_id);

        // Check if already in wishlist
        if ($user->hasInWishlist($event)) {
            return false;
        }

        // Add to wishlist
        $user->addToWishlist($event);

        return true;
    }
}
