<?php

use App\Models\Event;
use App\Models\User;
use App\Models\Organizer;
use App\Models\Category;
use App\Models\Comment;
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
});

it('creates approved comment when event does not require moderation', function () {
    $event = Event::factory()->create([
        'organizer_id' => $this->organizer->id,
        'category_id' => $this->category->id,
        'comments_enabled' => true,
        'comments_require_approval' => false,
    ]);

    Laravel\Sanctum\Sanctum::actingAs($this->user);

    $response = $this->postJson("/api/events/{$event->id}/comments", [
        'content' => 'This should be auto-approved!',
        'content_type' => 'plain'
    ]);

    $response->assertStatus(201);
    
    $comment = Comment::latest()->first();
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

    Laravel\Sanctum\Sanctum::actingAs($this->user);

    $response = $this->postJson("/api/events/{$event->id}/comments", [
        'content' => 'This should be pending approval.',
        'content_type' => 'plain'
    ]);

    $response->assertStatus(201);
    
    $comment = Comment::latest()->first();
    expect($comment->content)->toBe('This should be pending approval.');
    expect($comment->status)->toBe('pending');
});

it('creates approved comment for existing event with current configuration', function () {
    // This tests the actual event ID 1 from the database
    Laravel\Sanctum\Sanctum::actingAs($this->user);

    $response = $this->postJson("/api/events/1/comments", [
        'content' => 'This should be auto-approved based on current config!',
        'content_type' => 'plain'
    ]);

    if ($response->status() === 201) {
        $comment = Comment::latest()->first();
        expect($comment->content)->toBe('This should be auto-approved based on current config!');
        expect($comment->status)->toBe('approved');
    } else {
        // If the test fails due to authorization or other issues, we'll see the response
        $response->assertStatus(201);
    }
});