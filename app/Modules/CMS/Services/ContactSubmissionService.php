<?php

namespace App\Modules\CMS\Services;

use App\Modules\CMS\Actions\StoreContactSubmissionAction;
use App\Modules\CMS\DataTransferObjects\ContactSubmissionData;
use App\Modules\CMS\Models\ContactSubmission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ContactSubmissionService
{
    public function __construct(
        private StoreContactSubmissionAction $storeAction
    ) {}

    public function createSubmission(ContactSubmissionData $data): ContactSubmission
    {
        // Here you might want to trigger notifications, e.g., send an email to admin
        return $this->storeAction->execute($data);
    }

    public function getPaginatedSubmissions(int $perPage = 15): LengthAwarePaginator
    {
        return ContactSubmission::latest()->paginate($perPage);
    }

    public function getAllSubmissions(): Collection
    {
        return ContactSubmission::latest()->get();
    }

    public function markAsRead(ContactSubmission $submission): ContactSubmission
    {
        $submission->update(['is_read' => true]);
        return $submission;
    }

    public function markAsUnread(ContactSubmission $submission): ContactSubmission
    {
        $submission->update(['is_read' => false]);
        return $submission;
    }

    public function deleteSubmission(ContactSubmission $submission): bool
    {
        return $submission->delete();
    }

    public function getUnreadCount(): int
    {
        return ContactSubmission::where('is_read', false)->count();
    }
}
