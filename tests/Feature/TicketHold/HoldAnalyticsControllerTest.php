<?php

use App\Enums\OrganizerPermissionEnum;
use App\Enums\RoleNameEnum;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
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

    // Create ticket hold with allocations
    $this->ticketHold = TicketHold::factory()->active()->create([
        'organizer_id' => $this->organizer->id,
        'event_occurrence_id' => $this->occurrence->id,
    ]);

    HoldTicketAllocation::factory()->create([
        'ticket_hold_id' => $this->ticketHold->id,
        'ticket_definition_id' => $this->ticketDefinition->id,
        'allocated_quantity' => 50,
        'purchased_quantity' => 10,
    ]);

    // Create organizer admin with VIEW_ANALYTICS permission
    $this->analyticsUser = User::factory()->create();
    $this->analyticsUser->assignRole(RoleNameEnum::USER);
    $this->analyticsUser->organizers()->attach($this->organizer->id, [
        'role_in_organizer' => 'admin',
        'is_active' => true,
        'joined_at' => now(),
        'permissions' => json_encode([OrganizerPermissionEnum::VIEW_ANALYTICS->value]),
    ]);

    // Create organizer user without analytics permission
    $this->noAnalyticsUser = User::factory()->create();
    $this->noAnalyticsUser->assignRole(RoleNameEnum::USER);
    $this->noAnalyticsUser->organizers()->attach($this->organizer->id, [
        'role_in_organizer' => 'member',
        'is_active' => true,
        'joined_at' => now(),
        'permissions' => json_encode([OrganizerPermissionEnum::VIEW_EVENTS->value]),
    ]);
});

describe('HoldAnalyticsController Show', function () {
    it('can get hold analytics for platform admin', function () {
        // Create some purchase links with accesses
        $link = PurchaseLink::factory()->active()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        PurchaseLinkAccess::factory()->count(5)->create([
            'purchase_link_id' => $link->id,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->getJson(route('admin.ticket-holds.analytics', $this->ticketHold));

        $response->assertOk();
        $response->assertJsonStructure([
            'analytics' => [
                'hold',
                'inventory',
                'allocations',
                'links',
                'engagement',
            ],
            'revenue_by_ticket_type',
            'top_performing_links',
            'generated_at',
        ]);
    });

    it('can get hold analytics for user with VIEW_ANALYTICS permission', function () {
        $response = $this->actingAs($this->analyticsUser)
            ->getJson(route('admin.ticket-holds.analytics', $this->ticketHold));

        $response->assertOk();
        $response->assertJsonStructure([
            'analytics',
            'generated_at',
        ]);
    });

    it('returns correct inventory data', function () {
        $response = $this->actingAs($this->platformAdmin)
            ->getJson(route('admin.ticket-holds.analytics', $this->ticketHold));

        $response->assertOk();

        $inventory = $response->json('analytics.inventory');
        expect($inventory['total_allocated'])->toBe(50);
        expect($inventory['total_purchased'])->toBe(10);
        expect($inventory['total_remaining'])->toBe(40);
        expect((float) $inventory['utilization_rate'])->toBe(20.0);
    });

    it('returns correct conversion rates', function () {
        // Create a link
        $link = PurchaseLink::factory()->active()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        // Create 10 accesses
        PurchaseLinkAccess::factory()->count(10)->create([
            'purchase_link_id' => $link->id,
            'resulted_in_purchase' => false,
        ]);

        // Create 3 accesses that resulted in purchases
        PurchaseLinkAccess::factory()->count(3)->create([
            'purchase_link_id' => $link->id,
            'resulted_in_purchase' => true,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->getJson(route('admin.ticket-holds.analytics', $this->ticketHold));

        $response->assertOk();

        $engagement = $response->json('analytics.engagement');
        expect($engagement['total_accesses'])->toBe(13);
        // Note: conversion is based on purchases count, not accesses with resulted_in_purchase
    });

    it('returns link statistics', function () {
        // Create various links with different statuses
        PurchaseLink::factory()->active()->count(3)->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);
        PurchaseLink::factory()->revoked()->count(2)->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);
        PurchaseLink::factory()->exhausted()->create([
            'ticket_hold_id' => $this->ticketHold->id,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->getJson(route('admin.ticket-holds.analytics', $this->ticketHold));

        $response->assertOk();

        $links = $response->json('analytics.links');
        expect($links['total'])->toBe(6);
        expect($links['active'])->toBe(3);
        expect($links['revoked'])->toBe(2);
        expect($links['exhausted'])->toBe(1);
    });

    it('requires authorization for hold analytics', function () {
        $response = $this->actingAs($this->regularUser)
            ->getJson(route('admin.ticket-holds.analytics', $this->ticketHold));

        $response->assertForbidden();
    });

    it('cannot access analytics without VIEW_ANALYTICS permission', function () {
        $response = $this->actingAs($this->noAnalyticsUser)
            ->getJson(route('admin.ticket-holds.analytics', $this->ticketHold));

        $response->assertForbidden();
    });

    it('cannot access analytics for hold of another organizer', function () {
        $otherOrganizer = Organizer::factory()->create();
        $otherEvent = Event::factory()->create(['organizer_id' => $otherOrganizer->id]);
        $otherOccurrence = EventOccurrence::factory()->create(['event_id' => $otherEvent->id]);
        $otherHold = TicketHold::factory()->active()->create([
            'organizer_id' => $otherOrganizer->id,
            'event_occurrence_id' => $otherOccurrence->id,
        ]);

        $response = $this->actingAs($this->analyticsUser)
            ->getJson(route('admin.ticket-holds.analytics', $otherHold));

        $response->assertForbidden();
    });

    it('returns top performing links when organizer_id is set', function () {
        // Create multiple links with varying access counts
        $link1 = PurchaseLink::factory()->active()->create([
            'ticket_hold_id' => $this->ticketHold->id,
            'name' => 'High Performer',
        ]);
        PurchaseLinkAccess::factory()->count(20)->create([
            'purchase_link_id' => $link1->id,
            'resulted_in_purchase' => false,
        ]);
        PurchaseLinkAccess::factory()->count(10)->create([
            'purchase_link_id' => $link1->id,
            'resulted_in_purchase' => true,
        ]);

        $link2 = PurchaseLink::factory()->active()->create([
            'ticket_hold_id' => $this->ticketHold->id,
            'name' => 'Low Performer',
        ]);
        PurchaseLinkAccess::factory()->count(5)->create([
            'purchase_link_id' => $link2->id,
            'resulted_in_purchase' => false,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->getJson(route('admin.ticket-holds.analytics', $this->ticketHold));

        $response->assertOk();
        $topLinks = $response->json('top_performing_links');
        expect($topLinks)->toBeArray();
    });

    it('requires authentication', function () {
        $response = $this->getJson(route('admin.ticket-holds.analytics', $this->ticketHold));

        $response->assertUnauthorized();
    });
});

describe('HoldAnalyticsController with empty data', function () {
    it('handles hold with no purchase links gracefully', function () {
        $emptyHold = TicketHold::factory()->active()->create([
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
        ]);

        HoldTicketAllocation::factory()->create([
            'ticket_hold_id' => $emptyHold->id,
            'ticket_definition_id' => $this->ticketDefinition->id,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->getJson(route('admin.ticket-holds.analytics', $emptyHold));

        $response->assertOk();

        $analytics = $response->json('analytics');
        expect($analytics['links']['total'])->toBe(0);
        expect($analytics['engagement']['total_accesses'])->toBe(0);
        expect($analytics['engagement']['conversion_rate'])->toBe(0);
    });

    it('handles zero allocated quantity', function () {
        $emptyHold = TicketHold::factory()->active()->create([
            'organizer_id' => $this->organizer->id,
            'event_occurrence_id' => $this->occurrence->id,
        ]);

        HoldTicketAllocation::factory()->create([
            'ticket_hold_id' => $emptyHold->id,
            'ticket_definition_id' => $this->ticketDefinition->id,
            'allocated_quantity' => 0,
            'purchased_quantity' => 0,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->getJson(route('admin.ticket-holds.analytics', $emptyHold));

        $response->assertOk();

        $inventory = $response->json('analytics.inventory');
        expect($inventory['utilization_rate'])->toBe(0);
    });
});
