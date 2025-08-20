<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Enums\CommentConfigEnum;
use App\Enums\OrganizerPermissionEnum;
use Illuminate\Auth\Access\Response;
use App\Enums\RoleNameEnum;

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
        // Can view approved comments, or own comments, or if has moderate permissions
        if ($comment->isApproved()) {
            return true;
        }

        if ($user->id === $comment->user_id) {
            return true;
        }

        return $this->canModerateComment($user, $comment);
    }

    /**
     * Determine whether the user can create models on a commentable entity.
     */
    public function create(User $user, string $commentableType, int $commentableId): Response
    {
        if (isset($user->is_commenting_blocked) && $user->is_commenting_blocked) {
            return Response::deny('You are blocked from commenting.');
        }

        $commentable = $commentableType::find($commentableId);
        if (!$commentable) {
            return Response::deny('The item you are trying to comment on does not exist.');
        }

        // Check if comments are enabled for this entity
        if (isset($commentable->comments_enabled) && !$commentable->comments_enabled) {
            return Response::deny('Comments are disabled for this item.');
        }

        // Additional checks for specific entity types
        if ($commentable instanceof Event) {
            if (isset($commentable->comment_config) && $commentable->comment_config === CommentConfigEnum::DISABLED) {
                return Response::deny('Comments are disabled for this event.');
            }
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Comment $comment): bool
    {
        // Only comment owner can update their comment
        return $user->id === $comment->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Comment $comment): bool
    {
        // Own comments or admin/moderator permissions
        if ($user->id === $comment->user_id) {
            return true;
        }

        if ($user->can('delete comments')) {
            return true;
        }

        return $this->canModerateComment($user, $comment);
    }

    /**
     * Determine whether the user can vote on a comment.
     */
    public function vote(User $user, Comment $comment): bool
    {
        // Cannot vote on own comments
        if ($user->id === $comment->user_id) {
            return false;
        }

        // Can only vote on approved comments
        if (!$comment->isApproved()) {
            return false;
        }

        // Can only vote if voting is enabled for this comment
        return $comment->votes_enabled;
    }

    /**
     * Determine whether the user can moderate the comment.
     */
    public function moderate(User $user, Comment $comment): bool
    {
        return $this->canModerateComment($user, $comment);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Comment $comment): bool
    {
        return $user->can('restore comments') || $this->canModerateComment($user, $comment);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Comment $comment): bool
    {
        return $user->can('force delete comments');
    }

    /**
     * Helper method to determine if user can moderate comments on the commentable entity.
     */
    private function canModerateComment(User $user, Comment $comment): bool
    {
        // Platform admin can moderate everything
        if ($user->hasRole(RoleNameEnum::ADMIN->value)) {
            return true;
        }

        $commentable = $comment->commentable;
        if (!$commentable) {
            return false;
        }

        if ($commentable instanceof Event) {
            if (!$commentable->organizer) {
                return false;
            }
            return $user->hasOrganizerPermission($commentable->organizer, OrganizerPermissionEnum::MODERATE_COMMENTS);
        }

        if ($commentable instanceof Organizer) {
            return $user->hasOrganizerPermission($commentable, OrganizerPermissionEnum::MODERATE_COMMENTS);
        }

        return false;
    }
}
