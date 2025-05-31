<?php

use App\Models\User;
use App\Models\Event;
use App\Enums\RoleNameEnum;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create roles
    Role::create(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);
    Role::create(['name' => RoleNameEnum::ORGANIZER->value, 'guard_name' => 'web']);
    Role::create(['name' => RoleNameEnum::USER->value, 'guard_name' => 'web']);
});

it('allows platform admin to access QR scanner page', function () {
    // Create a platform admin user
    $admin = User::factory()->create();
    $admin->assignRole(RoleNameEnum::ADMIN);

    // Create some events
    $events = Event::factory()->count(3)->create([
        'event_status' => 'published'
    ]);

    // Act as the admin and visit the QR scanner page
    $response = $this->actingAs($admin)->get(route('admin.qr-scanner.index'));

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert the correct component is rendered
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/QrScanner/Index')
            ->has('events', 3)
            ->where('user_role', RoleNameEnum::ADMIN->value)
    );
});

it('allows organizer to access QR scanner page with their events only', function () {
    // Create an organizer user
    $organizer = User::factory()->create();
    $organizer->assignRole(RoleNameEnum::ORGANIZER);

    // Create events - some by the organizer, some by others
    $organizerEvents = Event::factory()->count(2)->create([
        'organizer_id' => $organizer->id,
        'event_status' => 'published'
    ]);

    $otherEvents = Event::factory()->count(3)->create([
        'event_status' => 'published'
    ]);

    // Act as the organizer and visit the QR scanner page
    $response = $this->actingAs($organizer)->get(route('admin.qr-scanner.index'));

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert only organizer's events are returned
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/QrScanner/Index')
            ->has('events', 2)
            ->where('user_role', RoleNameEnum::ORGANIZER->value)
    );
});

it('shows no events for regular user', function () {
    // Create a regular user
    $user = User::factory()->create();
    $user->assignRole(RoleNameEnum::USER);

    // Create some events
    Event::factory()->count(3)->create([
        'event_status' => 'published'
    ]);

    // Act as the user and visit the QR scanner page
    $response = $this->actingAs($user)->get(route('admin.qr-scanner.index'));

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert no events are returned for regular users
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/QrScanner/Index')
            ->has('events', 0)
            ->where('user_role', RoleNameEnum::USER->value)
    );
});

it('platform admin can see all published events regardless of organizer', function () {
    // Create a platform admin user
    $admin = User::factory()->create();
    $admin->assignRole(RoleNameEnum::ADMIN);

    // Create organizers
    $organizer1 = User::factory()->create();
    $organizer2 = User::factory()->create();

    // Create events by different organizers
    $events1 = Event::factory()->count(2)->create([
        'organizer_id' => $organizer1->id,
        'event_status' => 'published'
    ]);

    $events2 = Event::factory()->count(3)->create([
        'organizer_id' => $organizer2->id,
        'event_status' => 'published'
    ]);

    // Create some draft events (should not be included)
    Event::factory()->count(2)->create([
        'organizer_id' => $organizer1->id,
        'event_status' => 'draft'
    ]);

    // Act as the admin and visit the QR scanner page
    $response = $this->actingAs($admin)->get(route('admin.qr-scanner.index'));

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert all published events are returned (5 total)
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/QrScanner/Index')
            ->has('events', 5)
            ->where('user_role', RoleNameEnum::ADMIN->value)
    );
});
