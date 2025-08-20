<?php

namespace App\Actions\Comment;

use App\DataTransferObjects\CommentData;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;

class UpsertCommentAction
{
    public function execute(CommentData $commentData): Comment
    {
        return DB::transaction(function () use ($commentData) {
            $dataToUpdate = [
                'user_id' => $commentData->user_id,
                'commentable_type' => $commentData->commentable_type,
                'commentable_id' => $commentData->commentable_id,
                'content' => $commentData->content,
                'content_type' => $commentData->content_type,
                'status' => $commentData->status,
                'parent_id' => $commentData->parent_id,
                'votes_enabled' => $commentData->votes_enabled,
                'votes_up_count' => $commentData->votes_up_count,
                'votes_down_count' => $commentData->votes_down_count,
            ];

            if ($commentData->id) { // Update existing comment
                $comment = Comment::findOrFail($commentData->id);
                $comment->fill($dataToUpdate);
            } else { // Create new comment
                $comment = new Comment($dataToUpdate);
            }

            $comment->save();

            return $comment->fresh();
        });
    }
}