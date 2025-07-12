<?php

use App\Models\User;
use App\Models\Event;
use App\Models\Comment;
use App\Models\Organizer;
use App\Enums\CommentConfigEnum;
use App\Enums\OrganizerPermissionEnum;
use App\Enums\OrganizerRoleEnum;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;

it('denies comment creation if user is blocked', function () {
    $user = User::factory()->create(['is_commenting_blocked' => true]);
    $event = Event::factory()->create(['comment_config' => CommentConfigEnum::ENABLED]);

    actingAs($user);

    $this->assertFalse(Gate::allows('create', [Comment::class, $event]));
});

it('denies comment creation if event comments are disabled', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['comment_config' => CommentConfigEnum::DISABLED]);

    actingAs($user);

    $this->assertFalse(Gate::allows('create', [Comment::class, $event]));
});

it('allows comment creation if user is not blocked and event comments are enabled', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['comment_config' => CommentConfigEnum::ENABLED]);

    actingAs($user);

    $this->assertTrue(Gate::allows('create', [Comment::class, $event]));
});

it('allows a user to update their own comment', function () {
    $user = User::factory()->create();
    $comment = Comment::factory()->for($user)->create();

    actingAs($user);

    $this->assertTrue(Gate::allows('update', $comment));
});

it('denies a user from updating another user\'s comment', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $comment = Comment::factory()->for($otherUser)->create();

    actingAs($user);

    $this->assertFalse(Gate::allows('update', $comment));
});

it('allows a user to delete their own comment', function () {
    $user = User::factory()->create();
    $comment = Comment::factory()->for($user)->create();

    actingAs($user);

    $this->assertTrue(Gate::allows('delete', $comment));
});

it('allows an organizer with permission to moderate comments', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->for($organizer)->create();
    $user = User::factory()->create();

    $user->organizers()->attach($organizer->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
    ]);

    actingAs($user);

    $this->assertTrue($user->can('moderate-comments', $event));
});

it('denies an organizer without permission to moderate comments', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->for($organizer)->create();
    $user = User::factory()->create();

    $user->organizers()->attach($organizer->id, [
        'role_in_organizer' => OrganizerRoleEnum::STAFF->value,
    ]);

    actingAs($user);

    // The STAFF role does not have MODERATE_COMMENTS permission by default
    $this->assertFalse($user->can('moderate-comments', $event));
});

it('denies a user who is not part of the organizer to moderate comments', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->for($organizer)->create();
    $user = User::factory()->create();

    actingAs($user);

    $this->assertFalse($user->can('moderate-comments', $event));
});
