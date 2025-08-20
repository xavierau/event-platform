<?php

use App\Models\Comment;
use App\Models\CommentVote;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can create a comment', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $comment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'This is a test comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    expect($comment)->toBeInstanceOf(Comment::class);
    expect($comment->content)->toBe('This is a test comment');
    expect($comment->commentable_type)->toBe(Event::class);
    expect($comment->commentable_id)->toBe($event->id);
    expect($comment->user_id)->toBe($user->id);
});

it('has polymorphic relationship with commentable', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $comment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    expect($comment->commentable)->toBeInstanceOf(Event::class);
    expect($comment->commentable->id)->toBe($event->id);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $comment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    expect($comment->user)->toBeInstanceOf(User::class);
    expect($comment->user->id)->toBe($user->id);
});

it('can have replies', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $parentComment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'Parent comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    $reply = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'Reply comment',
        'content_type' => 'plain',
        'status' => 'approved',
        'parent_id' => $parentComment->id,
    ]);

    expect($parentComment->replies)->toHaveCount(1);
    expect($reply->parent)->toBeInstanceOf(Comment::class);
    expect($reply->parent->id)->toBe($parentComment->id);
});

it('can have votes', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $comment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'approved',
        'votes_enabled' => true,
    ]);

    $vote = CommentVote::create([
        'user_id' => $user->id,
        'comment_id' => $comment->id,
        'vote_type' => 'up',
    ]);

    expect($comment->votes)->toHaveCount(1);
    expect($comment->upVotes)->toHaveCount(1);
    expect($comment->downVotes)->toHaveCount(0);
});

it('has status helper methods', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $approvedComment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'Approved comment',
        'status' => 'approved',
    ]);

    $pendingComment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'Pending comment',
        'status' => 'pending',
    ]);

    $rejectedComment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'Rejected comment',
        'status' => 'rejected',
    ]);

    expect($approvedComment->isApproved())->toBeTrue();
    expect($approvedComment->isPending())->toBeFalse();
    expect($approvedComment->isRejected())->toBeFalse();

    expect($pendingComment->isApproved())->toBeFalse();
    expect($pendingComment->isPending())->toBeTrue();
    expect($pendingComment->isRejected())->toBeFalse();

    expect($rejectedComment->isApproved())->toBeFalse();
    expect($rejectedComment->isPending())->toBeFalse();
    expect($rejectedComment->isRejected())->toBeTrue();
});

it('has default values for vote counts', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $comment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'Test comment',
        'content_type' => 'plain',
        'status' => 'approved',
    ]);

    // Refresh from database to get default values
    $comment->refresh();
    
    expect($comment->votes_up_count)->toBe(0);
    expect($comment->votes_down_count)->toBe(0);
    expect($comment->votes_enabled)->toBeFalse();
});