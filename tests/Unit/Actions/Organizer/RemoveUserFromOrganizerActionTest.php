<?php

namespace Tests\Unit\Actions\Organizer;

use App\Actions\Organizer\RemoveUserFromOrganizerAction;
use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RemoveUserFromOrganizerActionTest extends TestCase
{
    use RefreshDatabase;

    private RemoveUserFromOrganizerAction $action;
    private Organizer $organizer;
    private User $owner;
    private User $manager;
    private User $staff;
    private User $targetUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new RemoveUserFromOrganizerAction();
        $this->organizer = Organizer::factory()->create();
        $this->owner = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->staff = User::factory()->create();
        $this->targetUser = User::factory()->create();

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

        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'viewer',
            'is_active' => true,
            'invited_by' => $this->manager->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        Notification::fake();
    }

    public function test_owner_can_remove_any_user()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->targetUser->id,
            removedBy: $this->owner->id
        );

        $this->assertTrue($result);

        // User should no longer be active in organizer
        $membership = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $this->assertFalse((bool) $membership->pivot->is_active);
    }

    public function test_manager_can_remove_staff_and_viewers()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->targetUser->id, // viewer
            removedBy: $this->manager->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $this->assertFalse((bool) $membership->pivot->is_active);
    }

    public function test_manager_can_remove_staff()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->staff->id,
            removedBy: $this->manager->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->staff->id)->first();
        $this->assertFalse((bool) $membership->pivot->is_active);
    }

    public function test_manager_cannot_remove_other_managers()
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
        $this->expectExceptionMessage('Insufficient permissions to remove this user');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $anotherManager->id,
            removedBy: $this->manager->id
        );
    }

    public function test_manager_cannot_remove_owners()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Insufficient permissions to remove this user');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->owner->id,
            removedBy: $this->manager->id
        );
    }

    public function test_staff_cannot_remove_anyone()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Insufficient permissions to remove users');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->targetUser->id,
            removedBy: $this->staff->id
        );
    }

    public function test_cannot_remove_last_owner()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Cannot remove the last owner of the organizer');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->owner->id,
            removedBy: $this->owner->id
        );
    }

    public function test_can_remove_owner_if_other_owners_exist()
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
            userToRemoveId: $this->owner->id,
            removedBy: $anotherOwner->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->owner->id)->first();
        $this->assertFalse((bool) $membership->pivot->is_active);
    }

    public function test_user_can_remove_themselves()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->targetUser->id,
            removedBy: $this->targetUser->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $this->assertFalse((bool) $membership->pivot->is_active);
    }

    public function test_owner_cannot_remove_self_if_last_owner()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Cannot remove the last owner of the organizer');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->owner->id,
            removedBy: $this->owner->id
        );
    }

    public function test_fails_when_organizer_not_found()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Organizer not found');

        $this->action->execute(
            organizerId: 999999,
            userToRemoveId: $this->targetUser->id,
            removedBy: $this->owner->id
        );
    }

    public function test_fails_when_user_to_remove_not_found()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User to remove not found');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: 999999,
            removedBy: $this->owner->id
        );
    }

    public function test_fails_when_remover_not_found()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User performing removal not found');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->targetUser->id,
            removedBy: 999999
        );
    }

    public function test_fails_when_user_not_member_of_organizer()
    {
        $outsideUser = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User is not a member of this organizer');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $outsideUser->id,
            removedBy: $this->owner->id
        );
    }

    public function test_fails_when_remover_not_member_of_organizer()
    {
        $outsideUser = User::factory()->create();

        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('User performing removal is not a member of this organizer');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->targetUser->id,
            removedBy: $outsideUser->id
        );
    }

    public function test_fails_when_user_already_inactive()
    {
        // Make target user inactive
        $this->organizer->users()->updateExistingPivot($this->targetUser->id, [
            'is_active' => false,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User is already inactive in this organizer');

        $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->targetUser->id,
            removedBy: $this->owner->id
        );
    }

    public function test_sends_removal_notification_to_removed_user()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->targetUser->id,
            removedBy: $this->owner->id
        );

        $this->assertTrue($result);

        // Verify removal notification was sent to removed user
        Notification::assertSentTo($this->targetUser, \App\Notifications\UserRemovedFromOrganizerNotification::class);
    }

    public function test_sends_removal_notification_to_other_owners_and_managers()
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
            userToRemoveId: $this->targetUser->id,
            removedBy: $this->owner->id
        );

        $this->assertTrue($result);

        // Verify notification was sent to other admins
        Notification::assertSentTo($anotherOwner, \App\Notifications\TeamMemberRemovedNotification::class);
        Notification::assertSentTo($anotherManager, \App\Notifications\TeamMemberRemovedNotification::class);
        Notification::assertSentTo($this->manager, \App\Notifications\TeamMemberRemovedNotification::class);
    }

    public function test_does_not_send_notification_to_remover()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->targetUser->id,
            removedBy: $this->owner->id
        );

        $this->assertTrue($result);

        // Verify remover doesn't get notification about their own action
        Notification::assertNotSentTo($this->owner, \App\Notifications\TeamMemberRemovedNotification::class);
    }

    public function test_soft_removal_preserves_historical_data()
    {
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->targetUser->id,
            removedBy: $this->owner->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();

        // Historical data should be preserved
        $this->assertNotNull($membership->pivot->joined_at);
        $this->assertNotNull($membership->pivot->invitation_accepted_at);
        $this->assertNotNull($membership->pivot->invited_by);
        $this->assertEquals('viewer', $membership->pivot->role_in_organizer);

        // Removal data should be set
        $this->assertFalse((bool) $membership->pivot->is_active);
    }

    public function test_can_remove_user_with_custom_permissions()
    {
        // Update target user with custom permissions
        $this->organizer->users()->updateExistingPivot($this->targetUser->id, [
            'permissions' => json_encode(['create_events', 'view_analytics']),
        ]);

        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->targetUser->id,
            removedBy: $this->owner->id
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $this->assertFalse((bool) $membership->pivot->is_active);

        // Custom permissions should be preserved for historical purposes
        $this->assertEquals(['create_events', 'view_analytics'], json_decode($membership->pivot->permissions, true));
    }

    public function test_removal_with_reason_parameter()
    {
        $reason = 'User violated team policies';

        $result = $this->action->executeWithReason(
            organizerId: $this->organizer->id,
            userToRemoveId: $this->targetUser->id,
            removedBy: $this->owner->id,
            reason: $reason
        );

        $this->assertTrue($result);

        $membership = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $this->assertFalse((bool) $membership->pivot->is_active);
        // Note: We'd need to add a removal_reason column to fully test this
    }
}
