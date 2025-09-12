<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleNameEnum;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin role and permission if they don't exist
        $adminRole = Role::firstOrCreate(['name' => RoleNameEnum::ADMIN->value]);
        $manageUsersPermission = Permission::firstOrCreate(['name' => 'manage-users']);
        
        $adminRole->givePermissionTo($manageUsersPermission);
        
        // Create admin user with proper role and permission
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);
        $this->adminUser->givePermissionTo($manageUsersPermission);
        
        $this->actingAs($this->adminUser);
    }

    #[Test]
    public function admin_can_access_user_creation_form()
    {
        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Test Level'],
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.users.create'));

        $response->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Create')
                ->has('membershipLevels', 1)
                ->where('membershipLevels.0.id', $membershipLevel->id)
                ->where('membershipLevels.0.name', $membershipLevel->name)
            );
    }

    #[Test]
    public function admin_can_create_user_with_valid_data()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'mobile_number' => '+1234567890',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'reason' => 'Test user creation',
        ];

        $response = $this->post(route('admin.users.store'), $userData);

        $user = User::where('email', 'john@example.com')->first();
        $response->assertRedirectToRoute('admin.users.show', ['user' => $user->id]);
        
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'mobile_number' => '+1234567890',
            'email_verified_at' => now(),
        ]);

        // Check audit log was created
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $this->adminUser->id,
            'action_type' => 'create_user',
            'reason' => 'Test user creation',
        ]);
    }

    #[Test]
    public function admin_can_create_user_with_membership()
    {
        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Premium'],
            'duration_months' => 12,
        ]);

        $userData = [
            'name' => 'Premium User',
            'email' => 'premium@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'membership_level_id' => $membershipLevel->id,
            'reason' => 'Premium user creation',
        ];

        $response = $this->post(route('admin.users.store'), $userData);

        $user = User::where('email', 'premium@example.com')->first();
        $response->assertRedirectToRoute('admin.users.show', ['user' => $user->id]);

        // Check user was created
        $this->assertDatabaseHas('users', [
            'email' => 'premium@example.com',
            'name' => 'Premium User',
        ]);

        // Check membership was assigned
        $this->assertDatabaseHas('user_memberships', [
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
        ]);
    }

    #[Test]
    public function admin_can_create_user_with_custom_membership_duration()
    {
        $membershipLevel = MembershipLevel::factory()->create([
            'duration_months' => 12,
        ]);

        $userData = [
            'name' => 'Custom Duration User',
            'email' => 'custom@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'membership_level_id' => $membershipLevel->id,
            'membership_duration_months' => 6,
        ];

        $this->post(route('admin.users.store'), $userData);

        $user = User::where('email', 'custom@example.com')->first();
        $membership = UserMembership::where('user_id', $user->id)->first();
        
        expect($membership)->not->toBeNull();
        
        $expectedExpiry = $membership->started_at->addMonths(6);
        expect($membership->expires_at->format('Y-m-d'))->toBe($expectedExpiry->format('Y-m-d'));
    }

    #[Test]
    public function user_creation_validates_required_fields()
    {
        $response = $this->post(route('admin.users.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }

    #[Test]
    public function user_creation_validates_email_uniqueness()
    {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    #[Test]
    public function user_creation_validates_password_confirmation()
    {
        $response = $this->post(route('admin.users.store'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'DifferentPassword!',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    #[Test]
    public function admin_can_change_user_membership()
    {
        $user = User::factory()->create();
        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'New Level'],
        ]);

        $response = $this->postJson(route('admin.users.change-membership', $user), [
            'membership_level_id' => $membershipLevel->id,
            'reason' => 'Upgrading user membership',
        ]);

        $response->assertSuccessful()
            ->assertJson([
                'success' => true,
                'message' => 'Membership changed successfully.',
            ]);

        // Check membership was created
        $this->assertDatabaseHas('user_memberships', [
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
        ]);

        // Check audit log was created
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $this->adminUser->id,
            'target_user_id' => $user->id,
            'action_type' => 'change_membership',
            'reason' => 'Upgrading user membership',
        ]);
    }

    #[Test]
    public function membership_change_validates_required_fields()
    {
        $user = User::factory()->create();

        $response = $this->postJson(route('admin.users.change-membership', $user), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['membership_level_id', 'reason']);
    }

    #[Test]
    public function membership_change_validates_membership_level_exists()
    {
        $user = User::factory()->create();

        $response = $this->postJson(route('admin.users.change-membership', $user), [
            'membership_level_id' => 99999,
            'reason' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['membership_level_id']);
    }

    #[Test]
    public function admin_can_change_membership_with_custom_duration()
    {
        $user = User::factory()->create();
        $membershipLevel = MembershipLevel::factory()->create([
            'duration_months' => 12,
        ]);

        $response = $this->postJson(route('admin.users.change-membership', $user), [
            'membership_level_id' => $membershipLevel->id,
            'membership_duration_months' => 6,
            'reason' => 'Custom duration test',
        ]);

        $response->assertSuccessful();

        $membership = UserMembership::where('user_id', $user->id)->first();
        expect($membership)->not->toBeNull();
        
        $expectedExpiry = $membership->started_at->addMonths(6);
        expect($membership->expires_at->format('Y-m-d'))->toBe($expectedExpiry->format('Y-m-d'));
    }

    #[Test]
    public function membership_change_cancels_existing_membership()
    {
        $user = User::factory()->create();
        $oldMembershipLevel = MembershipLevel::factory()->create();
        $newMembershipLevel = MembershipLevel::factory()->create();

        // Create existing membership
        $oldMembership = UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $oldMembershipLevel->id,
            'status' => 'active',
        ]);

        $this->postJson(route('admin.users.change-membership', $user), [
            'membership_level_id' => $newMembershipLevel->id,
            'reason' => 'Membership upgrade',
        ]);

        // Check old membership was cancelled  
        $oldMembership->refresh();
        expect($oldMembership->status->value)->toBe('cancelled');

        // Check new membership exists
        $newMembership = UserMembership::where('user_id', $user->id)
            ->where('membership_level_id', $newMembershipLevel->id)
            ->first();
        expect($newMembership)->not->toBeNull()
            ->and($newMembership->status->value)->toBe('active');
    }

    #[Test]
    public function audit_log_includes_ip_and_user_agent()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $this->withHeaders([
            'User-Agent' => 'Test Browser',
        ])->post(route('admin.users.store'), $userData);

        $auditLog = AdminAuditLog::latest()->first();
        expect($auditLog)->not->toBeNull()
            ->and($auditLog->ip_address)->toBe('127.0.0.1')
            ->and($auditLog->user_agent)->toBe('Test Browser');
    }

    #[Test]
    public function handles_user_creation_failure_gracefully()
    {
        // Try to create user with invalid membership level
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'membership_level_id' => 99999, // Non-existent
        ];

        $response = $this->post(route('admin.users.store'), $userData);

        $response->assertSessionHasErrors(['membership_level_id']);
        
        // Verify user was not created
        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);
    }
}