<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Membership\Actions\AssignMembershipLevelAction;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMembershipAssignmentSimpleTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_membership_level_action_works()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $this->actingAs($admin);
        
        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Premium', 'zh-TW' => '高級會員'],
            'duration_months' => 12,
            'is_active' => true,
        ]);

        $action = new AssignMembershipLevelAction();
        $membership = $action->execute($user, $membershipLevel);

        $this->assertInstanceOf(UserMembership::class, $membership);
        $this->assertEquals($user->id, $membership->user_id);
        $this->assertEquals($membershipLevel->id, $membership->membership_level_id);
        $this->assertEquals(MembershipStatus::ACTIVE, $membership->status);
        $this->assertEquals(PaymentMethod::ADMIN_GRANT, $membership->payment_method);
        $this->assertFalse($membership->auto_renew);
        
        // Check metadata
        $metadata = $membership->subscription_metadata;
        $this->assertArrayHasKey('granted_by', $metadata);
        $this->assertArrayHasKey('granted_at', $metadata);
        $this->assertArrayHasKey('reason', $metadata);
        $this->assertEquals($admin->id, $metadata['granted_by']);
    }

    public function test_assign_membership_cancels_existing_membership()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $this->actingAs($admin);
        
        $level1 = MembershipLevel::factory()->create(['duration_months' => 6]);
        $level2 = MembershipLevel::factory()->create(['duration_months' => 12]);

        // Create existing membership
        $existingMembership = UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $level1->id,
            'status' => MembershipStatus::ACTIVE,
            'expires_at' => now()->addMonths(3),
        ]);

        $action = new AssignMembershipLevelAction();
        $newMembership = $action->execute($user, $level2);

        // Check old membership is cancelled
        $this->assertEquals(MembershipStatus::CANCELLED, $existingMembership->fresh()->status);
        $this->assertFalse($existingMembership->fresh()->auto_renew);

        // Check new membership is active
        $this->assertEquals(MembershipStatus::ACTIVE, $newMembership->status);
        $this->assertEquals($level2->id, $newMembership->membership_level_id);
    }

    public function test_assign_membership_with_custom_duration()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $this->actingAs($admin);
        
        $membershipLevel = MembershipLevel::factory()->create(['duration_months' => 12]);
        $customDuration = 6;

        $action = new AssignMembershipLevelAction();
        $membership = $action->execute($user, $membershipLevel, $customDuration);

        $expectedExpiry = now()->addMonths($customDuration);
        $this->assertTrue($membership->expires_at->isSameDay($expectedExpiry));
    }
}