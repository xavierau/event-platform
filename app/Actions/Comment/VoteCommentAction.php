<?php

namespace App\Actions\Comment;

use App\DataTransferObjects\CommentVoteData;
use App\Models\Comment;
use App\Models\CommentVote;
use Illuminate\Support\Facades\DB;

class VoteCommentAction
{
    public function execute(CommentVoteData $voteData): CommentVote
    {
        return DB::transaction(function () use ($voteData) {
            $comment = Comment::findOrFail($voteData->comment_id);
            
            // Check if user already voted on this comment
            $existingVote = CommentVote::where('user_id', $voteData->user_id)
                ->where('comment_id', $voteData->comment_id)
                ->first();

            if ($existingVote) {
                // Update existing vote count cache
                $this->updateVoteCounts($comment, $existingVote->vote_type, 'remove');
                
                if ($existingVote->vote_type === $voteData->vote_type) {
                    // Same vote type - remove the vote (toggle off)
                    $existingVote->delete();
                    return $existingVote; // Return the deleted vote for reference
                } else {
                    // Different vote type - update the vote
                    $existingVote->vote_type = $voteData->vote_type;
                    $existingVote->save();
                    
                    // Update cache counts
                    $this->updateVoteCounts($comment, $voteData->vote_type, 'add');
                    
                    return $existingVote->fresh();
                }
            } else {
                // Create new vote
                $vote = CommentVote::create([
                    'user_id' => $voteData->user_id,
                    'comment_id' => $voteData->comment_id,
                    'vote_type' => $voteData->vote_type,
                ]);
                
                // Update cache counts
                $this->updateVoteCounts($comment, $voteData->vote_type, 'add');
                
                return $vote;
            }
        });
    }

    private function updateVoteCounts(Comment $comment, string $voteType, string $operation): void
    {
        $increment = $operation === 'add' ? 1 : -1;
        
        if ($voteType === 'up') {
            $comment->increment('votes_up_count', $increment);
        } else {
            $comment->increment('votes_down_count', $increment);
        }
    }
}