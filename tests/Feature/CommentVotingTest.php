<?php

use App\Models\Comment;
use App\Models\CommentVote;
use App\Models\Event;
use App\Models\User;
use App\Enums\CommentConfigEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

it('can vote up on a comment', function () {
    $commentAuthor = User::factory()->create();
    $voter = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commentAuthor->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment for voting',
        'content_type' => 'plain',
        'status' => 'approved',
        'votes_enabled' => true,
    ]);

    $response = $this->actingAs($voter)
        ->postJson("/api/comments/{$comment->id}/vote", [
            'vote_type' => 'up',
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'message',
        'vote' => [
            'id',
            'vote_type',
        ],
        'comment' => [
            'id',
            'votes_up_count',
            'votes_down_count',
        ],
    ]);

    expect($response->json('comment.votes_up_count'))->toBe(1);
    expect($response->json('comment.votes_down_count'))->toBe(0);
    expect($response->json('vote.vote_type'))->toBe('up');

    // Verify in database
    expect(CommentVote::count())->toBe(1);
    $vote = CommentVote::first();
    expect($vote->user_id)->toBe($voter->id);
    expect($vote->comment_id)->toBe($comment->id);
    expect($vote->vote_type)->toBe('up');
});

it('can vote down on a comment', function () {
    $commentAuthor = User::factory()->create();
    $voter = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commentAuthor->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment for voting',
        'content_type' => 'plain',
        'status' => 'approved',
        'votes_enabled' => true,
    ]);

    $response = $this->actingAs($voter)
        ->postJson("/api/comments/{$comment->id}/vote", [
            'vote_type' => 'down',
        ]);

    $response->assertSuccessful();
    expect($response->json('comment.votes_up_count'))->toBe(0);
    expect($response->json('comment.votes_down_count'))->toBe(1);
    expect($response->json('vote.vote_type'))->toBe('down');
});

it('can change vote from up to down', function () {
    $commentAuthor = User::factory()->create();
    $voter = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commentAuthor->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment for voting',
        'content_type' => 'plain',
        'status' => 'approved',
        'votes_enabled' => true,
    ]);

    // First vote up
    $response = $this->actingAs($voter)
        ->postJson("/api/comments/{$comment->id}/vote", [
            'vote_type' => 'up',
        ]);
    $response->assertSuccessful();

    // Change to down vote
    $response = $this->actingAs($voter)
        ->postJson("/api/comments/{$comment->id}/vote", [
            'vote_type' => 'down',
        ]);

    $response->assertSuccessful();
    expect($response->json('comment.votes_up_count'))->toBe(0);
    expect($response->json('comment.votes_down_count'))->toBe(1);
    expect($response->json('vote.vote_type'))->toBe('down');

    // Should still be only one vote record
    expect(CommentVote::count())->toBe(1);
    expect(CommentVote::first()->vote_type)->toBe('down');
});

it('can remove vote by voting same type again', function () {
    $commentAuthor = User::factory()->create();
    $voter = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commentAuthor->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment for voting',
        'content_type' => 'plain',
        'status' => 'approved',
        'votes_enabled' => true,
    ]);

    // First vote up
    $response = $this->actingAs($voter)
        ->postJson("/api/comments/{$comment->id}/vote", [
            'vote_type' => 'up',
        ]);
    $response->assertSuccessful();

    // Vote up again to remove
    $response = $this->actingAs($voter)
        ->postJson("/api/comments/{$comment->id}/vote", [
            'vote_type' => 'up',
        ]);

    $response->assertSuccessful();
    expect($response->json('comment.votes_up_count'))->toBe(0);
    expect($response->json('comment.votes_down_count'))->toBe(0);

    // Vote should be deleted
    expect(CommentVote::count())->toBe(0);
});

it('cannot vote on comment with voting disabled', function () {
    $commentAuthor = User::factory()->create();
    $voter = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commentAuthor->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment for voting',
        'content_type' => 'plain',
        'status' => 'approved',
        'votes_enabled' => false, // Voting disabled
    ]);

    $response = $this->actingAs($voter)
        ->postJson("/api/comments/{$comment->id}/vote", [
            'vote_type' => 'up',
        ]);

    $response->assertForbidden();
});

it('requires authentication to vote', function () {
    $commentAuthor = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commentAuthor->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment for voting',
        'content_type' => 'plain',
        'status' => 'approved',
        'votes_enabled' => true,
    ]);

    $response = $this->postJson("/api/comments/{$comment->id}/vote", [
        'vote_type' => 'up',
    ]);

    $response->assertUnauthorized();
});

it('validates vote type', function () {
    $commentAuthor = User::factory()->create();
    $voter = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commentAuthor->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment for voting',
        'content_type' => 'plain',
        'status' => 'approved',
        'votes_enabled' => true,
    ]);

    // Invalid vote type
    $response = $this->actingAs($voter)
        ->postJson("/api/comments/{$comment->id}/vote", [
            'vote_type' => 'invalid',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['vote_type']);

    // Missing vote type
    $response = $this->actingAs($voter)
        ->postJson("/api/comments/{$comment->id}/vote", []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['vote_type']);
});

it('updates comment vote counts correctly', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();
    
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $commentAuthor = User::factory()->create();
    $comment = Comment::create([
        'user_id' => $commentAuthor->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment for voting',
        'content_type' => 'plain',
        'status' => 'approved',
        'votes_enabled' => true,
    ]);

    // User 1 votes up
    $this->actingAs($user1)
        ->postJson("/api/comments/{$comment->id}/vote", ['vote_type' => 'up'])
        ->assertSuccessful();

    // User 2 votes up
    $this->actingAs($user2)
        ->postJson("/api/comments/{$comment->id}/vote", ['vote_type' => 'up'])
        ->assertSuccessful();

    // User 3 votes down
    $response = $this->actingAs($user3)
        ->postJson("/api/comments/{$comment->id}/vote", ['vote_type' => 'down']);

    $response->assertSuccessful();
    expect($response->json('comment.votes_up_count'))->toBe(2);
    expect($response->json('comment.votes_down_count'))->toBe(1);

    // Verify comment model is updated
    $comment->refresh();
    expect($comment->votes_up_count)->toBe(2);
    expect($comment->votes_down_count)->toBe(1);
});

it('cannot vote on non-existent comment', function () {
    $voter = User::factory()->create();

    $response = $this->actingAs($voter)
        ->postJson("/api/comments/999/vote", [
            'vote_type' => 'up',
        ]);

    $response->assertNotFound();
});

it('cannot vote on rejected comment', function () {
    $commentAuthor = User::factory()->create();
    $voter = User::factory()->create();
    $event = Event::factory()->create();
    $event->update([
        'comments_enabled' => true,
        'comment_config' => CommentConfigEnum::ENABLED
    ]);
    
    $comment = Comment::create([
        'user_id' => $commentAuthor->id,
        'commentable_type' => 'App\Models\Event',
        'commentable_id' => $event->id,
        'content' => 'Test comment for voting',
        'content_type' => 'plain',
        'status' => 'rejected', // Rejected comment
        'votes_enabled' => true,
    ]);

    $response = $this->actingAs($voter)
        ->postJson("/api/comments/{$comment->id}/vote", [
            'vote_type' => 'up',
        ]);

    $response->assertForbidden();
});

it('cannot vote on own comment', function () {
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
        'content' => 'My own comment',
        'content_type' => 'plain',
        'status' => 'approved',
        'votes_enabled' => true,
    ]);

    $response = $this->actingAs($user)
        ->postJson("/api/comments/{$comment->id}/vote", [
            'vote_type' => 'up',
        ]);

    $response->assertForbidden();
});