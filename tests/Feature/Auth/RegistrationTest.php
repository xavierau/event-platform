<?php

use Illuminate\Support\Facades\Auth;
use App\Enums\RoleNameEnum;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    // Ensure the role exists before testing
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => RoleNameEnum::USER->value, 'guard_name' => 'web']);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));

    // Verify the user has the correct role
    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole(RoleNameEnum::USER->value))->toBeTrue();
    expect($user->roles)->toHaveCount(1);
});
