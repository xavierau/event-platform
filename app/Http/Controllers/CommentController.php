<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\CommentData;
use App\DataTransferObjects\CommentVoteData;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Organizer;
use App\Services\CommentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CommentController extends Controller
{
    public function __construct(
        private CommentService $commentService
    ) {}

    /**
     * Display comments for any commentable entity (polymorphic).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'commentable_type' => 'required|string|in:App\Models\Event,App\Models\Organizer',
            'commentable_id' => 'required|integer',
            'per_page' => 'nullable|integer|min:1|max:50'
        ]);

        $comments = $this->commentService->getCommentsForEntity(
            $request->get('commentable_type'),
            $request->get('commentable_id'),
            $request->get('per_page', 15)
        );

        // Add user vote information if authenticated
        if (Auth::check()) {
            $comments->getCollection()->transform(function ($comment) {
                $comment->user_vote = $this->commentService->getUserVoteForComment($comment->id, Auth::id());
                return $comment;
            });
        }

        return response()->json($comments);
    }

    /**
     * Store a new comment for any commentable entity (polymorphic).
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'commentable_type' => 'required|string|in:App\Models\Event,App\Models\Organizer',
            'commentable_id' => 'required|integer',
            'content' => 'required|string|max:10000',
            'content_type' => 'nullable|string|in:plain,rich',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ]);

        $commentData = CommentData::from([
            'user_id' => Auth::id(),
            'commentable_type' => $validatedData['commentable_type'],
            'commentable_id' => $validatedData['commentable_id'],
            'content' => $validatedData['content'],
            'content_type' => $validatedData['content_type'] ?? 'plain',
            'parent_id' => $validatedData['parent_id'] ?? null,
        ]);

        $comment = $this->commentService->createComment($commentData, Auth::user());

        return response()->json([
            'message' => 'Comment created successfully.',
            'comment' => $comment->load('user')
        ], Response::HTTP_CREATED);
    }

    /**
     * Display comments for an event.
     */
    public function indexForEvent(Request $request, Event $event): JsonResponse
    {
        return $this->indexForCommentable($request, $event);
    }

    /**
     * Display comments for an organizer.
     */
    public function indexForOrganizer(Request $request, Organizer $organizer): JsonResponse
    {
        return $this->indexForCommentable($request, $organizer);
    }

    /**
     * Display comments for a specific commentable entity.
     */
    private function indexForCommentable(Request $request, Model $commentable): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:50'
        ]);

        $comments = $this->commentService->getCommentsForEntity(
            get_class($commentable),
            $commentable->id,
            $request->get('per_page', 15)
        );

        // Add user vote information if authenticated
        if (Auth::check()) {
            $comments->getCollection()->transform(function ($comment) {
                $comment->user_vote = $this->commentService->getUserVoteForComment($comment->id, Auth::id());
                return $comment;
            });
        }

        return response()->json($comments);
    }

    /**
     * Store a new comment for an event.
     */
    public function storeForEvent(Request $request, Event $event): JsonResponse
    {
        return $this->storeForCommentable($request, $event);
    }

    /**
     * Store a new comment for an organizer.
     */
    public function storeForOrganizer(Request $request, Organizer $organizer): JsonResponse
    {
        return $this->storeForCommentable($request, $organizer);
    }

    /**
     * Store a new comment for a commentable entity.
     */
    private function storeForCommentable(Request $request, Model $commentable): JsonResponse
    {
        $validatedData = $request->validate([
            'content' => 'required|string|max:10000',
            'content_type' => 'nullable|string|in:plain,rich',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ]);

        $commentData = CommentData::from([
            'user_id' => Auth::id(),
            'commentable_type' => get_class($commentable),
            'commentable_id' => $commentable->id,
            'content' => $validatedData['content'],
            'content_type' => $validatedData['content_type'] ?? 'plain',
            'parent_id' => $validatedData['parent_id'] ?? null,
        ]);

        $comment = $this->commentService->createComment($commentData, Auth::user());

        return response()->json([
            'message' => 'Comment created successfully.',
            'comment' => $comment->load('user')
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a comment.
     */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        $validatedData = $request->validate([
            'content' => 'required|string|max:10000',
            'content_type' => 'nullable|string|in:plain,rich',
        ]);

        $commentData = CommentData::from([
            'id' => $comment->id,
            'user_id' => $comment->user_id,
            'commentable_type' => $comment->commentable_type,
            'commentable_id' => $comment->commentable_id,
            'content' => $validatedData['content'],
            'content_type' => $validatedData['content_type'] ?? $comment->content_type,
            'status' => $comment->status,
            'parent_id' => $comment->parent_id,
        ]);

        $updatedComment = $this->commentService->updateComment($commentData, Auth::user());

        return response()->json([
            'message' => 'Comment updated successfully.',
            'comment' => $updatedComment->load('user')
        ]);
    }

    /**
     * Delete a comment.
     */
    public function destroy(Comment $comment): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $this->commentService->deleteComment($comment->id, Auth::user());

        // If it's an Inertia request, return redirect response
        if (request()->header('X-Inertia')) {
            return back()->with('success', 'Comment deleted successfully.');
        }

        return response()->json([
            'message' => 'Comment deleted successfully.'
        ]);
    }

    /**
     * Vote on a comment.
     */
    public function vote(Request $request, Comment $comment): JsonResponse
    {
        $validatedData = $request->validate([
            'vote_type' => 'required|string|in:up,down',
        ]);

        $voteData = CommentVoteData::from([
            'user_id' => Auth::id(),
            'comment_id' => $comment->id,
            'vote_type' => $validatedData['vote_type'],
        ]);

        $vote = $this->commentService->voteComment($voteData, Auth::user());

        // Refresh comment to get updated vote counts
        $comment->refresh();

        return response()->json([
            'message' => 'Vote recorded successfully.',
            'vote' => $vote,
            'comment' => [
                'id' => $comment->id,
                'votes_up_count' => $comment->votes_up_count,
                'votes_down_count' => $comment->votes_down_count,
            ]
        ]);
    }

    /**
     * Approve a comment (moderation).
     */
    public function approve(Comment $comment): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $approvedComment = $this->commentService->approveComment($comment->id, Auth::user());

        // If it's an Inertia request, return redirect response
        if (request()->header('X-Inertia')) {
            return back()->with('success', 'Comment approved successfully.');
        }

        return response()->json([
            'message' => 'Comment approved successfully.',
            'comment' => $approvedComment
        ]);
    }

    /**
     * Reject a comment (moderation).
     */
    public function reject(Comment $comment): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $rejectedComment = $this->commentService->rejectComment($comment->id, Auth::user());

        // If it's an Inertia request, return redirect response
        if (request()->header('X-Inertia')) {
            return back()->with('success', 'Comment rejected successfully.');
        }

        return response()->json([
            'message' => 'Comment rejected successfully.',
            'comment' => $rejectedComment
        ]);
    }

    /**
     * Flag a comment (moderation).
     */
    public function flag(Comment $comment): JsonResponse
    {
        $flaggedComment = $this->commentService->flagComment($comment->id, Auth::user());

        return response()->json([
            'message' => 'Comment flagged successfully.',
            'comment' => $flaggedComment
        ]);
    }

    /**
     * Get pending comments for moderation.
     */
    public function pending(Request $request): JsonResponse
    {
        $request->validate([
            'commentable_type' => 'required|string|in:App\Models\Event,App\Models\Organizer',
            'commentable_id' => 'required|integer',
        ]);

        $pendingComments = $this->commentService->getPendingComments(
            $request->get('commentable_type'),
            $request->get('commentable_id')
        );

        return response()->json($pendingComments);
    }

    /**
     * Get comments for moderation (admin panel).
     */
    public function indexForModeration(Event $event): JsonResponse
    {
        // Get all comments for the event (pending, approved, rejected)
        $comments = Comment::where('commentable_type', Event::class)
            ->where('commentable_id', $event->id)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $comments
        ]);
    }
}
