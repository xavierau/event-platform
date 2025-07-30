<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMembershipAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $targetUser;
    private MembershipLevel $membershipLevel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        // Create admin user with proper permissions  
        $this->admin = User::factory()->create();
        
        // Create the required permission if it doesn't exist
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage-users', 'guard_name' => 'web']);
        $this->admin->givePermissionTo('manage-users');
        
        // Create target user
        $this->targetUser = User::factory()->create();
        
        // Create membership level
        $this->membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Premium', 'zh-TW' => '高級會員'],
            'duration_months' => 12,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_assign_membership_level_to_user()
    {
        $this->actingAs($this->admin);

        // Test the action directly first
        $action = new \App\Modules\Membership\Actions\AssignMembershipLevelAction();
        $membership = $action->execute($this->targetUser, $this->membershipLevel);
        
        $this->assertInstanceOf(UserMembership::class, $membership);
        $this->assertEquals($this->targetUser->id, $membership->user_id);
        $this->assertEquals($this->membershipLevel->id, $membership->membership_level_id);
        $this->assertEquals(MembershipStatus::ACTIVE, $membership->status);
        $this->assertEquals(PaymentMethod::ADMIN_GRANT, $membership->payment_method);
    }

    public function test_admin_can_assign_membership_with_custom_duration()
    {
        $this->actingAs($this->admin);
        $customDuration = 6;

        $response = $this->patch(route('admin.users.update', $this->targetUser), [
            'is_commenting_blocked' => false,
            'membership_level_id' => $this->membershipLevel->id,
            'membership_duration_months' => $customDuration,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $membership = $this->targetUser->fresh()->currentMembership;
        $this->assertNotNull($membership);
        
        // Check duration is custom, not the default
        $expectedExpiry = now()->addMonths($customDuration);
        $this->assertTrue($membership->expires_at->isSameDay($expectedExpiry));
    }

    public function test_assigning_new_membership_cancels_existing_membership()
    {
        $this->actingAs($this->admin);

        // Create existing membership
        $existingMembership = UserMembership::factory()->create([
            'user_id' => $this->targetUser->id,
            'membership_level_id' => MembershipLevel::factory()->create()->id,
            'status' => MembershipStatus::ACTIVE,
            'expires_at' => now()->addMonths(6),
        ]);

        $response = $this->patch(route('admin.users.update', $this->targetUser), [
            'is_commenting_blocked' => false,
            'membership_level_id' => $this->membershipLevel->id,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        // Check old membership is cancelled
        $this->assertEquals(MembershipStatus::CANCELLED, $existingMembership->fresh()->status);

        // Check new membership is active
        $currentMembership = $this->targetUser->fresh()->currentMembership;
        $this->assertEquals($this->membershipLevel->id, $currentMembership->membership_level_id);
        $this->assertEquals(MembershipStatus::ACTIVE, $currentMembership->status);
    }

    public function test_invalid_membership_level_id_fails_validation()
    {
        $this->actingAs($this->admin);

        $response = $this->patch(route('admin.users.update', $this->targetUser), [
            'is_commenting_blocked' => false,
            'membership_level_id' => 999999, // Non-existent ID
        ]);

        $response->assertSessionHasErrors('membership_level_id');
    }

    public function test_invalid_duration_fails_validation()
    {
        $this->actingAs($this->admin);

        $response = $this->patch(route('admin.users.update', $this->targetUser), [
            'is_commenting_blocked' => false,
            'membership_level_id' => $this->membershipLevel->id,
            'membership_duration_months' => 0, // Invalid duration
        ]);

        $response->assertSessionHasErrors('membership_duration_months');

        $response = $this->patch(route('admin.users.update', $this->targetUser), [
            'is_commenting_blocked' => false,
            'membership_level_id' => $this->membershipLevel->id,
            'membership_duration_months' => 121, // Exceeds max
        ]);

        $response->assertSessionHasErrors('membership_duration_months');
    }

    public function test_membership_assignment_stores_metadata()
    {
        $this->actingAs($this->admin);

        $response = $this->patch(route('admin.users.update', $this->targetUser), [
            'is_commenting_blocked' => false,
            'membership_level_id' => $this->membershipLevel->id,
        ]);

        $membership = $this->targetUser->fresh()->currentMembership;
        $this->assertNotNull($membership, 'Membership should be created');
        
        $metadata = $membership->subscription_metadata;
        $this->assertNotNull($metadata, 'Metadata should not be null');
        $this->assertIsArray($metadata, 'Metadata should be an array');

        $this->assertArrayHasKey('granted_by', $metadata);
        $this->assertArrayHasKey('granted_at', $metadata);
        $this->assertArrayHasKey('reason', $metadata);
        $this->assertEquals($this->admin->id, $metadata['granted_by']);
        $this->assertEquals('Admin grant from user management', $metadata['reason']);
    }

    public function test_non_admin_cannot_assign_membership()
    {
        $regularUser = User::factory()->create();
        $this->actingAs($regularUser);

        $response = $this->patch(route('admin.users.update', $this->targetUser), [
            'is_commenting_blocked' => false,
            'membership_level_id' => $this->membershipLevel->id,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_user_without_changing_membership()
    {
        $this->actingAs($this->admin);

        // Create existing membership
        $existingMembership = UserMembership::factory()->create([
            'user_id' => $this->targetUser->id,
            'membership_level_id' => $this->membershipLevel->id,
            'status' => MembershipStatus::ACTIVE,
            'expires_at' => now()->addMonths(6),
        ]);

        $response = $this->patch(route('admin.users.update', $this->targetUser), [
            'is_commenting_blocked' => true,
            // No membership_level_id provided
        ]);

        $response->assertRedirect(route('admin.users.index'));

        // Check membership remains unchanged
        $currentMembership = $this->targetUser->fresh()->currentMembership;
        $this->assertEquals($existingMembership->id, $currentMembership->id);
        $this->assertEquals(MembershipStatus::ACTIVE, $currentMembership->status);
        
        // Check user property was updated
        $this->assertTrue($this->targetUser->fresh()->is_commenting_blocked);
    }
}