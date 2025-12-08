<?php

use App\Enums\RoleNameEnum;
use App\Models\User;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\TemporaryRegistration\Models\TemporaryRegistrationPage;

beforeEach(function () {
    // Ensure roles exist
    if (!\Spatie\Permission\Models\Role::where('name', RoleNameEnum::ADMIN->value)->exists()) {
        \Spatie\Permission\Models\Role::create(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);
    }
    if (!\Spatie\Permission\Models\Role::where('name', RoleNameEnum::USER->value)->exists()) {
        \Spatie\Permission\Models\Role::create(['name' => RoleNameEnum::USER->value, 'guard_name' => 'web']);
    }
});

// ============================================
// ADMIN CRUD TESTS
// ============================================

describe('Admin CRUD', function () {
    it('can list temporary registration pages', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN->value);

        TemporaryRegistrationPage::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.temporary-registration.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TemporaryRegistration/Index')
            ->has('pages.data', 3)
            ->has('stats')
        );
    });

    it('can create a temporary registration page with required fields', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN->value);

        $membershipLevel = MembershipLevel::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.temporary-registration.store'), [
                'title' => ['en' => 'Test Registration Page', 'zh-TW' => '測試註冊頁面'],
                'description' => ['en' => 'A test description'],
                'membership_level_id' => $membershipLevel->id,
                'use_slug' => false,
                'is_active' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('temporary_registration_pages', [
            'membership_level_id' => $membershipLevel->id,
            'is_active' => true,
        ]);
    });

    it('can create a page with custom slug', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN->value);

        $membershipLevel = MembershipLevel::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.temporary-registration.store'), [
                'title' => ['en' => 'Summer Promo'],
                'membership_level_id' => $membershipLevel->id,
                'use_slug' => true,
                'slug' => 'summer-promo-2025',
                'is_active' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('temporary_registration_pages', [
            'slug' => 'summer-promo-2025',
            'use_slug' => true,
        ]);
    });

    it('can update a temporary registration page', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN->value);

        $page = TemporaryRegistrationPage::factory()->create([
            'title' => ['en' => 'Original Title'],
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.temporary-registration.update', $page), [
                'title' => ['en' => 'Updated Title'],
                'membership_level_id' => $page->membership_level_id,
                'use_slug' => false,
                'is_active' => true,
            ]);

        $response->assertRedirect();

        $page->refresh();
        expect($page->getTranslation('title', 'en'))->toBe('Updated Title');
    });

    it('can delete a temporary registration page', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN->value);

        $page = TemporaryRegistrationPage::factory()->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.temporary-registration.destroy', $page));

        $response->assertRedirect(route('admin.temporary-registration.index'));

        $this->assertSoftDeleted('temporary_registration_pages', [
            'id' => $page->id,
        ]);
    });

    it('can toggle page active status', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN->value);

        $page = TemporaryRegistrationPage::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)
            ->patch(route('admin.temporary-registration.toggle-active', $page));

        $response->assertRedirect();

        $page->refresh();
        expect($page->is_active)->toBeFalse();
    });

    it('non-admin cannot access admin pages', function () {
        $user = User::factory()->create();
        $user->assignRole(RoleNameEnum::USER->value);

        $response = $this->actingAs($user)
            ->get(route('admin.temporary-registration.index'));

        // CheckRole middleware redirects unauthorized users to home
        $response->assertRedirect('/');
    });
});

// ============================================
// PUBLIC REGISTRATION TESTS
// ============================================

describe('Public Registration', function () {
    it('allows user registration through available page', function () {
        $membershipLevel = MembershipLevel::factory()->create([
            'duration_months' => 12,
        ]);

        $page = TemporaryRegistrationPage::factory()->available()->create([
            'membership_level_id' => $membershipLevel->id,
        ]);

        $response = $this->post(route('register.temporary.store', $page->token), [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'mobile_number' => '+85291234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', ['email' => 'testuser@example.com']);

        $user = User::where('email', 'testuser@example.com')->first();
        expect($user)->not->toBeNull();
        expect($user->hasRole(RoleNameEnum::USER->value))->toBeTrue();
    });

    it('assigns correct membership level to registered user', function () {
        $membershipLevel = MembershipLevel::factory()->create([
            'duration_months' => 6,
        ]);

        $page = TemporaryRegistrationPage::factory()->available()->create([
            'membership_level_id' => $membershipLevel->id,
        ]);

        $this->post(route('register.temporary.store', $page->token), [
            'name' => 'Test User',
            'email' => 'member@example.com',
            'mobile_number' => '+85291234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'member@example.com')->first();

        $this->assertDatabaseHas('user_memberships', [
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => MembershipStatus::ACTIVE->value,
            'payment_method' => PaymentMethod::PROMOTIONAL->value,
        ]);
    });

    it('increments registration count after successful registration', function () {
        $page = TemporaryRegistrationPage::factory()->available()->create([
            'registrations_count' => 5,
        ]);

        $this->post(route('register.temporary.store', $page->token), [
            'name' => 'Test User',
            'email' => 'counter@example.com',
            'mobile_number' => '+85291234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $page->refresh();
        expect($page->registrations_count)->toBe(6);
    });

    it('tracks registered users in pivot table', function () {
        $page = TemporaryRegistrationPage::factory()->available()->create();

        $this->post(route('register.temporary.store', $page->token), [
            'name' => 'Tracked User',
            'email' => 'tracked@example.com',
            'mobile_number' => '+85291234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'tracked@example.com')->first();

        $this->assertDatabaseHas('temporary_registration_page_users', [
            'temporary_registration_page_id' => $page->id,
            'user_id' => $user->id,
        ]);
    });

    it('shows unavailable page when expired', function () {
        $page = TemporaryRegistrationPage::factory()->expired()->create();

        $response = $this->get(route('register.temporary', $page->token));

        $response->assertOk();
        $response->assertInertia(fn ($inertia) => $inertia
            ->component('auth/TemporaryRegistration/Unavailable')
            ->where('reason', 'expired')
        );
    });

    it('shows unavailable page when full', function () {
        $page = TemporaryRegistrationPage::factory()->full()->create();

        $response = $this->get(route('register.temporary', $page->token));

        $response->assertOk();
        $response->assertInertia(fn ($inertia) => $inertia
            ->component('auth/TemporaryRegistration/Unavailable')
            ->where('reason', 'full')
        );
    });

    it('shows unavailable page when inactive', function () {
        $page = TemporaryRegistrationPage::factory()->inactive()->create();

        $response = $this->get(route('register.temporary', $page->token));

        $response->assertOk();
        $response->assertInertia(fn ($inertia) => $inertia
            ->component('auth/TemporaryRegistration/Unavailable')
            ->where('reason', 'inactive')
        );
    });

    it('prevents duplicate email registration', function () {
        User::factory()->create(['email' => 'existing@example.com']);

        $page = TemporaryRegistrationPage::factory()->available()->create();

        $response = $this->post(route('register.temporary.store', $page->token), [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'mobile_number' => '+85291234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    });

    it('can access page by slug when use_slug is true', function () {
        $page = TemporaryRegistrationPage::factory()->available()->create([
            'slug' => 'my-promo-page',
            'use_slug' => true,
        ]);

        $response = $this->get(route('register.temporary', 'my-promo-page'));

        $response->assertOk();
        $response->assertInertia(fn ($inertia) => $inertia
            ->component('auth/TemporaryRegistration/Register')
        );
    });

    it('returns 404 for non-existent page', function () {
        $response = $this->get(route('register.temporary', 'non-existent-token'));

        $response->assertNotFound();
    });

    it('assigns custom duration_days to membership when set', function () {
        $membershipLevel = MembershipLevel::factory()->create([
            'duration_months' => 12, // Default is 12 months
        ]);

        // Create page with custom 14-day trial
        $page = TemporaryRegistrationPage::factory()->available()->create([
            'membership_level_id' => $membershipLevel->id,
            'duration_days' => 14,
        ]);

        $this->post(route('register.temporary.store', $page->token), [
            'name' => 'Trial User',
            'email' => 'trial@example.com',
            'mobile_number' => '+85291234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'trial@example.com')->first();
        $membership = $user->memberships()->first();

        expect($membership)->not->toBeNull();
        expect($membership->membership_level_id)->toBe($membershipLevel->id);
        expect($membership->status->value)->toBe(MembershipStatus::ACTIVE->value);

        // Verify the expiration is 14 days from now, not 12 months
        $expectedExpiry = now()->addDays(14);
        expect($membership->expires_at->format('Y-m-d'))->toBe($expectedExpiry->format('Y-m-d'));

        // Verify custom_duration_days is stored in metadata
        expect($membership->subscription_metadata['custom_duration_days'])->toBe(14);
    });

    it('uses membership level duration when duration_days is null', function () {
        $membershipLevel = MembershipLevel::factory()->create([
            'duration_months' => 6,
        ]);

        // Create page without custom duration (uses membership level default)
        $page = TemporaryRegistrationPage::factory()->available()->create([
            'membership_level_id' => $membershipLevel->id,
            'duration_days' => null,
        ]);

        $this->post(route('register.temporary.store', $page->token), [
            'name' => 'Standard User',
            'email' => 'standard@example.com',
            'mobile_number' => '+85291234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'standard@example.com')->first();
        $membership = $user->memberships()->first();

        expect($membership)->not->toBeNull();

        // Verify the expiration is 6 months from now (membership level default)
        $expectedExpiry = now()->addMonths(6);
        expect($membership->expires_at->format('Y-m-d'))->toBe($expectedExpiry->format('Y-m-d'));

        // custom_duration_days should be null in metadata
        expect($membership->subscription_metadata['custom_duration_days'])->toBeNull();
    });

    it('assigns 7-day trial correctly', function () {
        $membershipLevel = MembershipLevel::factory()->create([
            'duration_months' => 12,
        ]);

        $page = TemporaryRegistrationPage::factory()->available()->create([
            'membership_level_id' => $membershipLevel->id,
            'duration_days' => 7,
        ]);

        $this->post(route('register.temporary.store', $page->token), [
            'name' => 'Weekly Trial User',
            'email' => 'weekly@example.com',
            'mobile_number' => '+85291234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'weekly@example.com')->first();
        $membership = $user->memberships()->first();

        $expectedExpiry = now()->addDays(7);
        expect($membership->expires_at->format('Y-m-d'))->toBe($expectedExpiry->format('Y-m-d'));
    });

    it('assigns 30-day trial correctly', function () {
        $membershipLevel = MembershipLevel::factory()->create([
            'duration_months' => 12,
        ]);

        $page = TemporaryRegistrationPage::factory()->available()->create([
            'membership_level_id' => $membershipLevel->id,
            'duration_days' => 30,
        ]);

        $this->post(route('register.temporary.store', $page->token), [
            'name' => 'Monthly Trial User',
            'email' => 'monthly@example.com',
            'mobile_number' => '+85291234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'monthly@example.com')->first();
        $membership = $user->memberships()->first();

        $expectedExpiry = now()->addDays(30);
        expect($membership->expires_at->format('Y-m-d'))->toBe($expectedExpiry->format('Y-m-d'));
    });
});

// ============================================
// MODEL TESTS
// ============================================

describe('Model', function () {
    it('auto-generates token on creation', function () {
        $page = TemporaryRegistrationPage::factory()->create(['token' => null]);

        expect($page->token)->not->toBeNull();
        expect(strlen($page->token))->toBe(32);
    });

    it('correctly identifies available pages', function () {
        $available = TemporaryRegistrationPage::factory()->available()->create();
        $expired = TemporaryRegistrationPage::factory()->expired()->create();
        $full = TemporaryRegistrationPage::factory()->full()->create();
        $inactive = TemporaryRegistrationPage::factory()->inactive()->create();

        expect($available->isAvailable())->toBeTrue();
        expect($expired->isAvailable())->toBeFalse();
        expect($full->isAvailable())->toBeFalse();
        expect($inactive->isAvailable())->toBeFalse();
    });

    it('calculates remaining slots correctly', function () {
        $unlimited = TemporaryRegistrationPage::factory()->create([
            'max_registrations' => null,
        ]);

        $limited = TemporaryRegistrationPage::factory()->create([
            'max_registrations' => 100,
            'registrations_count' => 30,
        ]);

        expect($unlimited->getRemainingSlots())->toBeNull();
        expect($limited->getRemainingSlots())->toBe(70);
    });

    it('generates correct public URL', function () {
        $tokenPage = TemporaryRegistrationPage::factory()->create([
            'token' => 'abc123token',
            'use_slug' => false,
        ]);

        $slugPage = TemporaryRegistrationPage::factory()->create([
            'slug' => 'my-custom-slug',
            'use_slug' => true,
        ]);

        expect($tokenPage->getPublicUrl())->toContain('abc123token');
        expect($slugPage->getPublicUrl())->toContain('my-custom-slug');
    });
});
