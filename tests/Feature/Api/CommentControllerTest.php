<?php

use App\Models\User;
use App\Models\Event;
use App\Models\Comment;
use App\Models\Organizer;
use App\Enums\CommentConfigEnum;
use App\Enums\OrganizerRoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\deleteJson;

uses(RefreshDatabase::class);

it('requires authentication for comment endpoints', function () {
    $event = Event::factory()->create();
    $comment = Comment::factory()->create();

    getJson("/api/events/{$event->id}/comments")->assertUnauthorized();
    postJson("/api/events/{$event->id}/comments", ['content' => 'Test'])->assertUnauthorized();
    putJson("/api/comments/{$comment->id}", ['content' => 'Updated'])->assertUnauthorized();
    deleteJson("/api/comments/{$comment->id}")->assertUnauthorized();
    postJson("/api/comments/{$comment->id}/approve")->assertUnauthorized();
    postJson("/api/comments/{$comment->id}/reject")->assertUnauthorized();
});

it('can fetch approved comments for an event', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    Comment::factory()->for($event)->create(['status' => 'approved']);
    Comment::factory()->for($event)->create(['status' => 'pending']);

    actingAs($user);

    getJson("/api/events/{$event->id}/comments")
        ->assertOk()
        ->assertJsonCount(1);
});

it('can post a comment to an event', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['comment_config' => CommentConfigEnum::ENABLED]);

    actingAs($user);

    $commentData = ['content' => 'This is a new comment'];

    postJson("/api/events/{$event->id}/comments", $commentData)
        ->assertCreated()
        ->assertJsonFragment(['content' => 'This is a new comment']);
});

it('can update own comment', function () {
    $user = User::factory()->create();
    $comment = Comment::factory()->for($user)->create();

    actingAs($user);

    $updateData = ['content' => 'This is updated'];

    putJson("/api/comments/{$comment->id}", $updateData)
        ->assertOk()
        ->assertJsonFragment(['content' => 'This is updated']);
});

it('cannot update another user\'s comment', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $comment = Comment::factory()->for($otherUser)->create();

    actingAs($user);

    $updateData = ['content' => 'This is updated'];

    putJson("/api/comments/{$comment->id}", $updateData)
        ->assertForbidden();
});

it('can delete own comment', function () {
    $user = User::factory()->create();
    $comment = Comment::factory()->for($user)->create();

    actingAs($user);

    deleteJson("/api/comments/{$comment->id}")
        ->assertNoContent();
});

it('can approve a comment with permission', function () {
    $organizer = Organizer::factory()->create();
    $user = User::factory()->create();
    $user->organizers()->attach($organizer->id, ['role_in_organizer' => OrganizerRoleEnum::MANAGER->value]);
    $event = Event::factory()->for($organizer)->create();
    $comment = Comment::factory()->for($event)->create(['status' => 'pending']);

    actingAs($user);

    postJson("/api/comments/{$comment->id}/approve")
        ->assertOk()
        ->assertJsonFragment(['status' => 'approved']);
});

it('can reject a comment with permission', function () {
    $organizer = Organizer::factory()->create();
    $user = User::factory()->create();
    $user->organizers()->attach($organizer->id, ['role_in_organizer' => OrganizerRoleEnum::MANAGER->value]);
    $event = Event::factory()->for($organizer)->create();
    $comment = Comment::factory()->for($event)->create(['status' => 'pending']);

    actingAs($user);

    postJson("/api/comments/{$comment->id}/reject")
        ->assertOk()
        ->assertJsonFragment(['status' => 'rejected']);
});
