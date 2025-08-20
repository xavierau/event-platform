<?php

use App\Models\Event;
use App\Models\User;
use App\Models\Organizer;
use App\Models\Category;
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

it('prevents comment creation when comments are disabled on event', function () {
    $event = Event::factory()->create([
        'organizer_id' => $this->organizer->id,
        'category_id' => $this->category->id,
        'comments_enabled' => false,
        'comments_require_approval' => false,
        'comment_config' => 'disabled',
    ]);

    Laravel\Sanctum\Sanctum::actingAs($this->user);

    $response = $this->postJson("/api/events/{$event->id}/comments", [
        'content' => 'This comment should not be created!',
        'content_type' => 'plain'
    ]);

    // Should return 403 Forbidden when comments are disabled
    $response->assertStatus(403);
    $response->assertJson([
        'message' => 'Comments are disabled for this item.'
    ]);
    
    // Verify no comment was created in the database
    $this->assertDatabaseMissing('comments', [
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'This comment should not be created!'
    ]);
});

it('allows comment creation when comments are enabled on event', function () {
    $event = Event::factory()->create([
        'organizer_id' => $this->organizer->id,
        'category_id' => $this->category->id,
        'comments_enabled' => true,
        'comments_require_approval' => false,
        'comment_config' => 'enabled',
    ]);

    Laravel\Sanctum\Sanctum::actingAs($this->user);

    $response = $this->postJson("/api/events/{$event->id}/comments", [
        'content' => 'This comment should be created and approved!',
        'content_type' => 'plain'
    ]);

    $response->assertStatus(201);
    
    // Verify comment was created and is approved
    $this->assertDatabaseHas('comments', [
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'content' => 'This comment should be created and approved!',
        'status' => 'approved'
    ]);
});