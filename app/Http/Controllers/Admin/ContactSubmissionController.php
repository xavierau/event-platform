<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\RoleNameEnum;
use App\Modules\CMS\Models\ContactSubmission;
use App\Modules\CMS\Services\ContactSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ContactSubmissionController extends Controller
{
    public function __construct(
        private ContactSubmissionService $contactSubmissionService
    ) {
        // Ensure only platform admins can access contact submissions
        $this->middleware(function ($request, $next) {
            if (!Auth::user()->hasRole(RoleNameEnum::ADMIN)) {
                abort(403, 'Only platform administrators can manage contact submissions.');
            }
            return $next($request);
        });
    }

    public function index(): Response
    {
        return Inertia::render('Admin/ContactSubmissions/Index', [
            'submissions' => $this->contactSubmissionService->getPaginatedSubmissions(),
        ]);
    }

    public function show(ContactSubmission $contactSubmission): Response
    {
        return Inertia::render('Admin/ContactSubmissions/Show', [
            'submission' => $contactSubmission,
        ]);
    }

    public function destroy(ContactSubmission $submission)
    {
        $this->contactSubmissionService->deleteSubmission($submission);

        return redirect()->route('admin.contact-submissions.index')
            ->with('success', 'Submission deleted successfully.');
    }

    public function toggleRead(ContactSubmission $submission)
    {
        if ($submission->is_read) {
            $this->contactSubmissionService->markAsUnread($submission);
            $message = 'Marked as unread.';
        } else {
            $this->contactSubmissionService->markAsRead($submission);
            $message = 'Marked as read.';
        }

        return back()->with('success', $message);
    }
}
