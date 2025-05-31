<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MyWishlistPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->event = Event::factory()->create(['event_status' => 'published']);
    }

    #[Test]
    public function authenticated_user_can_access_wishlist_page()
    {
        $response = $this->actingAs($this->user)
            ->get('/my-wishlist');

        $response->assertStatus(Response::HTTP_OK)
            ->assertInertia(
                fn($page) => $page
                    ->component('Public/MyWishlist')
            );
    }

    #[Test]
    public function unauthenticated_user_is_redirected_to_login()
    {
        $response = $this->get('/my-wishlist');

        $response->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('/login');
    }

    #[Test]
    public function wishlist_page_renders_correctly_with_events()
    {
        // Add event to user's wishlist
        $this->user->addToWishlist($this->event);

        $response = $this->actingAs($this->user)
            ->get('/my-wishlist');

        $response->assertStatus(Response::HTTP_OK)
            ->assertInertia(
                fn($page) => $page
                    ->component('Public/MyWishlist')
            );
    }

    #[Test]
    public function wishlist_page_renders_correctly_when_empty()
    {
        $response = $this->actingAs($this->user)
            ->get('/my-wishlist');

        $response->assertStatus(Response::HTTP_OK)
            ->assertInertia(
                fn($page) => $page
                    ->component('Public/MyWishlist')
            );
    }
}
