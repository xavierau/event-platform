<?php

use App\Enums\OrganizerPermissionEnum;
use App\Enums\RoleNameEnum;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
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

    // Create platform admin
    $this->platformAdmin = User::factory()->create();
    $this->platformAdmin->assignRole(RoleNameEnum::ADMIN);

    // Create regular user
    $this->regularUser = User::factory()->create();
    $this->regularUser->assignRole(RoleNameEnum::USER);

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

    // Create ticket hold
    $this->ticketHold = TicketHold::factory()->active()->create([
        'organizer_id' => $this->organizer->id,
        'event_occurrence_id' => $this->occurrence->id,
    ]);

    HoldTicketAllocation::factory()->create([
        'ticket_hold_id' => $this->ticketHold->id,
        'ticket_definition_id' => $this->ticketDefinition->id,
        'allocated_quantity' => 20,
    ]);

    // Create organizer admin user with MANAGE_BOOKINGS permission
    $this->organizerAdmin = User::factory()->create();
    $this->organizerAdmin->assignRole(RoleNameEnum::USER);
    $this->organizerAdmin->organizers()->attach($this->organizer->id, [
        'role_in_organizer' => 'admin',
        'is_active' => true,
        'joined_at' => now(),
        'permissions' => json_encode([OrganizerPermissionEnum::MANAGE_BOOKINGS->value]),
    ]);
});

describe('PurchaseLinkController Store', function () {
    it('can create a purchase link', function () {
        $linkData = [
            'name' => 'VIP Access Link',
            'quantity_mode' => QuantityModeEnum::MAXIMUM->value,
            'quantity_limit' => 5,
            'expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'notes' => 'For VIP guests',
        ];

        $response = $this->actingAs($this->platformAdmin)
            ->post(route('admin.ticket-holds.purchase-links.store', $this->ticketHold), $linkData);

        $response->assertRedirect(route('admin.ticket-holds.show', $this->ticketHold));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_links', [
            'ticket_hold_id' => $this->ticketHold->id,
            'name' => 'VIP Access Link',
            'quantity_mode' => QuantityModeEnum::MAXIMUM->value,
            'quantity_limit' => 5,
        ]);
    });

    it('can create anonymous purchase link', function () {
        $linkData = [
            'name' => 'Public Link',
            'assigned_user_id' => null,
            'quantity_mode' => QuantityModeEnum::UNLIMITED->value,
        ];

        $response = $this->actingAs($this->platformAdmin)
            ->post(route('admin.ticket-holds.purchase-links.store', $this->ticketHold), $linkData);

        $response->assertRedirect(route('admin.ticket-holds.show', $this->ticketHold));

        $link = PurchaseLink::where('ticket_hold_id', $this->ticketHold->id)->latest()->first();
        expect($link->is_anonymous)->toBeTrue();
        expect($link->quantity_mode)->toBe(QuantityModeEnum::UNLIMITED);
    });

    it('can create user-assigned purchase link', function () {
        $assignedUser = User::factory()->create();

        $linkData = [
            'name' => 'Personal Invitation',
            'assigned_user_id' => $assignedUser->id,
            'quantity_mode' => QuantityModeEnum::FIXED->value,
            'quantity_limit' => 2,
        ];

        $response = $this->actingAs($this->platformAdmin)
            ->post(route('admin.ticket-holds.purchase-links.store', $this->ticketHold), $linkData);

        $response->assertRedirect(route('admin.ticket-holds.show', $this->ticketHold));

        $link = PurchaseLink::where('ticket_hold_id', $this->ticketHold->id)->latest()->first();
        expect($link->assigned_user_id)->toBe($assignedUser->id);
        expect($link->is_anonymous)->toBeFalse();
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->platformAdmin)
            ->post(route('admin.ticket-holds.purchase-links.store', $this->ticketHold), []);

        $response->assertSessionHasErrors(['quantity_mode']);
    });

    it('validates quantity_limit required for non-unlimited modes', function () {
        $linkData = [
            'quantity_mode' => QuantityModeEnum::MAXIMUM->value,
            // quantity_limit missing
        ];

        $response = $this->actingAs($this->platformAdmin)
            ->post(route('admin.ticket-holds.purchase-links.store', $this->ticketHold), $linkData);

        $response->assertSessionHasErrors(['quantity_limit']);
    });

    it('organizer admin can create link for their hold', function () {
        $linkData = [
            'name' => 'Organizer Link',
            'quantity_mode' => QuantityModeEnum::MAXIMUM->value,
            'quantity_limit' => 3,
        ];

        $response = $this->actingAs($this->organizerAdmin)
            ->post(route('admin.ticket-holds.purchase-links.store', $this->ticketHold), $linkData);

        $response->assertRedirect(route('admin.ticket-holds.show', $this->ticketHold));
        $response->assertSessionHas('success');
    });

    it('cannot create link for hold without access', function () {
        $otherOrganizer = Organizer::factory()->create();
        $otherEvent = Event::factory()->create(['organizer_id' => $otherOrganizer->id]);
        $otherOccurrence = EventOccurrence::factory()->create(['event_id' => $otherEvent->id]);
        $otherHold = TicketHold::factory()->active()->create([
            'organizer_id' => $otherOrganizer->id,
            'event_occurrence_id' => $otherOccurrence->id,
        ]);

        $linkData = [
            'quantity_mode' => QuantityModeEnum::MAXIMUM->value,
            'quantity_limit' => 5,
        ];

        $response = $this->actingAs($this->organizerAdmin)
            ->post(route('admin.ticket-holds.purchase-links.store', $otherHold), $linkData);

        $response->assertForbidden();
    });
});

describe('PurchaseLinkController Show', function () {
    it('can show a purchase link for platform admin', function () {
        $link = PurchaseLink::factory()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->get(route('admin.purchase-links.show', $link));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TicketHolds/PurchaseLinks/Show', false)
            ->has('purchaseLink')
            ->has('analytics')
        );
    });

    it('can show a purchase link for organizer admin with access', function () {
        $link = PurchaseLink::factory()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->actingAs($this->organizerAdmin)
            ->get(route('admin.purchase-links.show', $link));

        $response->assertOk();
    });

    it('cannot show a purchase link without access', function () {
        $otherOrganizer = Organizer::factory()->create();
        $otherEvent = Event::factory()->create(['organizer_id' => $otherOrganizer->id]);
        $otherOccurrence = EventOccurrence::factory()->create(['event_id' => $otherEvent->id]);
        $otherHold = TicketHold::factory()->active()->create([
            'organizer_id' => $otherOrganizer->id,
            'event_occurrence_id' => $otherOccurrence->id,
        ]);
        $link = PurchaseLink::factory()->create([
            'ticket_hold_id' => $otherHold->id,
        ]);

        $response = $this->actingAs($this->organizerAdmin)
            ->get(route('admin.purchase-links.show', $link));

        $response->assertForbidden();
    });
});

describe('PurchaseLinkController Update', function () {
    it('can update a purchase link', function () {
        $link = PurchaseLink::factory()->active()->create([
            'ticket_hold_id' => $this->ticketHold->id,
            'name' => 'Original Name',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'notes' => 'Updated notes',
        ];

        $response = $this->actingAs($this->platformAdmin)
            ->put(route('admin.purchase-links.update', $link), $updateData);

        $response->assertRedirect(route('admin.purchase-links.show', $link));
        $response->assertSessionHas('success');

        $link->refresh();
        expect($link->name)->toBe('Updated Name');
        expect($link->notes)->toBe('Updated notes');
    });

    it('cannot update a link without permission', function () {
        $otherOrganizer = Organizer::factory()->create();
        $otherEvent = Event::factory()->create(['organizer_id' => $otherOrganizer->id]);
        $otherOccurrence = EventOccurrence::factory()->create(['event_id' => $otherEvent->id]);
        $otherHold = TicketHold::factory()->active()->create([
            'organizer_id' => $otherOrganizer->id,
            'event_occurrence_id' => $otherOccurrence->id,
        ]);
        $link = PurchaseLink::factory()->create([
            'ticket_hold_id' => $otherHold->id,
        ]);

        $response = $this->actingAs($this->organizerAdmin)
            ->put(route('admin.purchase-links.update', $link), [
                'name' => 'Hacked Name',
            ]);

        $response->assertForbidden();
    });
});

describe('PurchaseLinkController Revoke', function () {
    it('can revoke a purchase link', function () {
        $link = PurchaseLink::factory()->active()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->post(route('admin.purchase-links.revoke', $link));

        $response->assertRedirect(route('admin.purchase-links.show', $link));
        $response->assertSessionHas('success');

        $link->refresh();
        expect($link->status)->toBe(LinkStatusEnum::REVOKED);
        expect($link->revoked_at)->not->toBeNull();
        expect($link->revoked_by)->toBe($this->platformAdmin->id);
    });

    it('organizer admin can revoke link for their hold', function () {
        $link = PurchaseLink::factory()->active()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->actingAs($this->organizerAdmin)
            ->post(route('admin.purchase-links.revoke', $link));

        $response->assertRedirect(route('admin.purchase-links.show', $link));

        $link->refresh();
        expect($link->status)->toBe(LinkStatusEnum::REVOKED);
    });

    it('cannot revoke link without permission', function () {
        $otherOrganizer = Organizer::factory()->create();
        $otherEvent = Event::factory()->create(['organizer_id' => $otherOrganizer->id]);
        $otherOccurrence = EventOccurrence::factory()->create(['event_id' => $otherEvent->id]);
        $otherHold = TicketHold::factory()->active()->create([
            'organizer_id' => $otherOrganizer->id,
            'event_occurrence_id' => $otherOccurrence->id,
        ]);
        $link = PurchaseLink::factory()->active()->create([
            'ticket_hold_id' => $otherHold->id,
        ]);

        $response = $this->actingAs($this->organizerAdmin)
            ->post(route('admin.purchase-links.revoke', $link));

        $response->assertForbidden();
    });
});

describe('PurchaseLinkController Delete', function () {
    it('can delete a purchase link', function () {
        $link = PurchaseLink::factory()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->delete(route('admin.purchase-links.destroy', $link));

        $response->assertRedirect(route('admin.ticket-holds.show', $this->ticketHold));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('purchase_links', ['id' => $link->id]);
    });
});

describe('PurchaseLinkController SearchUsers', function () {
    it('can search users with authorization', function () {
        $searchUser = User::factory()->create([
            'name' => 'John Smith',
            'email' => 'john.smith@example.com',
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->postJson(route('admin.api.ticket-holds.search-users'), [
                'query' => 'John',
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'users' => [
                '*' => ['id', 'name', 'email', 'label'],
            ],
        ]);
    });

    it('validates search query minimum length', function () {
        $response = $this->actingAs($this->platformAdmin)
            ->postJson(route('admin.api.ticket-holds.search-users'), [
                'query' => 'a', // Too short
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['query']);
    });

    it('escapes SQL wildcards in user search', function () {
        // Create user with special characters
        $user = User::factory()->create([
            'name' => 'Test%User_Name',
            'email' => 'test@example.com',
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->postJson(route('admin.api.ticket-holds.search-users'), [
                'query' => 'Test%User',
            ]);

        $response->assertOk();
        // The search should work correctly with escaped wildcards
    });

    it('cannot search users without authorization', function () {
        $response = $this->actingAs($this->regularUser)
            ->postJson(route('admin.api.ticket-holds.search-users'), [
                'query' => 'John',
            ]);

        $response->assertForbidden();
    });

    it('requires authentication for user search', function () {
        $response = $this->postJson(route('admin.api.ticket-holds.search-users'), [
            'query' => 'John',
        ]);

        $response->assertUnauthorized();
    });
});
