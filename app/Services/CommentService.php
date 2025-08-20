<?php

namespace App\Services;

use App\Actions\Comment\DeleteCommentAction;
use App\Actions\Comment\ModerateCommentAction;
use App\Actions\Comment\UpsertCommentAction;
use App\Actions\Comment\VoteCommentAction;
use App\DataTransferObjects\CommentData;
use App\DataTransferObjects\CommentVoteData;
use App\Models\Comment;
use App\Models\CommentVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class CommentService
{
    public function __construct(
        private UpsertCommentAction $upsertCommentAction,
        private DeleteCommentAction $deleteCommentAction,
        private ModerateCommentAction $moderateCommentAction,
        private VoteCommentAction $voteCommentAction,
    ) {}

    public function createComment(CommentData $commentData, User $user): Comment
    {
        Gate::forUser($user)->authorize('create', [Comment::class, $commentData->commentable_type, $commentData->commentable_id]);

        // Determine the appropriate status based on the commentable entity's configuration
        $status = $this->determineCommentStatus($commentData);
        
        // Create a new CommentData with the correct status
        $commentDataWithStatus = CommentData::from([
            ...$commentData->toArray(),
            'status' => $status
        ]);

        return $this->upsertCommentAction->execute($commentDataWithStatus);
    }

    public function updateComment(CommentData $commentData, User $user): Comment
    {
        $comment = Comment::findOrFail($commentData->id);
        Gate::forUser($user)->authorize('update', $comment);

        return $this->upsertCommentAction->execute($commentData);
    }

    public function deleteComment(int $commentId, User $user): bool
    {
        $comment = Comment::findOrFail($commentId);
        Gate::forUser($user)->authorize('delete', $comment);

        return $this->deleteCommentAction->execute($commentId);
    }

    public function voteComment(CommentVoteData $voteData, User $user): CommentVote
    {
        $comment = Comment::findOrFail($voteData->comment_id);
        Gate::forUser($user)->authorize('vote', $comment);

        return $this->voteCommentAction->execute($voteData);
    }

    public function approveComment(int $commentId, User $moderator): Comment
    {
        $comment = Comment::findOrFail($commentId);
        Gate::forUser($moderator)->authorize('moderate', $comment);

        return $this->moderateCommentAction->approve($commentId, $moderator->id);
    }

    public function rejectComment(int $commentId, User $moderator): Comment
    {
        $comment = Comment::findOrFail($commentId);
        Gate::forUser($moderator)->authorize('moderate', $comment);

        return $this->moderateCommentAction->reject($commentId, $moderator->id);
    }

    public function flagComment(int $commentId, User $moderator): Comment
    {
        $comment = Comment::findOrFail($commentId);
        Gate::forUser($moderator)->authorize('moderate', $comment);

        return $this->moderateCommentAction->flag($commentId, $moderator->id);
    }

    public function getCommentsForEntity(string $commentableType, int $commentableId, int $perPage = 15): LengthAwarePaginator
    {
        return Comment::where('commentable_type', $commentableType)
            ->where('commentable_id', $commentableId)
            ->whereNull('parent_id') // Only top-level comments
            ->approved()
            ->with(['user', 'replies' => function($query) {
                $query->approved()->with('user')->orderBy('created_at');
            }])
            ->withCount(['upVotes', 'downVotes'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getPendingComments(string $commentableType, int $commentableId): \Illuminate\Database\Eloquent\Collection
    {
        return Comment::where('commentable_type', $commentableType)
            ->where('commentable_id', $commentableId)
            ->pending()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getUserVoteForComment(int $commentId, int $userId): ?CommentVote
    {
        return CommentVote::where('comment_id', $commentId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Determine the appropriate status for a new comment based on the commentable entity's configuration.
     */
    private function determineCommentStatus(CommentData $commentData): string
    {
        // For Event comments, check the event's comment configuration
        if ($commentData->commentable_type === \App\Models\Event::class) {
            $event = \App\Models\Event::find($commentData->commentable_id);
            
            if (!$event) {
                return 'pending'; // Default fallback
            }
            
            // If comments require approval, set to pending
            if ($event->comments_require_approval) {
                return 'pending';
            }
            
            // Comments don't require approval - auto-approve
            return 'approved';
        }
        
        // For other commentable types (like Organizer), use default behavior
        // You can extend this logic for other entities as needed
        return 'pending';
    }
}
