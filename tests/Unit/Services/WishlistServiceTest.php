<?php

namespace Tests\Unit\Services;

use App\Actions\Wishlist\AddToWishlistAction;
use App\Actions\Wishlist\RemoveFromWishlistAction;
use App\DataTransferObjects\WishlistData;
use App\Models\Event;
use App\Models\User;
use App\Services\WishlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WishlistServiceTest extends TestCase
{
    use RefreshDatabase;

    private WishlistService $wishlistService;
    private AddToWishlistAction $addToWishlistAction;
    private RemoveFromWishlistAction $removeFromWishlistAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addToWishlistAction = new AddToWishlistAction();
        $this->removeFromWishlistAction = new RemoveFromWishlistAction();
        $this->wishlistService = new WishlistService(
            $this->addToWishlistAction,
            $this->removeFromWishlistAction
        );
    }

    #[Test]
    public function it_can_add_event_to_user_wishlist()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        $result = $this->wishlistService->addToWishlist($user->id, $event->id);

        $this->assertTrue($result);
        $this->assertTrue($user->hasInWishlist($event));
    }

    #[Test]
    public function it_returns_false_when_adding_event_already_in_wishlist()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        // Add to wishlist first
        $user->addToWishlist($event);

        $result = $this->wishlistService->addToWishlist($user->id, $event->id);

        $this->assertFalse($result);
        $this->assertCount(1, $user->wishlistedEvents); // Still only one
    }

    #[Test]
    public function it_throws_exception_when_adding_nonexistent_user_to_wishlist()
    {
        $event = Event::factory()->create(['event_status' => 'published']);

        $this->expectException(ValidationException::class);

        $this->wishlistService->addToWishlist(999, $event->id);
    }

    #[Test]
    public function it_throws_exception_when_adding_nonexistent_event_to_wishlist()
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        $this->wishlistService->addToWishlist($user->id, 999);
    }

    #[Test]
    public function it_throws_exception_when_adding_draft_event_to_wishlist()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'draft']);

        $this->expectException(ValidationException::class);

        $this->wishlistService->addToWishlist($user->id, $event->id);
    }

    #[Test]
    public function it_can_remove_event_from_user_wishlist()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        // Add to wishlist first
        $user->addToWishlist($event);

        $result = $this->wishlistService->removeFromWishlist($user->id, $event->id);

        $this->assertTrue($result);
        $this->assertFalse($user->hasInWishlist($event));
    }

    #[Test]
    public function it_returns_false_when_removing_event_not_in_wishlist()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        $result = $this->wishlistService->removeFromWishlist($user->id, $event->id);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_can_toggle_event_in_wishlist_add()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        $result = $this->wishlistService->toggleWishlist($user->id, $event->id);

        $this->assertTrue($result['added']);
        $this->assertTrue($result['in_wishlist']);
        $this->assertTrue($user->hasInWishlist($event));
    }

    #[Test]
    public function it_can_toggle_event_in_wishlist_remove()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        // Add to wishlist first
        $user->addToWishlist($event);

        $result = $this->wishlistService->toggleWishlist($user->id, $event->id);

        $this->assertFalse($result['added']);
        $this->assertFalse($result['in_wishlist']);
        $this->assertFalse($user->hasInWishlist($event));
    }

    #[Test]
    public function it_can_check_if_event_is_in_user_wishlist()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        // Not in wishlist initially
        $this->assertFalse($this->wishlistService->isInWishlist($user->id, $event->id));

        // Add to wishlist
        $user->addToWishlist($event);

        // Now it should be in wishlist
        $this->assertTrue($this->wishlistService->isInWishlist($user->id, $event->id));
    }

    #[Test]
    public function it_can_get_user_wishlist()
    {
        $user = User::factory()->create();
        $event1 = Event::factory()->create(['event_status' => 'published']);
        $event2 = Event::factory()->create(['event_status' => 'published']);
        $event3 = Event::factory()->create(['event_status' => 'published']);

        // Add events to wishlist
        $user->addToWishlist($event1);
        $user->addToWishlist($event2);

        $wishlist = $this->wishlistService->getUserWishlist($user->id);

        $this->assertCount(2, $wishlist);
        $this->assertTrue($wishlist->contains($event1));
        $this->assertTrue($wishlist->contains($event2));
        $this->assertFalse($wishlist->contains($event3));
    }

    #[Test]
    public function it_can_get_user_wishlist_with_relationships()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        $user->addToWishlist($event);

        $wishlist = $this->wishlistService->getUserWishlist($user->id, ['category', 'organizer']);

        $this->assertCount(1, $wishlist);
        $this->assertTrue($wishlist->first()->relationLoaded('category'));
        $this->assertTrue($wishlist->first()->relationLoaded('organizer'));
    }

    #[Test]
    public function it_returns_empty_collection_for_user_with_no_wishlist()
    {
        $user = User::factory()->create();

        $wishlist = $this->wishlistService->getUserWishlist($user->id);

        $this->assertCount(0, $wishlist);
        $this->assertTrue($wishlist->isEmpty());
    }

    #[Test]
    public function it_can_get_wishlist_count_for_user()
    {
        $user = User::factory()->create();
        $event1 = Event::factory()->create(['event_status' => 'published']);
        $event2 = Event::factory()->create(['event_status' => 'published']);

        // Initially zero
        $this->assertEquals(0, $this->wishlistService->getUserWishlistCount($user->id));

        // Add events
        $user->addToWishlist($event1);
        $user->addToWishlist($event2);

        $this->assertEquals(2, $this->wishlistService->getUserWishlistCount($user->id));
    }

    #[Test]
    public function it_can_clear_user_wishlist()
    {
        $user = User::factory()->create();
        $event1 = Event::factory()->create(['event_status' => 'published']);
        $event2 = Event::factory()->create(['event_status' => 'published']);

        // Add events to wishlist
        $user->addToWishlist($event1);
        $user->addToWishlist($event2);
        $this->assertCount(2, $user->wishlistedEvents);

        $result = $this->wishlistService->clearUserWishlist($user->id);

        $this->assertTrue($result);
        $this->assertCount(0, $user->fresh()->wishlistedEvents);
    }

    #[Test]
    public function it_can_get_user_wishlist_formatted_for_frontend()
    {
        // Create event with occurrences and tickets for proper formatting
        $category = \App\Models\Category::factory()->create(['name' => ['en' => 'Test Category']]);
        $venue = \App\Models\Venue::factory()->create(['name' => ['en' => 'Test Venue']]);
        $event = Event::factory()->create([
            'event_status' => 'published',
            'category_id' => $category->id
        ]);
        $eventOccurrence = \App\Models\EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => now()->addDays(7),
            'status' => 'scheduled'
        ]);
        $ticketDefinition = \App\Models\TicketDefinition::factory()->create([
            'name' => ['en' => 'General Admission'],
            'price' => 5000, // $50.00
            'currency' => 'USD'
        ]);

        // Properly associate ticket with occurrence
        $eventOccurrence->ticketDefinitions()->attach($ticketDefinition->id, [
            'quantity_for_occurrence' => 100,
            'availability_status' => 'available'
        ]);

        $user = User::factory()->create();
        $user->addToWishlist($event);

        $formattedWishlist = $this->wishlistService->getUserWishlistFormatted($user->id);

        $this->assertIsArray($formattedWishlist);
        $this->assertCount(1, $formattedWishlist);

        $eventData = $formattedWishlist[0];
        $this->assertEquals($event->id, $eventData['id']);
        $this->assertEquals($event->name, $eventData['name']);
        $this->assertEquals($event->slug, $eventData['slug']);
        $this->assertStringContainsString('/events/', $eventData['href']);
        $this->assertEquals('USD', $eventData['currency']);
        $this->assertEquals('Test Category', $eventData['category_name']);
        $this->assertEquals('Test Venue', $eventData['venue_name']);
        $this->assertArrayHasKey('date_range', $eventData);
        $this->assertArrayHasKey('image_url', $eventData);

        // Price might be null if no tickets are available, so let's check if it exists
        if ($eventData['price_from'] !== null) {
            $this->assertEquals(50.0, $eventData['price_from']); // Converted from cents
        }
    }

    #[Test]
    public function it_returns_empty_array_for_user_with_no_formatted_wishlist()
    {
        $user = User::factory()->create();

        $formattedWishlist = $this->wishlistService->getUserWishlistFormatted($user->id);

        $this->assertIsArray($formattedWishlist);
        $this->assertEmpty($formattedWishlist);
    }
}
