<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventWishlistTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function event_can_have_users_who_wishlisted_it()
    {
        $event = Event::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Add users to event's wishlist
        $event->wishlistedByUsers()->attach([$user1->id, $user2->id]);

        $this->assertCount(2, $event->wishlistedByUsers);
        $this->assertTrue($event->wishlistedByUsers->contains($user1));
        $this->assertTrue($event->wishlistedByUsers->contains($user2));
    }

    #[Test]
    public function event_can_check_if_wishlisted_by_user()
    {
        $event = Event::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $event->wishlistedByUsers()->attach($user1->id);

        $this->assertTrue($event->isWishlistedBy($user1));
        $this->assertFalse($event->isWishlistedBy($user2));
    }

    #[Test]
    public function event_can_get_wishlist_count()
    {
        $event = Event::factory()->create();
        $users = User::factory()->count(5)->create();

        $event->wishlistedByUsers()->attach($users->pluck('id'));

        $this->assertEquals(5, $event->getWishlistCount());
    }

    #[Test]
    public function event_wishlist_count_is_zero_when_no_users()
    {
        $event = Event::factory()->create();

        $this->assertEquals(0, $event->getWishlistCount());
    }

    #[Test]
    public function event_can_check_if_wishlisted_by_user_id()
    {
        $event = Event::factory()->create();
        $user = User::factory()->create();

        $event->wishlistedByUsers()->attach($user->id);

        $this->assertTrue($event->isWishlistedBy($user->id));
        $this->assertFalse($event->isWishlistedBy(999)); // Non-existent user ID
    }
}
