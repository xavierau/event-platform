<?php

use App\Models\Comment;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Enums\CommentConfigEnum;
use App\Enums\RoleNameEnum;
use App\Enums\OrganizerPermissionEnum;
use App\Enums\OrganizerRoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create platform admin role
    Role::create(['name' => RoleNameEnum::ADMIN->value]);
});

it('can approve a pending comment as organizer admin', function () {
    $admin = User::factory()->create();
    $commenter = User::factory()->create();
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
    ]);
    
    // Give admin permission to moderate comments
    $organizer->users()->attach($admin->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([OrganizerPermissionEnum::MODERATE_COMMENTS->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::MODERATED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment awaiting approval',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)
        ->postJson("/api/comments/{$comment->id}/approve");

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'message',
        'comment' => [
            'id',
            'status',
        ],
    ]);

    expect($response->json('comment.status'))->toBe('approved');
    
    $comment->refresh();
    expect($comment->status)->toBe('approved');
});

it('can reject a comment as organizer admin', function () {
    $admin = User::factory()->create();
    $commenter = User::factory()->create();
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
    ]);
    
    // Give admin permission to moderate comments
    $organizer->users()->attach($admin->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([OrganizerPermissionEnum::MODERATE_COMMENTS->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::MODERATED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment to reject',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)
        ->postJson("/api/comments/{$comment->id}/reject");

    $response->assertSuccessful();
    expect($response->json('comment.status'))->toBe('rejected');
    
    $comment->refresh();
    expect($comment->status)->toBe('rejected');
});

it('can flag a comment as organizer admin', function () {
    $admin = User::factory()->create();
    $commenter = User::factory()->create();
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
    ]);
    
    // Give admin permission to moderate comments
    $organizer->users()->attach($admin->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([OrganizerPermissionEnum::MODERATE_COMMENTS->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment to flag',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($admin)
        ->postJson("/api/comments/{$comment->id}/flag");

    $response->assertSuccessful();
    expect($response->json('comment.status'))->toBe('flagged');
    
    $comment->refresh();
    expect($comment->status)->toBe('flagged');
});

it('can get pending comments for moderation', function () {
    $admin = User::factory()->create();
    $commenter = User::factory()->create();
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
    ]);
    
    // Give admin permission to moderate comments
    $organizer->users()->attach($admin->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([OrganizerPermissionEnum::MODERATE_COMMENTS->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::MODERATED
    ]);
    
    // Create pending comments
    Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'First pending comment',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);
    
    Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Second pending comment',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);
    
    // Create approved comment (should not appear)
    Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Approved comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/comments/pending?commentable_type=App\\Models\\Event&commentable_id={$event->id}");

    $response->assertSuccessful();
    $response->assertJsonStructure([
        '*' => [
            'id',
            'content',
            'status',
            'user',
        ]
    ]);

    // Should only return pending comments
    expect(count($response->json()))->toBe(2);
    expect($response->json('0.status'))->toBe('pending');
    expect($response->json('1.status'))->toBe('pending');
});

it('platform admin can moderate any comment', function () {
    $platformAdmin = User::factory()->create();
    $platformAdmin->assignRole(RoleNameEnum::ADMIN->value);
    
    $commenter = User::factory()->create();
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
    ]);
    
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::MODERATED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment for platform admin',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($platformAdmin)
        ->postJson("/api/comments/{$comment->id}/approve");

    $response->assertSuccessful();
    expect($response->json('comment.status'))->toBe('approved');
});

it('cannot moderate comment without permissions', function () {
    $user = User::factory()->create();
    $commenter = User::factory()->create();
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
    ]);
    
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::MODERATED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment unauthorized user',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/api/comments/{$comment->id}/approve");

    $response->assertForbidden();
    
    $comment->refresh();
    expect($comment->status)->toBe('pending');
});

it('requires authentication for moderation actions', function () {
    $commenter = User::factory()->create();
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
    ]);
    
    $comment = Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment unauthorized',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    $response = $this->postJson("/api/comments/{$comment->id}/approve");
    $response->assertUnauthorized();

    $response = $this->postJson("/api/comments/{$comment->id}/reject");
    $response->assertUnauthorized();

    $response = $this->postJson("/api/comments/{$comment->id}/flag");
    $response->assertUnauthorized();
});

it('cannot moderate non-existent comment', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleNameEnum::ADMIN->value);

    $response = $this->actingAs($admin)
        ->postJson("/api/comments/999/approve");

    $response->assertNotFound();

    $response = $this->actingAs($admin)
        ->postJson("/api/comments/999/reject");

    $response->assertNotFound();

    $response = $this->actingAs($admin)
        ->postJson("/api/comments/999/flag");

    $response->assertNotFound();
});

it('can moderate comments on organizer directly', function () {
    $admin = User::factory()->create();
    $commenter = User::factory()->create();
    $organizer = Organizer::factory()->create();
    
    // Give admin permission to moderate comments
    $organizer->users()->attach($admin->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([OrganizerPermissionEnum::MODERATE_COMMENTS->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    
    $organizer->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::MODERATED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Organizer',
        'commentable_id' => $organizer->id,
        'content' => 'Test comment on organizer',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)
        ->postJson("/api/comments/{$comment->id}/approve");

    $response->assertSuccessful();
    expect($response->json('comment.status'))->toBe('approved');
    
    $comment->refresh();
    expect($comment->status)->toBe('approved');
});

it('can get pending comments for organizer moderation', function () {
    $admin = User::factory()->create();
    $commenter = User::factory()->create();
    $organizer = Organizer::factory()->create();
    
    // Give admin permission to moderate comments
    $organizer->users()->attach($admin->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([OrganizerPermissionEnum::MODERATE_COMMENTS->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    
    $organizer->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::MODERATED
    ]);
    
    // Create pending comments on organizer
    Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Organizer',
        'commentable_id' => $organizer->id,
        'content' => 'First pending organizer comment',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);
    
    Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Organizer',
        'commentable_id' => $organizer->id,
        'content' => 'Second pending organizer comment',
        'content_type' => 'plain',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/comments/pending?commentable_type=App\\Models\\Organizer&commentable_id={$organizer->id}");

    $response->assertSuccessful();
    expect(count($response->json()))->toBe(2);
    expect($response->json('0.status'))->toBe('pending');
});

it('cannot approve already approved comment', function () {
    $admin = User::factory()->create();
    $commenter = User::factory()->create();
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
    ]);
    
    // Give admin permission to moderate comments
    $organizer->users()->attach($admin->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([OrganizerPermissionEnum::MODERATE_COMMENTS->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commenter->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Already approved comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($admin)
        ->postJson("/api/comments/{$comment->id}/approve");

    // Should still succeed but comment status remains the same
    $response->assertSuccessful();
    expect($response->json('comment.status'))->toBe('approved');
});

it('validates pending comments request parameters', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleNameEnum::ADMIN->value);

    // Missing commentable_type
    $response = $this->actingAs($admin)
        ->getJson("/api/comments/pending?commentable_id=1");
    
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['commentable_type']);

    // Missing commentable_id
    $response = $this->actingAs($admin)
        ->getJson("/api/comments/pending?commentable_type=App\\Models\\Event");
    
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['commentable_id']);

    // Invalid commentable_type
    $response = $this->actingAs($admin)
        ->getJson("/api/comments/pending?commentable_type=InvalidModel&commentable_id=1");
    
    $response->assertUnprocessable();
});