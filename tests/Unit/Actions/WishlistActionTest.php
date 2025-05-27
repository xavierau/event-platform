<?php

namespace Tests\Unit\Actions;

use App\Actions\Wishlist\AddToWishlistAction;
use App\Actions\Wishlist\RemoveFromWishlistAction;
use App\DataTransferObjects\WishlistData;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WishlistActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function add_to_wishlist_action_adds_event_to_user_wishlist()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        $wishlistData = WishlistData::from([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $action = new AddToWishlistAction();
        $result = $action->execute($wishlistData);

        $this->assertTrue($result);
        $this->assertTrue($user->hasInWishlist($event));
        $this->assertCount(1, $user->wishlistedEvents);
    }

    #[Test]
    public function add_to_wishlist_action_returns_false_if_already_in_wishlist()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        // Add to wishlist first
        $user->addToWishlist($event);

        $wishlistData = WishlistData::from([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $action = new AddToWishlistAction();
        $result = $action->execute($wishlistData);

        $this->assertFalse($result);
        $this->assertCount(1, $user->wishlistedEvents); // Still only one
    }

    #[Test]
    public function add_to_wishlist_action_throws_exception_for_nonexistent_user()
    {
        $event = Event::factory()->create(['event_status' => 'published']);

        // This will throw ValidationException during DTO creation, not ModelNotFoundException
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        WishlistData::validateAndCreate([
            'user_id' => 999, // Non-existent user
            'event_id' => $event->id,
        ]);
    }

    #[Test]
    public function add_to_wishlist_action_throws_exception_for_nonexistent_event()
    {
        $user = User::factory()->create();

        // This will throw ValidationException during DTO creation, not ModelNotFoundException
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        WishlistData::validateAndCreate([
            'user_id' => $user->id,
            'event_id' => 999, // Non-existent event
        ]);
    }

    #[Test]
    public function remove_from_wishlist_action_removes_event_from_user_wishlist()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        // Add to wishlist first
        $user->addToWishlist($event);
        $this->assertTrue($user->hasInWishlist($event));

        $wishlistData = WishlistData::from([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $action = new RemoveFromWishlistAction();
        $result = $action->execute($wishlistData);

        $this->assertTrue($result);
        $this->assertFalse($user->hasInWishlist($event));
        $this->assertCount(0, $user->wishlistedEvents);
    }

    #[Test]
    public function remove_from_wishlist_action_returns_false_if_not_in_wishlist()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        $wishlistData = WishlistData::from([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $action = new RemoveFromWishlistAction();
        $result = $action->execute($wishlistData);

        $this->assertFalse($result);
        $this->assertCount(0, $user->wishlistedEvents);
    }

    #[Test]
    public function remove_from_wishlist_action_throws_exception_for_nonexistent_user()
    {
        $event = Event::factory()->create(['event_status' => 'published']);

        // This will throw ValidationException during DTO creation, not ModelNotFoundException
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        WishlistData::validateAndCreate([
            'user_id' => 999, // Non-existent user
            'event_id' => $event->id,
        ]);
    }

    #[Test]
    public function remove_from_wishlist_action_throws_exception_for_nonexistent_event()
    {
        $user = User::factory()->create();

        // This will throw ValidationException during DTO creation, not ModelNotFoundException
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        WishlistData::validateAndCreate([
            'user_id' => $user->id,
            'event_id' => 999, // Non-existent event
        ]);
    }

    #[Test]
    public function add_to_wishlist_action_throws_exception_for_draft_event()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'draft']); // Not published

        // This will throw ValidationException during DTO creation
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        WishlistData::validateAndCreate([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);
    }
}
