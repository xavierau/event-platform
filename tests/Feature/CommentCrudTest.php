<?php

use App\Models\Comment;
use App\Models\Event;
use App\Models\User;
use App\Enums\CommentConfigEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

it('can create a comment via API', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/comments', [
            'commentable_type' => 'App\Models\Event',
            'commentable_id' => $event->id,
            'content' => 'This is a test comment',
            'content_type' => 'plain',
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'message',
        'comment' => [
            'id',
            'user_id',
            'commentable_type',
            'commentable_id',
            'content',
            'content_type',
            'status',
            'votes_up_count',
            'votes_down_count',
            'created_at',
            'updated_at',
            'user' => [
                'id',
                'name',
            ],
        ],
    ]);

    expect(Comment::count())->toBe(1);
    
    $comment = Comment::first();
    expect($comment->content)->toBe('This is a test comment');
    expect($comment->user_id)->toBe($user->id);
    expect($comment->commentable_type)->toBe('App\Models\Event');
    expect($comment->commentable_id)->toBe($event->id);
});

it('can list comments for an event when authenticated', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    // Create multiple comments
    Comment::create([
        'user_id' => $user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'First comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);
    
    Comment::create([
        'user_id' => $user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Second comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/api/comments?commentable_type=App\Models\Event&commentable_id={$event->id}");

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'content',
                'user',
                'created_at',
            ]
        ],
        'current_page',
        'per_page',
        'total',
    ]);

    expect($response->json('total'))->toBe(2);
});

it('can update own comment', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Original content',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($user)
        ->putJson("/api/comments/{$comment->id}", [
            'content' => 'Updated content',
            'content_type' => 'plain',
        ]);

    $response->assertSuccessful();
    
    $comment->refresh();
    expect($comment->content)->toBe('Updated content');
});

it('cannot update another users comment', function () {
    $user = User::factory()->create();
    $anotherUser = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Original content',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($anotherUser)
        ->putJson("/api/comments/{$comment->id}", [
            'content' => 'Attempted update',
        ]);

    $response->assertForbidden();
    
    $comment->refresh();
    expect($comment->content)->toBe('Original content');
});

it('can delete own comment', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Comment to delete',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($user)
        ->deleteJson("/api/comments/{$comment->id}");

    $response->assertSuccessful();
    
    expect(Comment::find($comment->id))->toBeNull();
});

it('cannot delete another users comment', function () {
    $user = User::factory()->create();
    $anotherUser = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Comment to keep',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($anotherUser)
        ->deleteJson("/api/comments/{$comment->id}");

    $response->assertForbidden();
    
    expect(Comment::find($comment->id))->not->toBeNull();
});

it('requires authentication to create comments', function () {
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);

    $response = $this->postJson('/api/comments', [
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'This should fail',
    ]);

    $response->assertUnauthorized();
});

it('validates comment creation data', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);

    // Missing content
    $response = $this->actingAs($user)
        ->postJson('/api/comments', [
            'commentable_type' => 'App\Models\Event',
            'commentable_id' => $event->id,
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['content']);

    // Invalid commentable_type
    $response = $this->actingAs($user)
        ->postJson('/api/comments', [
            'commentable_type' => 'InvalidModel',
            'commentable_id' => $event->id,
            'content' => 'Test content',
        ]);

    $response->assertUnprocessable();
});

it('cannot comment on event with comments disabled', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => false,
        'comment_config' => CommentConfigEnum::DISABLED
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/comments', [
            'commentable_type' => 'App\Models\Event',
            'commentable_id' => $event->id,
            'content' => 'This should be blocked',
        ]);

    $response->assertForbidden();
});

it('can create reply to comment', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $parentComment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Parent comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/comments', [
            'commentable_type' => 'App\Models\Event',
            'commentable_id' => $event->id,
            'content' => 'This is a reply',
            'content_type' => 'plain',
            'parent_id' => $parentComment->id,
        ]);

    $response->assertSuccessful();
    
    $reply = Comment::where('parent_id', $parentComment->id)->first();
    expect($reply)->not->toBeNull();
    expect($reply->content)->toBe('This is a reply');
    expect($reply->parent_id)->toBe($parentComment->id);
});

it('can filter comments by status', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    // Create comments with different statuses
    Comment::create([
        'user_id' => $user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Approved comment',
        'status' => 'approved',
    ]);
    
    Comment::create([
        'user_id' => $user->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Pending comment',
        'status' => 'pending',
    ]);

    // Test approved comments only (default behavior) - requires auth
    $response = $this->actingAs($user)
        ->getJson("/api/comments?commentable_type=App\Models\Event&commentable_id={$event->id}");
    $response->assertSuccessful();
    expect($response->json('total'))->toBe(1);
});