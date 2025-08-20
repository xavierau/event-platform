<?php

namespace App\Traits;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Commentable
{
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function approvedComments(): MorphMany
    {
        return $this->comments()->approved();
    }

    public function pendingComments(): MorphMany
    {
        return $this->comments()->pending();
    }

    public function rejectedComments(): MorphMany
    {
        return $this->comments()->rejected();
    }

    public function topLevelComments(): MorphMany
    {
        return $this->comments()->whereNull('parent_id');
    }

    public function approvedTopLevelComments(): MorphMany
    {
        return $this->topLevelComments()->approved();
    }

    public function commentsWithReplies(): MorphMany
    {
        return $this->comments()->with(['replies' => function ($query) {
            $query->approved()->with('user');
        }])->whereNull('parent_id');
    }

    public function areCommentsEnabled(): bool
    {
        return $this->comments_enabled ?? false;
    }

    public function requireCommentApproval(): bool
    {
        return $this->comments_require_approval ?? false;
    }

    public function getCommentStatusForUser(): string
    {
        return $this->requireCommentApproval() ? 'pending' : 'approved';
    }
}