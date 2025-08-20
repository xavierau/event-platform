<?php

namespace App\Actions\Comment;

use App\Models\Comment;
use Illuminate\Support\Facades\DB;

class ModerateCommentAction
{
    public function execute(int $commentId, string $status, ?int $moderatorId = null): Comment
    {
        if (!in_array($status, ['approved', 'rejected', 'flagged', 'pending'])) {
            throw new \InvalidArgumentException('Invalid comment status provided');
        }

        return DB::transaction(function () use ($commentId, $status, $moderatorId) {
            $comment = Comment::findOrFail($commentId);
            
            $comment->status = $status;
            
            // You could add a moderation log here if needed
            // This would require additional table/model for audit trail
            
            $comment->save();

            return $comment->fresh();
        });
    }

    public function approve(int $commentId, ?int $moderatorId = null): Comment
    {
        return $this->execute($commentId, 'approved', $moderatorId);
    }

    public function reject(int $commentId, ?int $moderatorId = null): Comment
    {
        return $this->execute($commentId, 'rejected', $moderatorId);
    }

    public function flag(int $commentId, ?int $moderatorId = null): Comment
    {
        return $this->execute($commentId, 'flagged', $moderatorId);
    }
}