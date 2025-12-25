<?php

use App\Enums\OrganizerPermissionEnum;
use App\Enums\RoleNameEnum;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\Enums\HoldStatusEnum;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
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

describe('TicketHoldController Index', function () {
    it('can list ticket holds for platform admin', function () {
        $hold = TicketHold::factory()->create([
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->get(route('admin.ticket-holds.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TicketHolds/Index')
            ->has('ticketHolds')
            ->has('organizers')
            ->has('occurrences')
            ->has('statusOptions')
        );
    });

    it('can list ticket holds for organizer admin scoped to their organizer', function () {
        // Create a hold for this organizer
        $ownHold = TicketHold::factory()->create([
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
        ]);

        // Create another organizer with a hold the user should NOT see
        $otherOrganizer = Organizer::factory()->create();
        $otherEvent = Event::factory()->create(['organizer_id' => $otherOrganizer->id]);
        $otherOccurrence = EventOccurrence::factory()->create([
            'event_id' => $otherEvent->id,
            'start_at' => now()->addDays(7),
        ]);
        $otherHold = TicketHold::factory()->create([
            'organizer_id' => $otherOrganizer->id,
            'event_occurrence_id' => $otherOccurrence->id,
        ]);

        $response = $this->actingAs($this->organizerAdmin)
            ->get(route('admin.ticket-holds.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TicketHolds/Index')
            ->has('ticketHolds.data', 1)
            ->where('ticketHolds.data.0.id', $ownHold->id)
        );
    });

    it('cannot list ticket holds for unauthorized user', function () {
        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.ticket-holds.index'));

        $response->assertForbidden();
    });

    it('redirects guest to login', function () {
        $response = $this->get(route('admin.ticket-holds.index'));

        $response->assertRedirect(route('login'));
    });
});

describe('TicketHoldController Create', function () {
    it('can access create form for platform admin', function () {
        $response = $this->actingAs($this->platformAdmin)
            ->get(route('admin.ticket-holds.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TicketHolds/Create')
            ->has('organizers')
            ->has('eventOccurrences')
            ->has('pricingModes')
        );
    });

    it('can access create form for organizer admin', function () {
        $response = $this->actingAs($this->organizerAdmin)
            ->get(route('admin.ticket-holds.create'));

        $response->assertOk();
    });

    it('cannot access create form for unauthorized user', function () {
        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.ticket-holds.create'));

        $response->assertForbidden();
    });
});

describe('TicketHoldController Store', function () {
    it('can create a ticket hold with allocations', function () {
        $holdData = [
            'event_occurrence_id' => $this->occurrence->id,
            'organizer_id' => $this->organizer->id,
            'name' => 'VIP Hold Test',
            'description' => 'Test description',
            'internal_notes' => 'Internal notes here',
            'expires_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'allocations' => [
                [
                    'ticket_definition_id' => $this->ticketDefinition->id,
                    'allocated_quantity' => 10,
                    'pricing_mode' => PricingModeEnum::ORIGINAL->value,
                    'custom_price' => null,
                    'discount_percentage' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->platformAdmin)
            ->post(route('admin.ticket-holds.store'), $holdData);

        $response->assertRedirect(route('admin.ticket-holds.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_holds', [
            'name' => 'VIP Hold Test',
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
        ]);

        $hold = TicketHold::where('name', 'VIP Hold Test')->first();
        expect($hold->allocations)->toHaveCount(1);
        expect($hold->allocations->first()->allocated_quantity)->toBe(10);
    });

    it('validates required fields when creating', function () {
        $response = $this->actingAs($this->platformAdmin)
            ->post(route('admin.ticket-holds.store'), []);

        $response->assertSessionHasErrors([
            'event_occurrence_id',
            'name',
            'allocations',
        ]);
    });

    it('validates allocations structure', function () {
        $holdData = [
            'event_occurrence_id' => $this->occurrence->id,
            'name' => 'Test Hold',
            'allocations' => [
                [
                    // Missing required fields
                ],
            ],
        ];

        $response = $this->actingAs($this->platformAdmin)
            ->post(route('admin.ticket-holds.store'), $holdData);

        $response->assertSessionHasErrors([
            'allocations.0.ticket_definition_id',
            'allocations.0.allocated_quantity',
            'allocations.0.pricing_mode',
        ]);
    });

    it('cannot create hold for organizer without access', function () {
        $otherOrganizer = Organizer::factory()->create();

        $holdData = [
            'event_occurrence_id' => $this->occurrence->id,
            'organizer_id' => $otherOrganizer->id, // Organizer user doesn't have access
            'name' => 'Unauthorized Hold',
            'allocations' => [
                [
                    'ticket_definition_id' => $this->ticketDefinition->id,
                    'allocated_quantity' => 10,
                    'pricing_mode' => PricingModeEnum::ORIGINAL->value,
                ],
            ],
        ];

        $response = $this->actingAs($this->organizerAdmin)
            ->post(route('admin.ticket-holds.store'), $holdData);

        $response->assertSessionHasErrors(['organizer_id']);
    });
});

describe('TicketHoldController Show', function () {
    it('can show a ticket hold for platform admin', function () {
        $hold = TicketHold::factory()->create([
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
        ]);

        HoldTicketAllocation::factory()->create([
            'ticket_hold_id' => $hold->id,
            'ticket_definition_id' => $this->ticketDefinition->id,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->get(route('admin.ticket-holds.show', $hold));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TicketHolds/Show')
            ->has('ticketHold')
            ->has('analytics')
        );
    });

    it('can show a ticket hold for organizer admin with access', function () {
        $hold = TicketHold::factory()->create([
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
        ]);

        $response = $this->actingAs($this->organizerAdmin)
            ->get(route('admin.ticket-holds.show', $hold));

        $response->assertOk();
    });

    it('cannot show a ticket hold for organizer admin without access', function () {
        $otherOrganizer = Organizer::factory()->create();
        $otherEvent = Event::factory()->create(['organizer_id' => $otherOrganizer->id]);
        $otherOccurrence = EventOccurrence::factory()->create(['event_id' => $otherEvent->id]);

        $hold = TicketHold::factory()->create([
            'organizer_id' => $otherOrganizer->id,
            'event_occurrence_id' => $otherOccurrence->id,
        ]);

        $response = $this->actingAs($this->organizerAdmin)
            ->get(route('admin.ticket-holds.show', $hold));

        $response->assertForbidden();
    });
});

describe('TicketHoldController Edit', function () {
    it('can access edit form for platform admin', function () {
        $hold = TicketHold::factory()->create([
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
        ]);

        HoldTicketAllocation::factory()->create([
            'ticket_hold_id' => $hold->id,
            'ticket_definition_id' => $this->ticketDefinition->id,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->get(route('admin.ticket-holds.edit', $hold));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TicketHolds/Edit')
            ->has('ticketHold')
            ->has('organizers')
            ->has('pricingModes')
            ->has('ticketDefinitions')
        );
    });
});

describe('TicketHoldController Update', function () {
    it('can update a ticket hold', function () {
        $hold = TicketHold::factory()->create([
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
            'name' => 'Original Name',
        ]);

        HoldTicketAllocation::factory()->create([
            'ticket_hold_id' => $hold->id,
            'ticket_definition_id' => $this->ticketDefinition->id,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ];

        $response = $this->actingAs($this->platformAdmin)
            ->put(route('admin.ticket-holds.update', $hold), $updateData);

        $response->assertRedirect(route('admin.ticket-holds.index'));
        $response->assertSessionHas('success');

        $hold->refresh();
        expect($hold->name)->toBe('Updated Name');
        expect($hold->description)->toBe('Updated description');
    });

    it('cannot update a hold without permission', function () {
        $otherOrganizer = Organizer::factory()->create();
        $otherEvent = Event::factory()->create(['organizer_id' => $otherOrganizer->id]);
        $otherOccurrence = EventOccurrence::factory()->create(['event_id' => $otherEvent->id]);

        $hold = TicketHold::factory()->create([
            'organizer_id' => $otherOrganizer->id,
            'event_occurrence_id' => $otherOccurrence->id,
        ]);

        $response = $this->actingAs($this->organizerAdmin)
            ->put(route('admin.ticket-holds.update', $hold), [
                'name' => 'Hacked Name',
            ]);

        $response->assertForbidden();
    });
});

describe('TicketHoldController Release', function () {
    it('can release a ticket hold', function () {
        $hold = TicketHold::factory()->active()->create([
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->post(route('admin.ticket-holds.release', $hold));

        $response->assertRedirect(route('admin.ticket-holds.show', $hold));
        $response->assertSessionHas('success');

        $hold->refresh();
        expect($hold->status)->toBe(HoldStatusEnum::RELEASED);
        expect($hold->released_at)->not->toBeNull();
        expect($hold->released_by)->toBe($this->platformAdmin->id);
    });

    it('organizer admin can release their own hold', function () {
        $hold = TicketHold::factory()->active()->create([
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
        ]);

        $response = $this->actingAs($this->organizerAdmin)
            ->post(route('admin.ticket-holds.release', $hold));

        $response->assertRedirect(route('admin.ticket-holds.show', $hold));
        $hold->refresh();
        expect($hold->status)->toBe(HoldStatusEnum::RELEASED);
    });

    it('cannot release a hold without permission', function () {
        $otherOrganizer = Organizer::factory()->create();
        $otherEvent = Event::factory()->create(['organizer_id' => $otherOrganizer->id]);
        $otherOccurrence = EventOccurrence::factory()->create(['event_id' => $otherEvent->id]);

        $hold = TicketHold::factory()->active()->create([
            'organizer_id' => $otherOrganizer->id,
            'event_occurrence_id' => $otherOccurrence->id,
        ]);

        $response = $this->actingAs($this->organizerAdmin)
            ->post(route('admin.ticket-holds.release', $hold));

        $response->assertForbidden();
    });
});

describe('TicketHoldController Delete', function () {
    it('can delete a ticket hold', function () {
        $hold = TicketHold::factory()->create([
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->delete(route('admin.ticket-holds.destroy', $hold));

        $response->assertRedirect(route('admin.ticket-holds.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('ticket_holds', ['id' => $hold->id]);
    });
});

describe('TicketHoldController AvailableTickets', function () {
    it('can get available tickets for an occurrence', function () {
        $response = $this->actingAs($this->platformAdmin)
            ->getJson(route('admin.api.ticket-holds.available-tickets', $this->occurrence));

        $response->assertOk();
        $response->assertJsonStructure([
            'ticket_definitions',
            'occurrence',
        ]);
    });

    it('cannot get available tickets without permission', function () {
        $response = $this->actingAs($this->regularUser)
            ->getJson(route('admin.api.ticket-holds.available-tickets', $this->occurrence));

        $response->assertForbidden();
    });
});
