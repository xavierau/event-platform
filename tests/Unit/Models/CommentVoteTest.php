<?php

use App\Models\Comment;
use App\Models\CommentVote;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can create a comment vote', function () {
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

    $vote = CommentVote::create([
        'user_id' => $user->id,
        'comment_id' => $comment->id,
        'vote_type' => 'up',
    ]);

    expect($vote)->toBeInstanceOf(CommentVote::class);
    expect($vote->vote_type)->toBe('up');
    expect($vote->user_id)->toBe($user->id);
    expect($vote->comment_id)->toBe($comment->id);
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

    $vote = CommentVote::create([
        'user_id' => $user->id,
        'comment_id' => $comment->id,
        'vote_type' => 'up',
    ]);

    expect($vote->user)->toBeInstanceOf(User::class);
    expect($vote->user->id)->toBe($user->id);
});

it('belongs to a comment', function () {
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

    $vote = CommentVote::create([
        'user_id' => $user->id,
        'comment_id' => $comment->id,
        'vote_type' => 'up',
    ]);

    expect($vote->comment)->toBeInstanceOf(Comment::class);
    expect($vote->comment->id)->toBe($comment->id);
});

it('can be upvote or downvote', function () {
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

    $upVote = CommentVote::create([
        'user_id' => $user->id,
        'comment_id' => $comment->id,
        'vote_type' => 'up',
    ]);

    $anotherUser = User::factory()->create();
    $downVote = CommentVote::create([
        'user_id' => $anotherUser->id,
        'comment_id' => $comment->id,
        'vote_type' => 'down',
    ]);

    expect($upVote->vote_type)->toBe('up');
    expect($downVote->vote_type)->toBe('down');
});

it('has unique constraint on user and comment combination', function () {
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

    // Create first vote
    CommentVote::create([
        'user_id' => $user->id,
        'comment_id' => $comment->id,
        'vote_type' => 'up',
    ]);

    // Attempting to create another vote should fail due to unique constraint
    $this->expectException(\Illuminate\Database\QueryException::class);
    
    CommentVote::create([
        'user_id' => $user->id,
        'comment_id' => $comment->id,
        'vote_type' => 'down',
    ]);
});

it('can update vote type by creating new vote with same user and comment', function () {
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

    // Create initial upvote
    $vote = CommentVote::create([
        'user_id' => $user->id,
        'comment_id' => $comment->id,
        'vote_type' => 'up',
    ]);

    // Update to downvote using updateOrCreate pattern
    $updatedVote = CommentVote::updateOrCreate(
        [
            'user_id' => $user->id,
            'comment_id' => $comment->id,
        ],
        [
            'vote_type' => 'down',
        ]
    );

    expect($updatedVote->id)->toBe($vote->id);
    expect($updatedVote->vote_type)->toBe('down');
    expect(CommentVote::where('user_id', $user->id)->where('comment_id', $comment->id)->count())->toBe(1);
});

it('validates vote type enum values', function () {
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

    // This should fail due to invalid vote_type
    $this->expectException(\Illuminate\Database\QueryException::class);
    
    CommentVote::create([
        'user_id' => $user->id,
        'comment_id' => $comment->id,
        'vote_type' => 'invalid',
    ]);
});