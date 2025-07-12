<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Event;
use App\Services\CommentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CommentController extends Controller
{
    protected $commentService;

    public function __construct(CommentService $commentService)
    {
        $this->commentService = $commentService;
    }

    public function index(Event $event)
    {
        $comments = $event->comments()->where('status', 'approved')->with('user', 'replies')->get();
        return response()->json($comments);
    }

    public function store(Request $request, Event $event)
    {
        $request->validate([
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        $this->commentService->createComment($request->all(), Auth::user(), $event);

        // Return an Inertia redirect response
        return redirect()->back()->with('success', 'Comment submitted successfully.');
    }

    public function update(Request $request, Comment $comment)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $comment = $this->commentService->updateComment($comment, $request->all(), Auth::user());

        return response()->json($comment);
    }

    public function destroy(Comment $comment)
    {
        $this->commentService->deleteComment($comment, Auth::user());

        return response()->json(null, 204);
    }

    public function approve(Comment $comment)
    {
        $comment = $this->commentService->approveComment($comment, Auth::user());
        return response()->json($comment);
    }

    public function reject(Comment $comment)
    {
        $comment = $this->commentService->rejectComment($comment, Auth::user());
        return response()->json($comment);
    }

    public function indexForModeration(Event $event)
    {
        // This is for admin use, so we don't filter by status
        $comments = $event->comments()->with('user', 'replies')->latest()->paginate(10);
        return response()->json($comments);
    }
}
