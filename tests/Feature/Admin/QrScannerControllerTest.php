<?php

use App\Enums\RoleNameEnum;
use App\Models\Event;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => RoleNameEnum::USER->value, 'guard_name' => 'web']);
});

it('allows platform admin to access QR scanner page', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleNameEnum::ADMIN);

    $response = $this->actingAs($admin)->get(route('admin.qr-scanner.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn($page) => $page->component('Admin/QrScanner/Index'));
});

it('allows user with organizer entity membership to access QR scanner page with their events only', function () {
    // Create a user (no special role needed)
    $organizer = User::factory()->create();

    // Create an organizer entity for this user
    $organizerEntity = \App\Models\Organizer::factory()->create();
    $organizerEntity->users()->attach($organizer->id, [
        'role_in_organizer' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'invitation_accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create events - some by the organizer entity, some by others
    $organizerEvents = Event::factory(2)->create(['organizer_id' => $organizerEntity->id, 'event_status' => 'published']);
    $otherOrganizerEntity = \App\Models\Organizer::factory()->create();
    $otherEvents = Event::factory(2)->create(['organizer_id' => $otherOrganizerEntity->id, 'event_status' => 'published']);

    $response = $this->actingAs($organizer)->get(route('admin.qr-scanner.index'));

    $response->assertStatus(200);
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/QrScanner/Index')
            ->has('events', 2) // Should only see their own organizer entity's events
            ->where('events.0.id', $organizerEvents[0]->id)
            ->where('events.1.id', $organizerEvents[1]->id)
    );
});

it('denies access to regular user without organizer entity membership', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleNameEnum::USER);

    $response = $this->actingAs($user)->get(route('admin.qr-scanner.index'));

    $response->assertStatus(403);
});

it('platform admin can see all published events', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleNameEnum::ADMIN);

    // Create multiple organizer entities with events
    $organizer1 = \App\Models\Organizer::factory()->create();
    $organizer2 = \App\Models\Organizer::factory()->create();

    Event::factory(2)->create(['organizer_id' => $organizer1->id, 'event_status' => 'published']);
    Event::factory(1)->create(['organizer_id' => $organizer2->id, 'event_status' => 'published']);
    Event::factory(1)->create(['organizer_id' => $organizer1->id, 'event_status' => 'draft']); // Should not appear

    $response = $this->actingAs($admin)->get(route('admin.qr-scanner.index'));

    $response->assertStatus(200);
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/QrScanner/Index')
            ->has('events', 3) // Should see all published events
    );
});
