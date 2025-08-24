<?php

use App\Models\Event;
use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use Inertia\Testing\AssertableInertia;

it('shows public events to all users', function () {
    $event = Event::factory()->create([
        'visible_to_membership_levels' => null,
        'action_type' => 'purchase_ticket',
        'event_status' => 'published'
    ]);
    
    // Test as guest
    $response = $this->get(route('events.show', $event));
    $response->assertOk();
    
    // Test as authenticated user without membership
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('events.show', $event));
    $response->assertOk();
});

it('restricts member-only events to users with correct membership', function () {
    $premiumLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Premium', 'zh-TW' => '進階會員']
    ]);
    
    $event = Event::factory()->create([
        'visible_to_membership_levels' => [$premiumLevel->id],
        'action_type' => 'show_member_qr',
        'event_status' => 'published'
    ]);
    
    // User without membership
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('events.show', $event));
    $response->assertOk();
    
    // Check the response contains membership requirement info in the JSON response
    $response->assertInertia(fn ($page) => 
        $page->component('Public/EventDetail')
             ->where('event.user_has_access', false)
             ->where('event.action_type', 'show_member_qr')
             ->has('event.required_membership_names', 1)
    );
    
    // User with correct membership
    UserMembership::factory()->create([
        'user_id' => $user->id,
        'membership_level_id' => $premiumLevel->id,
        'status' => 'active',
        'expires_at' => now()->addMonth()
    ]);
    
    $response = $this->actingAs($user)->get(route('events.show', $event));
    $response->assertOk();
    
    // Check the response shows user has access
    $response->assertInertia(fn ($page) => 
        $page->component('Public/EventDetail')
             ->where('event.user_has_access', true)
             ->where('event.action_type', 'show_member_qr')
    );
});

it('correctly determines event visibility using model methods', function () {
    $membershipLevel = MembershipLevel::factory()->create();
    $user = User::factory()->create();
    
    // Public event
    $publicEvent = Event::factory()->create([
        'visible_to_membership_levels' => null
    ]);
    
    expect($publicEvent->isPublic())->toBeTrue();
    expect($publicEvent->isVisibleToUser(null))->toBeTrue();
    expect($publicEvent->isVisibleToUser($user))->toBeTrue();
    
    // Member-only event
    $memberEvent = Event::factory()->create([
        'visible_to_membership_levels' => [$membershipLevel->id]
    ]);
    
    expect($memberEvent->isPublic())->toBeFalse();
    expect($memberEvent->isVisibleToUser(null))->toBeFalse();
    expect($memberEvent->isVisibleToUser($user))->toBeFalse();
    
    // User gets membership
    UserMembership::factory()->create([
        'user_id' => $user->id,
        'membership_level_id' => $membershipLevel->id,
        'status' => 'active',
        'expires_at' => now()->addMonth()
    ]);
    
    // Refresh user to load relationship
    $user->refresh();
    expect($memberEvent->isVisibleToUser($user))->toBeTrue();
});