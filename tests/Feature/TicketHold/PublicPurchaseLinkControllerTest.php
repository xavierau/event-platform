<?php

use App\Enums\RoleNameEnum;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Bypass Vite manifest check in tests
    $this->withoutVite();
    // Create required roles
    Role::firstOrCreate(['name' => RoleNameEnum::ADMIN->value]);
    Role::firstOrCreate(['name' => RoleNameEnum::USER->value]);

    // Create organizer and related data
    $this->organizer = Organizer::factory()->create();
    $this->event = Event::factory()->create(['organizer_id' => $this->organizer->id]);
    $this->occurrence = EventOccurrence::factory()->create([
        'event_id' => $this->event->id,
        'start_at' => now()->addDays(7),
    ]);
    $this->ticketDefinition = TicketDefinition::factory()->create([
        'price' => 10000,
        'total_quantity' => 100,
    ]);

    // Attach ticket definition to the event occurrence
    $this->occurrence->ticketDefinitions()->attach($this->ticketDefinition->id);

    // Create active ticket hold with allocation
    $this->ticketHold = TicketHold::factory()->active()->neverExpires()->create([
        'organizer_id' => $this->organizer->id,
        'event_occurrence_id' => $this->occurrence->id,
    ]);

    $this->allocation = HoldTicketAllocation::factory()->create([
        'ticket_hold_id' => $this->ticketHold->id,
        'ticket_definition_id' => $this->ticketDefinition->id,
        'allocated_quantity' => 50,
        'purchased_quantity' => 0,
        'pricing_mode' => PricingModeEnum::ORIGINAL,
    ]);

    // Create regular user for testing
    $this->user = User::factory()->create();
    $this->user->assignRole(RoleNameEnum::USER);

    // Create another user
    $this->otherUser = User::factory()->create();
    $this->otherUser->assignRole(RoleNameEnum::USER);
});

describe('PublicPurchaseLinkController Show', function () {
    it('can view a valid anonymous purchase link', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
            'quantity_mode' => QuantityModeEnum::MAXIMUM,
            'quantity_limit' => 5,
        ]);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/PurchaseLink/Show', false)
            ->has('link')
            ->has('hold')
            ->has('event')
            ->has('occurrence')
            ->has('allocations')
        );
    });

    it('can view a valid anonymous link as guest', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/PurchaseLink/Show', false)
            ->where('isAuthenticated', false)
        );
    });

    it('can view a valid anonymous link as authenticated user', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/PurchaseLink/Show', false)
            ->where('isAuthenticated', true)
            ->has('user')
        );
    });

    it('cannot view expired link', function () {
        $link = PurchaseLink::factory()->expired()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/PurchaseLink/Unavailable', false)
            ->has('message')
        );
    });

    it('cannot view revoked link', function () {
        $link = PurchaseLink::factory()->revoked()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/PurchaseLink/Unavailable', false)
        );
    });

    it('returns not found for invalid link code', function () {
        $response = $this->get(route('purchase-link.show', ['code' => 'invalid-code']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/PurchaseLink/NotFound', false)
        );
    });

    it('records access when viewing link', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $this->assertDatabaseCount('purchase_link_accesses', 0);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();

        $this->assertDatabaseCount('purchase_link_accesses', 1);
        $this->assertDatabaseHas('purchase_link_accesses', [
            'purchase_link_id' => $link->id,
        ]);
    });

    it('records access with user_id when authenticated', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();

        $this->assertDatabaseHas('purchase_link_accesses', [
            'purchase_link_id' => $link->id,
            'user_id' => $this->user->id,
        ]);
    });

    it('cannot view exhausted link', function () {
        $link = PurchaseLink::factory()->exhausted()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/PurchaseLink/Unavailable', false)
        );
    });

    it('cannot view link when hold is released', function () {
        $releasedHold = TicketHold::factory()->released()->create([
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
        ]);

        HoldTicketAllocation::factory()->create([
            'ticket_hold_id' => $releasedHold->id,
            'ticket_definition_id' => $this->ticketDefinition->id,
        ]);

        $link = PurchaseLink::factory()->active()->anonymous()->create([
            'ticket_hold_id' => $releasedHold->id,
        ]);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/PurchaseLink/Unavailable', false)
        );
    });
});

describe('PublicPurchaseLinkController Show with User Assignment', function () {
    it('assigned user can view their link', function () {
        $link = PurchaseLink::factory()->active()->neverExpires()->withUser($this->user)->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/PurchaseLink/Show', false)
        );
    });

    it('other user cannot view user-assigned link', function () {
        $link = PurchaseLink::factory()->active()->neverExpires()->withUser($this->user)->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->actingAs($this->otherUser)
            ->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/PurchaseLink/Unauthorized', false)
        );
    });

    it('redirects guest to login for user-assigned link', function () {
        $link = PurchaseLink::factory()->active()->neverExpires()->withUser($this->user)->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        // The controller redirects to login with a redirect param back to the purchase link
        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    });
});

describe('PublicPurchaseLinkController Purchase', function () {
    it('can purchase with valid anonymous link', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
            'quantity_mode' => QuantityModeEnum::MAXIMUM,
            'quantity_limit' => 10,
        ]);

        // First access the link to set up the session
        $this->actingAs($this->user)
            ->get(route('purchase-link.show', ['code' => $link->code]));

        $purchaseData = [
            'items' => [
                [
                    'ticket_definition_id' => $this->ticketDefinition->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('purchase-link.purchase', ['code' => $link->code]), $purchaseData);

        // The controller redirects on success or back on error
        $response->assertRedirect();
    });

    it('requires authentication to purchase', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $purchaseData = [
            'items' => [
                [
                    'ticket_definition_id' => $this->ticketDefinition->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->post(route('purchase-link.purchase', ['code' => $link->code]), $purchaseData);

        $response->assertRedirect(route('login'));
    });

    it('validates required items', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('purchase-link.purchase', ['code' => $link->code]), []);

        $response->assertSessionHasErrors(['items']);
    });

    it('validates item structure', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $purchaseData = [
            'items' => [
                [
                    // Missing ticket_definition_id and quantity
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('purchase-link.purchase', ['code' => $link->code]), $purchaseData);

        $response->assertSessionHasErrors([
            'items.0.ticket_definition_id',
            'items.0.quantity',
        ]);
    });

    it('validates ticket definition exists', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $purchaseData = [
            'items' => [
                [
                    'ticket_definition_id' => 99999, // Non-existent
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('purchase-link.purchase', ['code' => $link->code]), $purchaseData);

        $response->assertSessionHasErrors(['items.0.ticket_definition_id']);
    });

    it('validates minimum quantity', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $purchaseData = [
            'items' => [
                [
                    'ticket_definition_id' => $this->ticketDefinition->id,
                    'quantity' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('purchase-link.purchase', ['code' => $link->code]), $purchaseData);

        $response->assertSessionHasErrors(['items.0.quantity']);
    });

    it('validates ticket belongs to hold allocation', function () {
        // Create a different ticket definition not in the hold
        $otherTicket = TicketDefinition::factory()->create();

        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $purchaseData = [
            'items' => [
                [
                    'ticket_definition_id' => $otherTicket->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('purchase-link.purchase', ['code' => $link->code]), $purchaseData);

        $response->assertSessionHasErrors();
    });

    it('cannot purchase with invalid link code', function () {
        $purchaseData = [
            'items' => [
                [
                    'ticket_definition_id' => $this->ticketDefinition->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('purchase-link.purchase', ['code' => 'invalid-code']), $purchaseData);

        // Validation fails with 'link' error when code is invalid
        $response->assertSessionHasErrors(['link']);
    });

    it('validates user assignment for user-tied link', function () {
        $link = PurchaseLink::factory()->active()->neverExpires()->withUser($this->user)->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        // First access the link
        $this->actingAs($this->user)
            ->get(route('purchase-link.show', ['code' => $link->code]));

        $purchaseData = [
            'items' => [
                [
                    'ticket_definition_id' => $this->ticketDefinition->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // The assigned user should be able to purchase
        $response = $this->actingAs($this->user)
            ->post(route('purchase-link.purchase', ['code' => $link->code]), $purchaseData);

        $response->assertRedirect();
    });
});

describe('PublicPurchaseLinkController Pricing Display', function () {
    it('displays original price for original pricing mode', function () {
        $this->allocation->update([
            'pricing_mode' => PricingModeEnum::ORIGINAL,
        ]);

        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('allocations.0.original_price')
            ->has('allocations.0.effective_price')
        );
    });

    it('displays free ticket correctly', function () {
        $this->allocation->update([
            'pricing_mode' => PricingModeEnum::FREE,
        ]);

        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('allocations.0.is_free')
        );
    });
});

describe('PublicPurchaseLinkController Quantity Modes', function () {
    it('shows unlimited availability for unlimited mode', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->unlimited()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('link.quantity_mode', QuantityModeEnum::UNLIMITED->value)
        );
    });

    it('shows remaining quantity for limited modes', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
            'quantity_mode' => QuantityModeEnum::MAXIMUM,
            'quantity_limit' => 10,
            'quantity_purchased' => 3,
        ]);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('link.remaining_quantity', 7)
        );
    });
});

describe('PublicPurchaseLinkController Rate Limiting', function () {
    beforeEach(function () {
        // Clear rate limiter cache before each test
        \Illuminate\Support\Facades\RateLimiter::clear('purchase-link-show');
        \Illuminate\Support\Facades\RateLimiter::clear('purchase-link-purchase');
    });

    it('rate limits show endpoint after 60 requests per minute', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        // Make 60 successful requests
        for ($i = 0; $i < 60; $i++) {
            $response = $this->get(route('purchase-link.show', ['code' => $link->code]));
            $response->assertStatus(200);
        }

        // The 61st request should be rate limited
        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));
        $response->assertStatus(429);
    });

    it('rate limits purchase endpoint after 10 requests per minute', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
            'quantity_mode' => QuantityModeEnum::UNLIMITED,
        ]);

        // First access the link to set up the session
        $this->actingAs($this->user)
            ->get(route('purchase-link.show', ['code' => $link->code]));

        $purchaseData = [
            'items' => [
                [
                    'ticket_definition_id' => $this->ticketDefinition->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // Make 10 requests (they may fail for business logic reasons, but not for rate limiting)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($this->user)
                ->post(route('purchase-link.purchase', ['code' => $link->code]), $purchaseData);
            $response->assertStatus(302); // Redirect (either success or back with error)
        }

        // The 11th request should be rate limited
        $response = $this->actingAs($this->user)
            ->post(route('purchase-link.purchase', ['code' => $link->code]), $purchaseData);
        $response->assertStatus(429);
    });

    it('rate limits are keyed by user id for authenticated users', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $purchaseData = [
            'items' => [
                [
                    'ticket_definition_id' => $this->ticketDefinition->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // User 1: Make 10 requests to hit rate limit
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->user)
                ->post(route('purchase-link.purchase', ['code' => $link->code]), $purchaseData);
        }

        // User 1 should be rate limited
        $response = $this->actingAs($this->user)
            ->post(route('purchase-link.purchase', ['code' => $link->code]), $purchaseData);
        $response->assertStatus(429);

        // User 2 should NOT be rate limited (different user, separate limit)
        $response = $this->actingAs($this->otherUser)
            ->post(route('purchase-link.purchase', ['code' => $link->code]), $purchaseData);
        $response->assertStatus(302); // Not rate limited
    });

    it('returns appropriate rate limit headers', function () {
        $link = PurchaseLink::factory()->active()->anonymous()->neverExpires()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->get(route('purchase-link.show', ['code' => $link->code]));

        $response->assertStatus(200);
        $response->assertHeader('X-Ratelimit-Limit');
        $response->assertHeader('X-Ratelimit-Remaining');
    });
});
