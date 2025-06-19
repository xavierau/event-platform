<?php

namespace Tests\Unit\Actions\Organizer;

use App\Actions\Organizer\UpdateOrganizerUserRoleAction;
use App\DataTransferObjects\Organizer\OrganizerUserData;
use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UpdateOrganizerUserRoleActionTest extends TestCase
{
    use RefreshDatabase;

    private UpdateOrganizerUserRoleAction $action;
    private Organizer $organizer;
    private User $owner;
    private User $manager;
    private User $staff;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateOrganizerUserRoleAction();
        $this->organizer = Organizer::factory()->create();
        $this->owner = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->staff = User::factory()->create();
        $this->viewer = User::factory()->create();

        // Set up team hierarchy
        $this->organizer->users()->attach($this->owner->id, [
            'role_in_organizer' => 'owner',
            'is_active' => true,
            'invited_by' => $this->owner->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $this->organizer->users()->attach($this->manager->id, [
            'role_in_organizer' => 'manager',
            'is_active' => true,
            'invited_by' => $this->owner->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $this->organizer->users()->attach($this->staff->id, [
            'role_in_organizer' => 'staff',
            'is_active' => true,
            'invited_by' => $this->owner->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $this->organizer->users()->attach($this->viewer->id, [
            'role_in_organizer' => 'viewer',
            'is_active' => true,
            'invited_by' => $this->manager->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        Notification::fake();
    }

    public function test_owner_can_promote_any_user()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->viewer->id,
            newRole: 'manager',
            updatedBy: $this->owner->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->viewer->id)->first();
        $this->assertEquals('manager', $membership->pivot->role_in_organizer);
    }

    public function test_owner_can_demote_any_user()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->manager->id,
            newRole: 'staff',
            updatedBy: $this->owner->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->manager->id)->first();
        $this->assertEquals('staff', $membership->pivot->role_in_organizer);
    }

    public function test_manager_can_promote_staff_and_viewers()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->viewer->id,
            newRole: 'staff',
            updatedBy: $this->manager->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->viewer->id)->first();
        $this->assertEquals('staff', $membership->pivot->role_in_organizer);
    }

    public function test_manager_can_demote_staff_to_viewer()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->staff->id,
            newRole: 'viewer',
            updatedBy: $this->manager->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->staff->id)->first();
        $this->assertEquals('viewer', $membership->pivot->role_in_organizer);
    }

    public function test_manager_cannot_promote_to_owner()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Insufficient permissions to assign owner role');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->staff->id,
            newRole: 'owner',
            updatedBy: $this->manager->id
        );
    }

    public function test_manager_cannot_promote_to_manager()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Insufficient permissions to assign manager role');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->staff->id,
            newRole: 'manager',
            updatedBy: $this->manager->id
        );
    }

    public function test_manager_cannot_modify_other_managers()
    {
        $anotherManager = User::factory()->create();
        $this->organizer->users()->attach($anotherManager->id, [
            'role_in_organizer' => 'manager',
            'is_active' => true,
            'invited_by' => $this->owner->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Insufficient permissions to modify this user\'s role');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $anotherManager->id,
            newRole: 'staff',
            updatedBy: $this->manager->id
        );
    }

    public function test_manager_cannot_modify_owners()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Insufficient permissions to modify this user\'s role');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->owner->id,
            newRole: 'manager',
            updatedBy: $this->manager->id
        );
    }

    public function test_staff_cannot_update_any_roles()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Insufficient permissions to update user roles');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->viewer->id,
            newRole: 'staff',
            updatedBy: $this->staff->id
        );
    }

    public function test_viewer_cannot_update_any_roles()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Insufficient permissions to update user roles');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->staff->id,
            newRole: 'viewer',
            updatedBy: $this->viewer->id
        );
    }

    public function test_cannot_demote_last_owner()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Cannot demote the last owner of the organizer');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->owner->id,
            newRole: 'manager',
            updatedBy: $this->owner->id
        );
    }

    public function test_can_demote_owner_if_other_owners_exist()
    {
        // Add another owner
        $anotherOwner = User::factory()->create();
        $this->organizer->users()->attach($anotherOwner->id, [
            'role_in_organizer' => 'owner',
            'is_active' => true,
            'invited_by' => $this->owner->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->owner->id,
            newRole: 'manager',
            updatedBy: $anotherOwner->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->owner->id)->first();
        $this->assertEquals('manager', $membership->pivot->role_in_organizer);
    }

    public function test_owner_cannot_demote_self_if_last_owner()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Cannot demote the last owner of the organizer');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->owner->id,
            newRole: 'manager',
            updatedBy: $this->owner->id
        );
    }

    public function test_update_role_with_custom_permissions()
    {
        $customPermissions = ['create_events', 'edit_events', 'view_analytics'];

        $result = $this->action->executeWithPermissions(
            organizerId: $this->organizer->id,
            userId: $this->staff->id,
            newRole: 'staff',
            customPermissions: $customPermissions,
            updatedBy: $this->owner->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->staff->id)->first();
        $this->assertEquals('staff', $membership->pivot->role_in_organizer);
        $this->assertEquals($customPermissions, json_decode($membership->pivot->permissions, true));
    }

    public function test_update_role_clears_custom_permissions_when_not_provided()
    {
        // First set custom permissions
        $this->organizer->users()->updateExistingPivot($this->staff->id, [
            'permissions' => json_encode(['create_events', 'view_analytics']),
        ]);

        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->staff->id,
            newRole: 'manager',
            updatedBy: $this->owner->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->staff->id)->first();
        $this->assertEquals('manager', $membership->pivot->role_in_organizer);
        $this->assertNull($membership->pivot->permissions);
    }

    public function test_fails_when_organizer_not_found()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Organizer not found');

        $this->action->execute(
            organizerId: 999999,
            userId: $this->staff->id,
            newRole: 'manager',
            updatedBy: $this->owner->id
        );
    }

    public function test_fails_when_user_not_found()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User not found');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: 999999,
            newRole: 'manager',
            updatedBy: $this->owner->id
        );
    }

    public function test_fails_when_updater_not_found()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User performing update not found');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->staff->id,
            newRole: 'manager',
            updatedBy: 999999
        );
    }

    public function test_fails_when_user_not_member_of_organizer()
    {
        $outsideUser = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User is not a member of this organizer');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $outsideUser->id,
            newRole: 'staff',
            updatedBy: $this->owner->id
        );
    }

    public function test_fails_when_updater_not_member_of_organizer()
    {
        $outsideUser = User::factory()->create();

        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('User performing update is not a member of this organizer');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->staff->id,
            newRole: 'manager',
            updatedBy: $outsideUser->id
        );
    }

    public function test_fails_when_user_is_inactive()
    {
        // Make staff inactive
        $this->organizer->users()->updateExistingPivot($this->staff->id, [
            'is_active' => false,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot update role of inactive user');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->staff->id,
            newRole: 'manager',
            updatedBy: $this->owner->id
        );
    }

    public function test_fails_with_invalid_role()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid role provided');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->staff->id,
            newRole: 'invalid_role',
            updatedBy: $this->owner->id
        );
    }

    public function test_no_change_when_role_is_same()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->staff->id,
            newRole: 'staff', // Same role
            updatedBy: $this->owner->id
        );

        $this->assertTrue($result);

        // Should still succeed but no change occurred
        $membership = $this->organizer->users()->where('user_id', $this->staff->id)->first();
        $this->assertEquals('staff', $membership->pivot->role_in_organizer);
    }

    public function test_sends_role_update_notification_to_user()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->viewer->id,
            newRole: 'staff',
            updatedBy: $this->owner->id
        );

        $this->assertTrue($result);

        // Verify role update notification was sent to user
        Notification::assertSentTo($this->viewer, \App\Notifications\RoleUpdatedNotification::class);
    }

    public function test_sends_role_update_notification_to_admins()
    {
        // Add another owner and manager
        $anotherOwner = User::factory()->create();
        $anotherManager = User::factory()->create();

        $this->organizer->users()->attach($anotherOwner->id, [
            'role_in_organizer' => 'owner',
            'is_active' => true,
            'invited_by' => $this->owner->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $this->organizer->users()->attach($anotherManager->id, [
            'role_in_organizer' => 'manager',
            'is_active' => true,
            'invited_by' => $this->owner->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->viewer->id,
            newRole: 'manager',
            updatedBy: $this->owner->id
        );

        $this->assertTrue($result);

        // Verify notification was sent to other admins
        Notification::assertSentTo($anotherOwner, \App\Notifications\TeamMemberRoleChangedNotification::class);
        Notification::assertSentTo($anotherManager, \App\Notifications\TeamMemberRoleChangedNotification::class);
        Notification::assertSentTo($this->manager, \App\Notifications\TeamMemberRoleChangedNotification::class);
    }

    public function test_does_not_send_admin_notification_to_updater()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->viewer->id,
            newRole: 'staff',
            updatedBy: $this->owner->id
        );

        $this->assertTrue($result);

        // Verify updater doesn't get admin notification about their own action
        Notification::assertNotSentTo($this->owner, \App\Notifications\TeamMemberRoleChangedNotification::class);
    }

    public function test_execute_with_organizer_user_data()
    {
        $organizerUserData = OrganizerUserData::forRoleUpdate(
            organizerId: $this->organizer->id,
            userId: $this->viewer->id,
            newRole: 'staff',
            customPermissions: ['create_events'],
            updatedBy: $this->owner->id
        );

        $result = $this->action->executeWithData($organizerUserData);

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->viewer->id)->first();
        $this->assertEquals('staff', $membership->pivot->role_in_organizer);
        $this->assertEquals(['create_events'], json_decode($membership->pivot->permissions, true));
    }

    public function test_returns_updated_organizer_user_data()
    {
        $result = $this->action->executeAndReturnData(
            organizerId: $this->organizer->id,
            userId: $this->viewer->id,
            newRole: 'staff',
            updatedBy: $this->owner->id
        );

        $this->assertInstanceOf(OrganizerUserData::class, $result);
        $this->assertEquals($this->organizer->id, $result->organizer_id);
        $this->assertEquals($this->viewer->id, $result->user_id);
        $this->assertEquals('staff', $result->role_in_organizer);
        $this->assertTrue($result->is_active);
    }

    public function test_tracks_role_change_history()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->viewer->id,
            newRole: 'manager',
            updatedBy: $this->owner->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->viewer->id)->first();
        $this->assertEquals('manager', $membership->pivot->role_in_organizer);
        $this->assertNotNull($membership->pivot->updated_at);
        // Note: Full history tracking would require additional role_changes table
    }
}
