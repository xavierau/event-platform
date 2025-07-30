<?php

namespace Tests\Unit\Modules\Membership\Actions;

use App\Models\User;
use App\Modules\Membership\Actions\AssignMembershipLevelAction;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignMembershipLevelActionTest extends TestCase
{
    use RefreshDatabase;

    private AssignMembershipLevelAction $action;
    private User $user;
    private MembershipLevel $membershipLevel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new AssignMembershipLevelAction();
        $this->user = User::factory()->create();
        $this->membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Gold', 'zh-TW' => '金牌會員'],
            'duration_months' => 12,
        ]);

        // Mock auth user
        $adminUser = User::factory()->create();
        $this->actingAs($adminUser);
    }

    public function test_it_creates_membership_for_user()
    {
        $membership = $this->action->execute($this->user, $this->membershipLevel);

        $this->assertInstanceOf(UserMembership::class, $membership);
        $this->assertEquals($this->user->id, $membership->user_id);
        $this->assertEquals($this->membershipLevel->id, $membership->membership_level_id);
        $this->assertEquals(MembershipStatus::ACTIVE, $membership->status);
        $this->assertEquals(PaymentMethod::ADMIN_GRANT, $membership->payment_method);
        $this->assertFalse($membership->auto_renew);
    }

    public function test_it_uses_membership_level_default_duration()
    {
        $membership = $this->action->execute($this->user, $this->membershipLevel);

        $expectedExpiry = now()->addMonths($this->membershipLevel->duration_months);
        $this->assertTrue($membership->expires_at->isSameDay($expectedExpiry));
    }

    public function test_it_uses_custom_duration_when_provided()
    {
        $customDuration = 6;
        $membership = $this->action->execute($this->user, $this->membershipLevel, $customDuration);

        $expectedExpiry = now()->addMonths($customDuration);
        $this->assertTrue($membership->expires_at->isSameDay($expectedExpiry));
    }

    public function test_it_cancels_existing_active_membership()
    {
        // Create existing active membership
        $existingMembership = UserMembership::factory()->create([
            'user_id' => $this->user->id,
            'membership_level_id' => MembershipLevel::factory()->create()->id,
            'status' => MembershipStatus::ACTIVE,
            'expires_at' => now()->addMonths(3),
        ]);

        $newMembership = $this->action->execute($this->user, $this->membershipLevel);

        // Assert old membership is cancelled
        $existingMembership->refresh();
        $this->assertEquals(MembershipStatus::CANCELLED, $existingMembership->status);
        $this->assertFalse($existingMembership->auto_renew);

        // Assert new membership is active
        $this->assertEquals(MembershipStatus::ACTIVE, $newMembership->status);
    }

    public function test_it_stores_metadata_about_grant()
    {
        $membership = $this->action->execute($this->user, $this->membershipLevel);

        $metadata = $membership->subscription_metadata;
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('granted_by', $metadata);
        $this->assertArrayHasKey('granted_at', $metadata);
        $this->assertArrayHasKey('reason', $metadata);
        $this->assertEquals(auth()->user()->id, $metadata['granted_by']);
        $this->assertEquals('Admin grant from user management', $metadata['reason']);
    }

    public function test_it_creates_membership_when_no_existing_membership()
    {
        // Ensure no existing membership
        $this->assertNull($this->user->currentMembership);

        $membership = $this->action->execute($this->user, $this->membershipLevel);

        $this->assertNotNull($membership);
        $this->assertEquals($this->user->id, $membership->user_id);
        $this->assertTrue($membership->isActive());
    }

    public function test_membership_starts_immediately()
    {
        $membership = $this->action->execute($this->user, $this->membershipLevel);

        $this->assertTrue($membership->started_at->isSameDay(now()));
        $this->assertTrue($membership->started_at->lte(now()));
    }

    public function test_it_is_wrapped_in_transaction()
    {
        // Create existing membership
        $existingMembership = UserMembership::factory()->create([
            'user_id' => $this->user->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        // Mock an exception during the process
        $this->expectException(\Exception::class);

        // Attempt to execute with a membership level that will cause an error
        $invalidLevel = new MembershipLevel();
        $invalidLevel->id = 999999; // Invalid ID that doesn't exist

        try {
            $this->action->execute($this->user, $invalidLevel);
        } catch (\Exception $e) {
            // Verify existing membership wasn't modified due to transaction rollback
            $existingMembership->refresh();
            $this->assertEquals(MembershipStatus::ACTIVE, $existingMembership->status);
            throw $e;
        }
    }
}