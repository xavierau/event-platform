<?php

use App\Models\Event;
use App\Models\User;
use App\Models\Organizer;
use App\Models\Category;
use App\Models\Comment;
use App\Services\CommentService;
use App\DataTransferObjects\CommentData;
use App\Enums\RoleNameEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create the admin role first
    Role::create(['name' => RoleNameEnum::ADMIN->value]);
    
    $this->user = User::factory()->create();
    $this->organizer = Organizer::factory()->create();
    $this->category = Category::factory()->create();
    $this->commentService = app(CommentService::class);
});

it('creates approved comment when event does not require moderation', function () {
    $event = Event::factory()->create([
        'organizer_id' => $this->organizer->id,
        'category_id' => $this->category->id,
        'comments_enabled' => true,
        'comments_require_approval' => false,
    ]);

    $commentData = CommentData::from([
        'user_id' => $this->user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'This should be auto-approved!',
        'content_type' => 'plain'
    ]);

    // Mock the authorization to pass
    $this->actingAs($this->user);
    Gate::shouldReceive('forUser')->andReturnSelf();
    Gate::shouldReceive('authorize')->andReturn(true);

    $comment = $this->commentService->createComment($commentData, $this->user);
    
    expect($comment->content)->toBe('This should be auto-approved!');
    expect($comment->status)->toBe('approved');
});

it('creates pending comment when event requires moderation', function () {
    $event = Event::factory()->create([
        'organizer_id' => $this->organizer->id,
        'category_id' => $this->category->id,
        'comments_enabled' => true,
        'comments_require_approval' => true,
    ]);

    $commentData = CommentData::from([
        'user_id' => $this->user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'This should be pending approval.',
        'content_type' => 'plain'
    ]);

    // Mock the authorization to pass
    $this->actingAs($this->user);
    Gate::shouldReceive('forUser')->andReturnSelf();
    Gate::shouldReceive('authorize')->andReturn(true);

    $comment = $this->commentService->createComment($commentData, $this->user);
    
    expect($comment->content)->toBe('This should be pending approval.');
    expect($comment->status)->toBe('pending');
});


it('creates pending comment for non-event commentable types', function () {
    $commentData = CommentData::from([
        'user_id' => $this->user->id,
        'commentable_type' => \App\Models\Organizer::class,
        'commentable_id' => $this->organizer->id,
        'content' => 'This should be pending for organizer.',
        'content_type' => 'plain'
    ]);

    // Mock the authorization to pass
    $this->actingAs($this->user);
    Gate::shouldReceive('forUser')->andReturnSelf();
    Gate::shouldReceive('authorize')->andReturn(true);

    $comment = $this->commentService->createComment($commentData, $this->user);
    
    expect($comment->content)->toBe('This should be pending for organizer.');
    expect($comment->status)->toBe('pending');
});