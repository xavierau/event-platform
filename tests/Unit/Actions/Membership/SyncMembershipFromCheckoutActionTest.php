<?php

use App\Actions\Membership\SyncMembershipFromCheckoutAction;
use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = new SyncMembershipFromCheckoutAction();
});

it('syncs membership from valid checkout session', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test123']);
    $membershipLevel = MembershipLevel::factory()->create([
        'stripe_price_id' => 'price_premium_monthly',
        'duration_months' => 1,
    ]);

    $session = (object)[
        'mode' => 'subscription',
        'customer' => 'cus_test123',
        'subscription' => 'sub_test123',
        'id' => 'cs_test123',
        'amount_total' => 2900,
        'currency' => 'usd',
        'metadata' => (object)[
            'membership_level_id' => $membershipLevel->id,
        ]
    ];

    $membership = $this->action->execute($session);

    expect($membership)
        ->toBeInstanceOf(UserMembership::class)
        ->user_id->toBe($user->id)
        ->membership_level_id->toBe($membershipLevel->id)
        ->status->toBe(MembershipStatus::ACTIVE)
        ->payment_method->toBe(PaymentMethod::STRIPE)
        ->stripe_subscription_id->toBe('sub_test123')
        ->stripe_customer_id->toBe('cus_test123')
        ->auto_renew->toBeTrue();

    expect($membership->subscription_metadata)
        ->toBeArray()
        ->toHaveKey('checkout_session_id', 'cs_test123')
        ->toHaveKey('amount_total', 2900)
        ->toHaveKey('currency', 'usd');
});

it('returns null for non-subscription sessions', function () {
    $session = (object)[
        'mode' => 'payment',
        'customer' => 'cus_test123',
    ];

    $result = $this->action->execute($session);

    expect($result)->toBeNull();
});

it('returns null when user not found', function () {
    $session = (object)[
        'mode' => 'subscription',
        'customer' => 'cus_nonexistent',
        'metadata' => (object)[
            'membership_level_id' => 1,
        ]
    ];

    $result = $this->action->execute($session);

    expect($result)->toBeNull();
});

it('returns null when membership level not found', function () {
    User::factory()->create(['stripe_id' => 'cus_test123']);

    $session = (object)[
        'mode' => 'subscription',
        'customer' => 'cus_test123',
        'metadata' => (object)[
            'membership_level_id' => 999,
        ]
    ];

    $result = $this->action->execute($session);

    expect($result)->toBeNull();
});

it('returns null when membership level id missing from metadata', function () {
    User::factory()->create(['stripe_id' => 'cus_test123']);

    $session = (object)[
        'mode' => 'subscription',
        'customer' => 'cus_test123',
        'metadata' => (object)[]
    ];

    $result = $this->action->execute($session);

    expect($result)->toBeNull();
});

it('returns existing membership if already synced', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test123']);
    $membershipLevel = MembershipLevel::factory()->create();
    
    $existingMembership = UserMembership::factory()->create([
        'stripe_subscription_id' => 'sub_test123',
        'user_id' => $user->id,
        'membership_level_id' => $membershipLevel->id,
    ]);

    $session = (object)[
        'mode' => 'subscription',
        'customer' => 'cus_test123',
        'subscription' => 'sub_test123',
        'metadata' => (object)[
            'membership_level_id' => $membershipLevel->id,
        ]
    ];

    $result = $this->action->execute($session);

    expect($result->id)->toBe($existingMembership->id);
    $this->assertDatabaseCount('user_memberships', 1);
});

it('sets expiration date for limited duration memberships', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test123']);
    $membershipLevel = MembershipLevel::factory()->create([
        'duration_months' => 3,
    ]);

    $session = (object)[
        'mode' => 'subscription',
        'customer' => 'cus_test123',
        'subscription' => 'sub_test123',
        'id' => 'cs_test123',
        'amount_total' => 2900,
        'currency' => 'usd',
        'metadata' => (object)[
            'membership_level_id' => $membershipLevel->id,
        ]
    ];

    $membership = $this->action->execute($session);

    expect($membership->expires_at)->not->toBeNull();
    expect($membership->expires_at->diffInMonths($membership->started_at))->toBe(3);
});

it('sets no expiration for unlimited memberships', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test123']);
    $membershipLevel = MembershipLevel::factory()->create([
        'duration_months' => null,
    ]);

    $session = (object)[
        'mode' => 'subscription',
        'customer' => 'cus_test123',
        'subscription' => 'sub_test123',
        'id' => 'cs_test123',
        'amount_total' => 0,
        'currency' => 'usd',
        'metadata' => (object)[
            'membership_level_id' => $membershipLevel->id,
        ]
    ];

    $membership = $this->action->execute($session);

    expect($membership->expires_at)->toBeNull();
});