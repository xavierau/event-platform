<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserWishlistTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_have_wishlist_events()
    {
        $user = User::factory()->create();
        $event1 = Event::factory()->create();
        $event2 = Event::factory()->create();

        // Add events to wishlist
        $user->wishlistedEvents()->attach([$event1->id, $event2->id]);

        $this->assertCount(2, $user->wishlistedEvents);
        $this->assertTrue($user->wishlistedEvents->contains($event1));
        $this->assertTrue($user->wishlistedEvents->contains($event2));
    }

    #[Test]
    public function user_can_add_event_to_wishlist()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $user->addToWishlist($event);

        $this->assertTrue($user->hasInWishlist($event));
        $this->assertCount(1, $user->wishlistedEvents);
    }

    #[Test]
    public function user_can_remove_event_from_wishlist()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        // Add to wishlist first
        $user->addToWishlist($event);
        $this->assertTrue($user->hasInWishlist($event));

        // Remove from wishlist
        $user->removeFromWishlist($event);
        $this->assertFalse($user->hasInWishlist($event));
        $this->assertCount(0, $user->wishlistedEvents);
    }

    #[Test]
    public function user_cannot_add_same_event_to_wishlist_twice()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        // Add event twice
        $user->addToWishlist($event);
        $user->addToWishlist($event);

        // Should only have one instance
        $this->assertCount(1, $user->wishlistedEvents);
    }

    #[Test]
    public function user_can_check_if_event_is_in_wishlist()
    {
        $user = User::factory()->create();
        $event1 = Event::factory()->create();
        $event2 = Event::factory()->create();

        $user->addToWishlist($event1);

        $this->assertTrue($user->hasInWishlist($event1));
        $this->assertFalse($user->hasInWishlist($event2));
    }

    #[Test]
    public function wishlist_relationship_includes_timestamps()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $user->addToWishlist($event);

        $pivotData = $user->wishlistedEvents()->where('event_id', $event->id)->first()->pivot;

        $this->assertNotNull($pivotData->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $pivotData->created_at);
    }
}
