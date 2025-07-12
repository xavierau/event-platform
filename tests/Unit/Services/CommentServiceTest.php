<?php

use App\Services\CommentService;
use App\Models\User;
use App\Models\Event;
use App\Models\Comment;
use App\Enums\CommentConfigEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('creates a comment with approved status when event is enabled', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['comment_config' => CommentConfigEnum::ENABLED]);
    $commentService = new CommentService();

    $commentData = [
        'content' => 'This is a test comment.',
    ];

    $comment = $commentService->createComment($commentData, $user, $event);

    $this->assertInstanceOf(Comment::class, $comment);
    $this->assertEquals('approved', $comment->status);
    $this->assertDatabaseHas('comments', [
        'id' => $comment->id,
        'status' => 'approved',
    ]);
});

it('creates a comment with pending status when event is moderated', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['comment_config' => CommentConfigEnum::MODERATED]);
    $commentService = new CommentService();

    $commentData = [
        'content' => 'This is a test comment.',
    ];

    $comment = $commentService->createComment($commentData, $user, $event);

    $this->assertEquals('pending', $comment->status);
    $this->assertDatabaseHas('comments', [
        'id' => $comment->id,
        'status' => 'pending',
    ]);
});

it('updates a comment', function () {
    $user = User::factory()->create();
    $comment = Comment::factory()->for($user)->create();
    $commentService = new CommentService();

    $updateData = ['content' => 'Updated content'];

    $updatedComment = $commentService->updateComment($comment, $updateData, $user);

    $this->assertEquals('Updated content', $updatedComment->content);
});

it('deletes a comment', function () {
    $user = User::factory()->create();
    $comment = Comment::factory()->for($user)->create();
    $commentService = new CommentService();

    $commentService->deleteComment($comment, $user);

    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
});

it('approves a comment', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $comment = Comment::factory()->for($event)->create(['status' => 'pending']);
    $commentService = new CommentService();

    Gate::shouldReceive('forUser')->with($user)->andReturnSelf();
    Gate::shouldReceive('authorize')->with('moderate-comments', \Mockery::on(function ($argument) use ($event) {
        return $argument->id === $event->id;
    }))->andReturn(true);

    $approvedComment = $commentService->approveComment($comment, $user);

    $this->assertEquals('approved', $approvedComment->status);
});

it('rejects a comment', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $comment = Comment::factory()->for($event)->create(['status' => 'pending']);
    $commentService = new CommentService();

    Gate::shouldReceive('forUser')->with($user)->andReturnSelf();
    Gate::shouldReceive('authorize')->with('moderate-comments', \Mockery::on(function ($argument) use ($event) {
        return $argument->id === $event->id;
    }))->andReturn(true);

    $rejectedComment = $commentService->rejectComment($comment, $user);

    $this->assertEquals('rejected', $rejectedComment->status);
});
