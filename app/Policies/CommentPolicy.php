<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Event;
use App\Models\User;
use App\Enums\CommentConfigEnum;
use App\Enums\OrganizerPermissionEnum;
use Illuminate\Auth\Access\Response;

class CommentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Comment $comment): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Event $event): Response
    {
        if ($user->is_commenting_blocked) {
            return Response::deny('You are blocked from commenting.');
        }

        if ($event->comment_config === CommentConfigEnum::DISABLED) {
            return Response::deny('Comments are disabled for this event.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id || $user->can('delete comments');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Comment $comment): bool
    {
        return $user->can('restore comments');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Comment $comment): bool
    {
        return $user->can('force delete comments');
    }

    /**
     * Determine whether the user can moderate comments for an event.
     */
    public function moderate(User $user, Event $event): bool
    {
        if (!$event->organizer) {
            return false;
        }

        return $user->hasOrganizerPermission($event->organizer, OrganizerPermissionEnum::MODERATE_COMMENTS);
    }
}
