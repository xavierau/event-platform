<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditorUploadController extends Controller
{
    /**
     * Handles image uploads from the rich text editor.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Max 2MB
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();

            // Store in 'public/editor_images' which links to 'storage/app/public/editor_images'
            $path = $file->storeAs('editor_images', $filename, 'public');

            if ($path) {
                return response()->json(['location' => Storage::url($path)]);
            }

            return response()->json(['error' => 'Could not save image.'], 500);
        }

        return response()->json(['error' => 'No file uploaded.'], 400);
    }
}
