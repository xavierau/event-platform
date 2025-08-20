<?php

use App\Models\User;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Organizer;
use App\Enums\OrganizerPermissionEnum;
use App\Enums\OrganizerRoleEnum;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create platform-admin role if it doesn't exist
    if (!Role::where('name', 'platform-admin')->exists()) {
        Role::create(['name' => 'platform-admin']);
    }
});

test('platform admin can assign seat to booking', function () {
    // Create a platform admin user
    $admin = User::factory()->create();
    $admin->assignRole('platform-admin');
    
    // Create booking
    $booking = Booking::factory()->create();
    
    // Act as admin and assign seat
    $response = $this
        ->actingAs($admin, 'sanctum')
        ->postJson("/api/bookings/{$booking->id}/seat", [
            'seat_number' => 'A12'
        ]);
    
    // Assert
    $response->assertSuccessful();
    $response->assertJson([
        'message' => 'Seat assigned successfully',
        'seat_number' => 'A12'
    ]);
    
    // Check database
    $booking->refresh();
    expect($booking->metadata['seat_number'])->toBe('A12');
    expect($booking->metadata['seat_assigned_by'])->toBe($admin->id);
    expect($booking->metadata['seat_assigned_at'])->not()->toBeNull();
});

test('organizer admin can assign seat to their event booking', function () {
    // Create organizer and user
    $organizer = Organizer::factory()->create();
    $user = User::factory()->create();
    
    // Manually create organizer membership
    $organizer->users()->attach($user->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([OrganizerPermissionEnum::MANAGE_BOOKINGS->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invited_by' => $user->id,
    ]);
    
    // Create event for this organizer
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $booking = Booking::factory()->create(['event_id' => $event->id]);
    
    // Act as organizer user and assign seat
    $response = $this
        ->actingAs($user, 'sanctum')
        ->postJson("/api/bookings/{$booking->id}/seat", [
            'seat_number' => 'B5'
        ]);
    
    // Assert
    $response->assertSuccessful();
    $response->assertJson([
        'message' => 'Seat assigned successfully',
        'seat_number' => 'B5'
    ]);
    
    // Check database
    $booking->refresh();
    expect($booking->metadata['seat_number'])->toBe('B5');
});

test('organizer cannot assign seat to other organizer event booking', function () {
    // Create two organizers and users
    $organizer1 = Organizer::factory()->create();
    $organizer2 = Organizer::factory()->create();
    $user = User::factory()->create();
    
    // Assign user to organizer1 only
    $organizer1->users()->attach($user->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([OrganizerPermissionEnum::MANAGE_BOOKINGS->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invited_by' => $user->id,
    ]);
    
    // Create event for organizer2
    $event = Event::factory()->create(['organizer_id' => $organizer2->id]);
    $booking = Booking::factory()->create(['event_id' => $event->id]);
    
    // Try to assign seat (should fail)
    $response = $this
        ->actingAs($user, 'sanctum')
        ->postJson("/api/bookings/{$booking->id}/seat", [
            'seat_number' => 'C1'
        ]);
    
    // Assert
    $response->assertForbidden();
});

test('can remove seat assignment', function () {
    // Create admin and booking with seat
    $admin = User::factory()->create();
    $admin->assignRole('platform-admin');
    
    $booking = Booking::factory()->create([
        'metadata' => [
            'seat_number' => 'D10',
            'seat_assigned_by' => $admin->id,
            'seat_assigned_at' => now()->toISOString()
        ]
    ]);
    
    // Remove seat assignment
    $response = $this
        ->actingAs($admin, 'sanctum')
        ->deleteJson("/api/bookings/{$booking->id}/seat");
    
    // Assert
    $response->assertSuccessful();
    $response->assertJson([
        'message' => 'Seat assignment removed successfully'
    ]);
    
    // Check database
    $booking->refresh();
    expect($booking->metadata['seat_number'] ?? null)->toBeNull();
});

test('booking model helper methods work correctly', function () {
    // Test booking without seat
    $booking = Booking::factory()->create();
    expect($booking->hasAssignedSeat())->toBeFalse();
    expect($booking->seat_number)->toBeNull();
    expect($booking->getSeatAssignmentInfo())->toBeNull();
    
    // Test booking with seat
    $booking = Booking::factory()->create([
        'metadata' => [
            'seat_number' => 'E15',
            'seat_assigned_by' => 1,
            'seat_assigned_at' => '2024-01-15T10:30:00Z'
        ]
    ]);
    
    expect($booking->hasAssignedSeat())->toBeTrue();
    expect($booking->seat_number)->toBe('E15');
    expect($booking->getSeatAssignmentInfo())->toBe([
        'seat_number' => 'E15',
        'assigned_by' => 1,
        'assigned_at' => '2024-01-15T10:30:00Z'
    ]);
});