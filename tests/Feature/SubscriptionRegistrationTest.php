<?php

use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Cashier;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create membership levels for testing
    MembershipLevel::create([
        'name' => ['en' => 'Free'],
        'slug' => 'free',
        'description' => ['en' => 'Free tier'],
        'price' => 0,
        'stripe_product_id' => 'prod_free',
        'stripe_price_id' => 'price_free',
        'benefits' => ['basic_access'],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    MembershipLevel::create([
        'name' => ['en' => 'Premium'],
        'slug' => 'premium',
        'description' => ['en' => 'Premium tier'],
        'price' => 2900,
        'stripe_product_id' => 'prod_premium',
        'stripe_price_id' => 'price_premium_monthly',
        'benefits' => ['premium_access'],
        'is_active' => true,
        'sort_order' => 2,
    ]);
});

it('displays pricing page with membership levels', function () {
    $response = $this->get(route('register.subscription.create'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/RegisterWithPricing')
            ->has('membershipLevels', 2)
            ->has('membershipLevels.0', fn ($level) => $level
                ->where('name.en', 'Free')
                ->where('price', 0)
                ->where('stripe_price_id', 'price_free')
                ->etc()
            )
            ->has('membershipLevels.1', fn ($level) => $level
                ->where('name.en', 'Premium')
                ->where('price', 2900)
                ->where('stripe_price_id', 'price_premium_monthly')
                ->etc()
            )
        );
});

it('registers user with free plan', function () {
    $response = $this->post(route('register.subscription.store'), [
        'name' => 'Free User',
        'email' => 'free@example.com',
        'mobile_number' => '+1234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'selected_price_id' => 'price_free',
    ]);

    $response->assertRedirect(route('register.subscription.success'));

    $this->assertDatabaseHas('users', [
        'email' => 'free@example.com',
        'name' => 'Free User',
    ]);

    $user = User::where('email', 'free@example.com')->first();
    expect($user->stripe_id)->not->toBeNull();
    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($user->id);
});

it('validates required fields', function () {
    $response = $this->post(route('register.subscription.store'), [
        'name' => '',
        'email' => 'invalid-email',
        'mobile_number' => '',
        'password' => '123',
        'password_confirmation' => '456',
        'selected_price_id' => '',
    ]);

    $response->assertSessionHasErrors([
        'name',
        'email',
        'mobile_number',
        'password',
        'selected_price_id',
    ]);

    $this->assertDatabaseCount('users', 0);
});

it('validates stripe price id exists in membership levels', function () {
    $response = $this->post(route('register.subscription.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'mobile_number' => '+1234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'selected_price_id' => 'price_nonexistent',
    ]);

    $response->assertSessionHasErrors(['selected_price_id']);
    $this->assertDatabaseCount('users', 0);
});

it('validates unique email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->post(route('register.subscription.store'), [
        'name' => 'Duplicate User',
        'email' => 'existing@example.com',
        'mobile_number' => '+1234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'selected_price_id' => 'price_free',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertDatabaseCount('users', 1);
});

it('registers user with premium plan and redirects to stripe', function () {
    // Mock Stripe checkout session creation
    $checkoutSession = new \Laravel\Cashier\Checkout(new User(), [
        'url' => 'https://checkout.stripe.com/pay/test_session',
    ]);

    $this->mock(\App\Services\RegistrationService::class, function ($mock) use ($checkoutSession) {
        $mock->shouldReceive('registerWithSubscription')
             ->once()
             ->andReturn([
                 'user' => User::factory()->make(),
                 'checkout' => $checkoutSession,
             ]);
    });

    $response = $this->post(route('register.subscription.store'), [
        'name' => 'Premium User',
        'email' => 'premium@example.com',
        'mobile_number' => '+1234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'selected_price_id' => 'price_premium_monthly',
    ]);

    // Should redirect to Stripe checkout
    $response->assertRedirect('https://checkout.stripe.com/pay/test_session');
});

it('handles success callback from stripe', function () {
    // Create and authenticate a user
    $user = User::factory()->create();
    $this->actingAs($user);

    // Mock Stripe session retrieval
    Cashier::stripe()->shouldReceive('checkout->sessions->retrieve')
        ->with('sess_test123')
        ->andReturn((object)[
            'payment_status' => 'paid'
        ]);

    $response = $this->get(route('register.subscription.success', ['session_id' => 'sess_test123']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/RegistrationSuccess')
            ->has('user')
        );
});

it('handles cancelled registration', function () {
    $response = $this->get(route('register.subscription.cancel'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/RegistrationCancelled')
        );
});

it('handles pending payment status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('register.subscription.pending'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/RegistrationPending')
            ->has('user')
        );
});