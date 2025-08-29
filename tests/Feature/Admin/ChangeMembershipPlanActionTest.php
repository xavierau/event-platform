<?php

use App\Actions\Admin\ChangeMembershipPlanAction;
use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Membership\Enums\PaymentMethod;

it('can change plans for multiple users at once', function () {
    // Create membership levels (both free to avoid Stripe)
    $basicLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Basic'],
        'price' => 0,
    ]);
    
    $premiumLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Premium'],
        'price' => 0, // Free for this test
    ]);

    // Create multiple users with basic membership
    $users = User::factory(3)->create();
    foreach ($users as $user) {
        $user->memberships()->create([
            'membership_level_id' => $basicLevel->id,
            'status' => 'active',
            'started_at' => now(),
            'payment_method' => PaymentMethod::ADMIN_GRANT,
            'auto_renew' => false,
        ]);
    }

    $action = new ChangeMembershipPlanAction();
    $result = $action->changePlanForMultipleUsers($users->pluck('id')->toArray(), $premiumLevel);

    expect($result['total'])->toBe(3);
    expect($result['successful'])->toBe(3);
    expect($result['failed'])->toBe(0);
    
    // Verify all users were updated
    foreach ($users as $user) {
        $user->refresh();
        expect($user->currentMembership()->membership_level_id)->toBe($premiumLevel->id);
    }
});

it('creates new membership when user has none', function () {
    $premiumLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Premium'],
        'price' => 0, // Free plan
    ]);

    $user = User::factory()->create();
    // User has no existing membership

    $action = new ChangeMembershipPlanAction();
    $result = $action->execute($user, $premiumLevel);

    expect($result['success'])->toBeTrue();
    expect($result['old_level_id'])->toBeNull();
    expect($result['new_level_id'])->toBe($premiumLevel->id);
    
    // Verify membership was created
    $currentMembership = $user->currentMembership();
    expect($currentMembership)->not->toBeNull();
    expect($currentMembership->membership_level_id)->toBe($premiumLevel->id);
});

it('changes between free plans correctly', function () {
    // Create two free membership levels
    $basicLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Basic Free'],
        'price' => 0,
    ]);
    
    $premiumLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Premium Free'],
        'price' => 0,
    ]);

    // Create user with basic membership
    $user = User::factory()->create();
    $user->memberships()->create([
        'membership_level_id' => $basicLevel->id,
        'status' => 'active',
        'started_at' => now(),
        'payment_method' => PaymentMethod::ADMIN_GRANT,
        'auto_renew' => false,
    ]);

    $action = new ChangeMembershipPlanAction();
    $result = $action->execute($user, $premiumLevel);

    expect($result['success'])->toBeTrue();
    expect($result['new_level_id'])->toBe($premiumLevel->id);
    expect($result['old_level_id'])->toBe($basicLevel->id);
    
    // Verify membership was updated
    $user->refresh();
    $currentMembership = $user->currentMembership();
    expect($currentMembership->membership_level_id)->toBe($premiumLevel->id);
    expect($currentMembership->payment_method)->toBe(PaymentMethod::ADMIN_GRANT);
    expect($currentMembership->auto_renew)->toBeFalse();
});

it('handles bulk changes with some failures', function () {
    $premiumLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Premium'],
        'price' => 0,
    ]);

    // Mix of valid user IDs and invalid ones
    $validUsers = User::factory(2)->create();
    $invalidUserIds = [9999, 9998]; // Non-existent IDs
    $userIds = array_merge($validUsers->pluck('id')->toArray(), $invalidUserIds);

    $action = new ChangeMembershipPlanAction();
    $result = $action->changePlanForMultipleUsers($userIds, $premiumLevel);

    expect($result['total'])->toBe(4);
    expect($result['successful'])->toBe(2);
    expect($result['failed'])->toBe(2);
    
    // Verify details contain information about failures
    expect($result['details'])->toHaveCount(4);
    expect($result['details'][9999]['success'])->toBeFalse();
    expect($result['details'][9998]['success'])->toBeFalse();
});
