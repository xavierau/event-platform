<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WishlistControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Event $publishedEvent;
    private Event $draftEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->publishedEvent = Event::factory()->create(['event_status' => 'published']);
        $this->draftEvent = Event::factory()->create(['event_status' => 'draft']);
    }

    #[Test]
    public function authenticated_user_can_add_event_to_wishlist()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/wishlist', [
                'event_id' => $this->publishedEvent->id,
            ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJson([
                'success' => true,
                'message' => 'Event added to wishlist successfully',
                'data' => [
                    'in_wishlist' => true,
                ],
            ]);

        $this->assertTrue($this->user->hasInWishlist($this->publishedEvent));
    }

    #[Test]
    public function authenticated_user_cannot_add_draft_event_to_wishlist()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/wishlist', [
                'event_id' => $this->draftEvent->id,
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['event_id']);
    }

    #[Test]
    public function authenticated_user_cannot_add_nonexistent_event_to_wishlist()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/wishlist', [
                'event_id' => 999,
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['event_id']);
    }

    #[Test]
    public function adding_event_already_in_wishlist_returns_appropriate_response()
    {
        // Add to wishlist first
        $this->user->addToWishlist($this->publishedEvent);

        $response = $this->actingAs($this->user)
            ->postJson('/wishlist', [
                'event_id' => $this->publishedEvent->id,
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Event is already in your wishlist',
                'data' => [
                    'in_wishlist' => true,
                ],
            ]);
    }

    #[Test]
    public function authenticated_user_can_remove_event_from_wishlist()
    {
        // Add to wishlist first
        $this->user->addToWishlist($this->publishedEvent);

        $response = $this->actingAs($this->user)
            ->deleteJson("/wishlist/{$this->publishedEvent->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Event removed from wishlist successfully',
                'data' => [
                    'in_wishlist' => false,
                ],
            ]);

        $this->assertFalse($this->user->hasInWishlist($this->publishedEvent));
    }

    #[Test]
    public function removing_event_not_in_wishlist_returns_appropriate_response()
    {
        $response = $this->actingAs($this->user)
            ->deleteJson("/wishlist/{$this->publishedEvent->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Event was not in your wishlist',
                'data' => [
                    'in_wishlist' => false,
                ],
            ]);
    }

    #[Test]
    public function authenticated_user_can_toggle_event_in_wishlist_add()
    {
        $response = $this->actingAs($this->user)
            ->putJson("/wishlist/{$this->publishedEvent->id}/toggle");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Event added to wishlist successfully',
                'data' => [
                    'added' => true,
                    'in_wishlist' => true,
                ],
            ]);

        $this->assertTrue($this->user->hasInWishlist($this->publishedEvent));
    }

    #[Test]
    public function authenticated_user_can_toggle_event_in_wishlist_remove()
    {
        // Add to wishlist first
        $this->user->addToWishlist($this->publishedEvent);

        $response = $this->actingAs($this->user)
            ->putJson("/wishlist/{$this->publishedEvent->id}/toggle");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Event removed from wishlist successfully',
                'data' => [
                    'added' => false,
                    'in_wishlist' => false,
                ],
            ]);

        $this->assertFalse($this->user->hasInWishlist($this->publishedEvent));
    }

    #[Test]
    public function authenticated_user_can_get_their_wishlist()
    {
        $event1 = Event::factory()->create(['event_status' => 'published']);
        $event2 = Event::factory()->create(['event_status' => 'published']);

        // Add events to wishlist
        $this->user->addToWishlist($event1);
        $this->user->addToWishlist($event2);

        $response = $this->actingAs($this->user)
            ->getJson('/wishlist');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'data' => [
                    'wishlist' => [
                        [
                            'id' => $event1->id,
                            'name' => $event1->name,
                        ],
                        [
                            'id' => $event2->id,
                            'name' => $event2->name,
                        ],
                    ],
                    'count' => 2,
                ],
            ]);
    }

    #[Test]
    public function authenticated_user_can_get_empty_wishlist()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/wishlist');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'data' => [
                    'wishlist' => [],
                    'count' => 0,
                ],
            ]);
    }

    #[Test]
    public function authenticated_user_can_check_if_event_is_in_wishlist()
    {
        // Add one event to wishlist
        $this->user->addToWishlist($this->publishedEvent);

        // Check event in wishlist
        $response = $this->actingAs($this->user)
            ->getJson("/wishlist/{$this->publishedEvent->id}/check");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'data' => [
                    'in_wishlist' => true,
                ],
            ]);

        // Check event not in wishlist
        $otherEvent = Event::factory()->create(['event_status' => 'published']);
        $response = $this->actingAs($this->user)
            ->getJson("/wishlist/{$otherEvent->id}/check");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'data' => [
                    'in_wishlist' => false,
                ],
            ]);
    }

    #[Test]
    public function authenticated_user_can_clear_their_wishlist()
    {
        // Add events to wishlist
        $this->user->addToWishlist($this->publishedEvent);
        $this->user->addToWishlist(Event::factory()->create(['event_status' => 'published']));

        $response = $this->actingAs($this->user)
            ->deleteJson('/wishlist');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Wishlist cleared successfully',
                'data' => [
                    'count' => 0,
                ],
            ]);

        $this->assertCount(0, $this->user->fresh()->wishlistedEvents);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_wishlist_endpoints()
    {
        // Test all endpoints require authentication
        // JSON requests return 401, regular requests get 302 redirect
        $endpoints = [
            ['method' => 'get', 'uri' => '/wishlist'],
            ['method' => 'post', 'uri' => '/wishlist'],
            ['method' => 'delete', 'uri' => '/wishlist'],
            ['method' => 'delete', 'uri' => "/wishlist/{$this->publishedEvent->id}"],
            ['method' => 'put', 'uri' => "/wishlist/{$this->publishedEvent->id}/toggle"],
            ['method' => 'get', 'uri' => "/wishlist/{$this->publishedEvent->id}/check"],
        ];

        foreach ($endpoints as $endpoint) {
            // JSON requests return 401 Unauthorized
            $response = $this->{$endpoint['method'] . 'Json'}($endpoint['uri']);
            $response->assertStatus(Response::HTTP_UNAUTHORIZED);

            // Regular requests get redirected to login (302)
            $response = $this->{$endpoint['method']}($endpoint['uri']);
            $response->assertStatus(Response::HTTP_FOUND);
        }
    }

    #[Test]
    public function wishlist_endpoints_validate_event_exists_for_event_specific_routes()
    {
        $endpoints = [
            ['method' => 'delete', 'uri' => '/wishlist/999'],
            ['method' => 'put', 'uri' => '/wishlist/999/toggle'],
            ['method' => 'get', 'uri' => '/wishlist/999/check'],
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->actingAs($this->user)
                ->{$endpoint['method'] . 'Json'}($endpoint['uri']);

            $response->assertStatus(Response::HTTP_NOT_FOUND);
        }
    }
}
