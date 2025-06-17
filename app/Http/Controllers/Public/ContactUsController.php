<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\CMS\DataTransferObjects\ContactSubmissionData;
use App\Modules\CMS\Services\ContactSubmissionService;
use Illuminate\Http\Request;

class ContactUsController extends Controller
{
    public function __construct(
        private ContactSubmissionService $submissionService
    ) {}

    public function store(Request $request)
    {
        try {
            $data = ContactSubmissionData::from($request->all());
            $this->submissionService->createSubmission($data);

            // You might also want to send a confirmation email to the user here.

            return back()->with('success', 'Thank you for your message! We will get back to you shortly.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An unexpected error occurred. Please try again.'])->withInput();
        }
    }
}
