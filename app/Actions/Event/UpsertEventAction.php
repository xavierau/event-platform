<?php

namespace App\Actions\Event;

use App\DataTransferObjects\EventData;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpsertEventAction
{
    public function execute(EventData $eventData): Event
    {
        return DB::transaction(function () use ($eventData) {
            $dataToUpdate = [
                'organizer_id' => $eventData->organizer_id,
                'category_id' => $eventData->category_id,
                'name' => $eventData->name,
                'slug' => $eventData->slug,
                'description' => $eventData->description,
                'short_summary' => $eventData->short_summary,
                'contact_email' => $eventData->contact_email,
                'contact_phone' => $eventData->contact_phone,
                'website_url' => $eventData->website_url,
                'social_media_links' => $eventData->social_media_links,
                'youtube_video_id' => $eventData->youtube_video_id,
                'cancellation_policy' => $eventData->cancellation_policy,
                'meta_title' => $eventData->meta_title,
                'meta_description' => $eventData->meta_description,
                'meta_keywords' => $eventData->meta_keywords,
                // Conditional fields
                'event_status' => $eventData->event_status ?? 'draft', // Default if not provided
                'visibility' => $eventData->visibility ?? 'private', // Default if not provided
                'is_featured' => $eventData->is_featured ?? false,
                'published_at' => $eventData->published_at,
            ];

            if ($eventData->id) { // Update
                $event = Event::findOrFail($eventData->id);
                $dataToUpdate['updated_by'] = Auth::id();
            } else { // Create
                $event = new Event();
                $dataToUpdate['created_by'] = Auth::id();
                $dataToUpdate['updated_by'] = Auth::id(); // Also set updated_by on create
            }

            $event->fill($dataToUpdate);
            $event->save();

            // Sync tags if provided
            if (!is_null($eventData->tag_ids)) {
                $event->tags()->sync($eventData->tag_ids);
            }

            // Handle removal of existing gallery items if updating and IDs are provided
            if ($eventData->id && !empty($eventData->removed_gallery_ids)) {
                $existingMedia = $event->getMedia('gallery');
                foreach ($eventData->removed_gallery_ids as $mediaIdToRemove) {
                    $mediaItem = $existingMedia->firstWhere('id', (int)$mediaIdToRemove);
                    if ($mediaItem) {
                        $mediaItem->delete();
                    }
                }
            }

            // Handle media uploads
            if ($eventData->uploaded_portrait_poster) {
                $event->addMedia($eventData->uploaded_portrait_poster)
                    ->toMediaCollection('portrait_poster');
            }

            if ($eventData->uploaded_landscape_poster) {
                $event->addMedia($eventData->uploaded_landscape_poster)
                    ->toMediaCollection('landscape_poster');
            }

            if (!empty($eventData->uploaded_gallery)) {
                foreach ($eventData->uploaded_gallery as $galleryFile) {
                    if ($galleryFile) { // Ensure file is not null
                        $event->addMedia($galleryFile)
                            ->toMediaCollection('gallery');
                    }
                }
            }

            return $event->refresh();
        });
    }
}
