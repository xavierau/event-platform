<?php

use App\Models\Comment;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Policies\CommentPolicy;
use App\Enums\CommentConfigEnum;
use App\Enums\RoleNameEnum;
use App\Enums\OrganizerPermissionEnum;
use App\Enums\OrganizerRoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new CommentPolicy();
    
    // Create platform admin role
    Role::create(['name' => RoleNameEnum::ADMIN->value]);
    
    // Create test users
    $this->user = User::factory()->create();
    $this->anotherUser = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleNameEnum::ADMIN->value);
    
    // Create organizer
    $this->organizer = Organizer::factory()->create();
    $this->event = Event::factory()->create([
        'organizer_id' => $this->organizer->id,
    ]);
    
    // Create organizer members with different permissions
    $this->organizerModerator = User::factory()->create();
    $this->organizer->users()->attach($this->organizerModerator->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([OrganizerPermissionEnum::MODERATE_COMMENTS->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    
    $this->organizerMember = User::factory()->create();
    $this->organizer->users()->attach($this->organizerMember->id, [
        'role_in_organizer' => OrganizerRoleEnum::STAFF->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
});

it('allows viewAny for any user', function () {
    expect($this->policy->viewAny($this->user))->toBeTrue();
    expect($this->policy->viewAny($this->admin))->toBeTrue();
});

it('allows viewing approved comments for any user', function () {
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    expect($this->policy->view($this->user, $comment))->toBeTrue();
    expect($this->policy->view($this->anotherUser, $comment))->toBeTrue();
    expect($this->policy->view($this->admin, $comment))->toBeTrue();
});

it('allows viewing own comments regardless of status', function () {
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    expect($this->policy->view($this->user, $comment))->toBeTrue();
    expect($this->policy->view($this->anotherUser, $comment))->toBeFalse();
});

it('allows moderators to view pending comments', function () {
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    expect($this->policy->view($this->organizerModerator, $comment))->toBeTrue();
    expect($this->policy->view($this->admin, $comment))->toBeTrue();
    expect($this->policy->view($this->organizerMember, $comment))->toBeFalse();
});

it('allows creating comments when comments are enabled', function () {
    $this->event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);

    $result = $this->policy->create($this->user, 'App\Models\Event', $this->event->id);
    expect($result->allowed())->toBeTrue();
});

it('denies creating comments when comments are disabled', function () {
    $this->event->update([
        'comments_enabled' => false,
        'comment_config' => CommentConfigEnum::DISABLED
    ]);

    $result = $this->policy->create($this->user, 'App\Models\Event', $this->event->id);
    expect($result->allowed())->toBeFalse();
    expect($result->message())->toBe('Comments are disabled for this item.');
});

it('denies creating comments when event comment config is disabled', function () {
    $this->event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::DISABLED
    ]);

    $result = $this->policy->create($this->user, 'App\Models\Event', $this->event->id);
    expect($result->allowed())->toBeFalse();
    expect($result->message())->toBe('Comments are disabled for this event.');
});

it('denies creating comments for blocked users', function () {
    $this->event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $this->user->is_commenting_blocked = true;
    $this->user->save();

    $result = $this->policy->create($this->user, 'App\Models\Event', $this->event->id);
    expect($result->allowed())->toBeFalse();
    expect($result->message())->toBe('You are blocked from commenting.');
});

it('denies creating comments for non-existent entity', function () {
    $result = $this->policy->create($this->user, 'App\Models\Event', 999);
    expect($result->allowed())->toBeFalse();
    expect($result->message())->toBe('The item you are trying to comment on does not exist.');
});

it('allows updating own comments', function () {
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    expect($this->policy->update($this->user, $comment))->toBeTrue();
    expect($this->policy->update($this->anotherUser, $comment))->toBeFalse();
});

it('allows deleting own comments', function () {
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    expect($this->policy->delete($this->user, $comment))->toBeTrue();
    expect($this->policy->delete($this->anotherUser, $comment))->toBeFalse();
});

it('allows moderators to delete comments', function () {
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    expect($this->policy->delete($this->organizerModerator, $comment))->toBeTrue();
    expect($this->policy->delete($this->admin, $comment))->toBeTrue();
    expect($this->policy->delete($this->organizerMember, $comment))->toBeFalse();
});

it('allows voting on approved comments by others with voting enabled', function () {
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'approved',
        'votes_enabled' => true,
    ]);

    expect($this->policy->vote($this->anotherUser, $comment))->toBeTrue();
    expect($this->policy->vote($this->user, $comment))->toBeFalse(); // Cannot vote on own comment
});

it('denies voting on comments with voting disabled', function () {
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'approved',
        'votes_enabled' => false,
    ]);

    expect($this->policy->vote($this->anotherUser, $comment))->toBeFalse();
});

it('denies voting on non-approved comments', function () {
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'pending',
        'votes_enabled' => true,
    ]);

    expect($this->policy->vote($this->anotherUser, $comment))->toBeFalse();
});

it('allows moderating comments with proper permissions', function () {
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    expect($this->policy->moderate($this->organizerModerator, $comment))->toBeTrue();
    expect($this->policy->moderate($this->admin, $comment))->toBeTrue();
    expect($this->policy->moderate($this->organizerMember, $comment))->toBeFalse();
    expect($this->policy->moderate($this->user, $comment))->toBeFalse();
});

it('handles organizer comment moderation permissions', function () {
    $organizerComment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Organizer',
        'commentable_id' => $this->organizer->id,
        'content' => 'Test organizer comment',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    expect($this->policy->moderate($this->organizerModerator, $organizerComment))->toBeTrue();
    expect($this->policy->moderate($this->admin, $organizerComment))->toBeTrue();
    expect($this->policy->moderate($this->organizerMember, $organizerComment))->toBeFalse();
});

it('allows moderators to restore comments', function () {
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    expect($this->policy->restore($this->organizerModerator, $comment))->toBeTrue();
    expect($this->policy->restore($this->admin, $comment))->toBeTrue();
    expect($this->policy->restore($this->organizerMember, $comment))->toBeFalse();
});

it('denies force delete to non-platform admins', function () {
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    expect($this->policy->forceDelete($this->admin, $comment))->toBeFalse(); // Admin needs specific permission
    expect($this->policy->forceDelete($this->organizerModerator, $comment))->toBeFalse();
    expect($this->policy->forceDelete($this->user, $comment))->toBeFalse();
});

it('handles moderation permissions for events from deleted organizer', function () {
    // Create event with different organizer, then delete that organizer
    // to simulate a detached commentable
    $anotherOrganizer = Organizer::factory()->create();
    $eventWithDeletedOrganizer = Event::factory()->create([
        'organizer_id' => $anotherOrganizer->id,
    ]);
    
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $eventWithDeletedOrganizer->id,
        'content' => 'Test comment on event with deleted organizer',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    // Delete the organizer after creating the comment
    $anotherOrganizer->delete();

    // Only platform admin should be able to moderate
    expect($this->policy->moderate($this->admin, $comment))->toBeTrue();
    expect($this->policy->moderate($this->organizerModerator, $comment))->toBeFalse();
    expect($this->policy->moderate($this->user, $comment))->toBeFalse();
});

it('handles comment creation on organizer entities', function () {
    $this->organizer->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);

    $result = $this->policy->create($this->user, 'App\Models\Organizer', $this->organizer->id);
    expect($result->allowed())->toBeTrue();
});

it('denies comment creation on organizer when disabled', function () {
    $this->organizer->update([
        'comments_enabled' => false,
        'comment_config' => CommentConfigEnum::DISABLED
    ]);

    $result = $this->policy->create($this->user, 'App\Models\Organizer', $this->organizer->id);
    expect($result->allowed())->toBeFalse();
});

it('handles moderation permissions across different organizers', function () {
    $anotherOrganizer = Organizer::factory()->create();
    $anotherEvent = Event::factory()->create([
        'organizer_id' => $anotherOrganizer->id,
    ]);
    
    $comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $anotherEvent->id,
        'content' => 'Test comment on different organizer event',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    // Moderator from different organizer should not be able to moderate
    expect($this->policy->moderate($this->organizerModerator, $comment))->toBeFalse();
    expect($this->policy->moderate($this->admin, $comment))->toBeTrue(); // Platform admin can moderate anywhere
});

it('handles permission inheritance for child comments', function () {
    $parentComment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Parent comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    $childComment = Comment::create([
        'user_id' => $this->anotherUser->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $this->event->id,
        'content' => 'Child comment',
        'content_type' => 'plain',
        'status' => 'pending',
        'parent_id' => $parentComment->id,
    ]);

    // Same moderation rules should apply
    expect($this->policy->moderate($this->organizerModerator, $childComment))->toBeTrue();
    expect($this->policy->moderate($this->organizerMember, $childComment))->toBeFalse();
});