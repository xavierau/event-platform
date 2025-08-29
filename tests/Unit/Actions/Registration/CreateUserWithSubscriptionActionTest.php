<?php

use App\Actions\Registration\CreateUserWithSubscriptionAction;
use App\DataTransferObjects\Registration\RegistrationWithSubscriptionData;
use App\Models\User;
use App\Enums\RoleNameEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Exceptions\CustomerAlreadyCreated;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = new CreateUserWithSubscriptionAction();
});

it('creates user with basic information', function () {
    $data = RegistrationWithSubscriptionData::from([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'mobile_number' => '+1234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'selected_price_id' => 'price_free',
    ]);

    $user = $this->action->execute($data);

    expect($user)
        ->toBeInstanceOf(User::class)
        ->email->toBe('john@example.com')
        ->name->toBe('John Doe')
        ->mobile_number->toBe('+1234567890');

    expect($user->hasRole(RoleNameEnum::USER->value))->toBeTrue();
});

it('creates stripe customer with metadata', function () {
    $data = RegistrationWithSubscriptionData::from([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'mobile_number' => '+1987654321',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'selected_price_id' => 'price_premium_monthly',
        'metadata' => ['source' => 'registration'],
    ]);

    $user = $this->action->execute($data);

    expect($user->stripe_id)->not->toBeNull();
    
    // Verify user was saved to database
    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
        'name' => 'Jane Smith',
    ]);
});

it('handles stripe customer already exists exception', function () {
    $data = RegistrationWithSubscriptionData::from([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'mobile_number' => '+1111111111',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'selected_price_id' => 'price_vip_monthly',
    ]);

    // Mock Stripe to throw CustomerAlreadyCreated exception
    $this->mock(\Laravel\Cashier\Billable::class, function ($mock) {
        $mock->shouldReceive('createAsStripeCustomer')
             ->andThrow(new CustomerAlreadyCreated());
    });

    // Should not throw exception, should continue execution
    $user = $this->action->execute($data);
    
    expect($user)->toBeInstanceOf(User::class);
    expect($user->email)->toBe('test@example.com');
});

it('uses database transaction', function () {
    $data = RegistrationWithSubscriptionData::from([
        'name' => 'Transaction Test',
        'email' => 'transaction@example.com',
        'mobile_number' => '+1222222222',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'selected_price_id' => 'price_free',
    ]);

    // Mock to throw exception after user creation
    DB::shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
        return $callback();
    });

    $user = $this->action->execute($data);
    expect($user)->toBeInstanceOf(User::class);
});