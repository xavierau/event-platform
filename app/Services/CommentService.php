<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CommentService
{
    public function createComment(array $data, User $user, Event $event): Comment
    {
        Gate::forUser($user)->authorize('create', [Comment::class, $event]);

        return Comment::create([
            'content' => $data['content'],
            'user_id' => $user->id,
            'event_id' => $event->id,
            'parent_id' => $data['parent_id'] ?? null,
            'status' => $event->comment_config->value === 'moderated' ? 'pending' : 'approved',
        ]);
    }

    public function updateComment(Comment $comment, array $data, User $user): Comment
    {
        Gate::forUser($user)->authorize('update', $comment);

        $comment->update($data);
        return $comment;
    }

    public function deleteComment(Comment $comment, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $comment);

        $comment->delete();
    }

    public function approveComment(Comment $comment, User $user): Comment
    {
        Gate::forUser($user)->authorize('moderate-comments', $comment->event);

        $comment->update(['status' => 'approved']);
        return $comment;
    }

    public function rejectComment(Comment $comment, User $user): Comment
    {
        Gate::forUser($user)->authorize('moderate-comments', $comment->event);

        $comment->update(['status' => 'rejected']);
        return $comment;
    }
}
