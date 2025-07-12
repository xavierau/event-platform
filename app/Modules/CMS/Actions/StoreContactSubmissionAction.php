<?php

namespace App\Modules\CMS\Actions;

use App\Modules\CMS\DataTransferObjects\ContactSubmissionData;
use App\Modules\CMS\Models\ContactSubmission;

class StoreContactSubmissionAction
{
    public function execute(ContactSubmissionData $data): ContactSubmission
    {
        return ContactSubmission::create([
            'name' => $data->name,
            'email' => $data->email,
            'subject' => $data->subject,
            'message' => $data->message,
        ]);
    }
}
