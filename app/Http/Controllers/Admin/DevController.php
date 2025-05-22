<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\DataTransferObjects\Dev\SingleMediaTestData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DevController extends Controller
{
    public function mediaUploadTest()
    {
        return Inertia::render('Admin/Dev/MediaUploadTestPage', [
            'pageTitle' => 'Media Upload Test',
            'breadcrumbs' => [
                ['text' => 'Admin Dashboard', 'href' => route('admin.dashboard')], // Assuming you have this route
                ['text' => 'Development'],
                ['text' => 'Media Upload Test']
            ]
        ]);
    }

    public function handleMediaPost(SingleMediaTestData $data)
    {
        Log::info('DevController handleMediaPost DTO data:', $data->toArray());
        // Access validated and casted data directly from the DTO
        // e.g., $data->someOtherField, $data->metadata->author, $data->singleFile

        if ($data->singleFile) {
            Log::info('Uploaded single file (from DTO):', [$data->singleFile->getClientOriginalName()]);
            // Example: $path = $data->singleFile->store('dev_uploads');
            // Log::info('Stored file at:', [$path]);
        }
        Log::info('Metadata keywords (from DTO):', $data->metadata->keywords);
        Log::info('Metadata setting isVisible (from DTO):', [$data->metadata->settings->isVisible]);

        // Redirect back to the test page or return a JSON response
        return redirect()->route('admin.dev.media-upload-test')->with('success_message', 'POST request received and validated via DTO. Check logs.');
    }

    public function handleMediaPut(SingleMediaTestData $data)
    {
        // Laravel handles the _method:PUT for routing, then DTO resolves from the actual POST data.
        Log::info('DevController handleMediaPut DTO data:', $data->toArray());

        if ($data->singleFile) {
            Log::info('Uploaded single file (PUT from DTO):', [$data->singleFile->getClientOriginalName()]);
        }
        Log::info('Metadata author (PUT from DTO):', [$data->metadata->author]);
        Log::info('Metadata setting rating (PUT from DTO):', [$data->metadata->settings->rating]);

        // Redirect back to the test page or return a JSON response
        return redirect()->route('admin.dev.media-upload-test')->with('success_message', 'PUT request received and validated via DTO. Check logs.');
    }
}
