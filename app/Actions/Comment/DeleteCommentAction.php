<?php

namespace App\Actions\Comment;

use App\Models\Comment;
use Illuminate\Support\Facades\DB;

class DeleteCommentAction
{
    public function execute(int $commentId): bool
    {
        return DB::transaction(function () use ($commentId) {
            $comment = Comment::findOrFail($commentId);
            
            // Delete all votes for this comment first
            $comment->votes()->delete();
            
            // Delete all replies to this comment
            $comment->replies()->each(function ($reply) {
                $reply->votes()->delete();
                $reply->delete();
            });
            
            // Delete the comment itself
            return $comment->delete();
        });
    }
}