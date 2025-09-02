<?php

namespace App\Actions\Event;

use App\DataTransferObjects\EventData;
use App\Enums\CommentConfigEnum;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class UpsertEventAction
{
    public function execute(EventData $eventData): Event
    {
        return DB::transaction(function () use ($eventData) {
            // Validate critical business rules to prevent silent failures
            $this->validateCriticalFields($eventData);
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
                'comment_config' => $eventData->comment_config ?? CommentConfigEnum::DISABLED->value,
                'comments_enabled' => $eventData->comments_enabled ?? false,
                'comments_require_approval' => $eventData->comments_require_approval ?? false,
                'seating_chart' => $eventData->seating_chart,
                // Conditional fields
                'event_status' => $eventData->event_status ?? 'draft', // Default if not provided
                'visibility' => $eventData->visibility ?? 'private', // Default if not provided
                'visible_to_membership_levels' => $eventData->visible_to_membership_levels,
                'action_type' => $eventData->action_type ?? 'purchase_ticket', // Default matches migration
                'is_featured' => $eventData->is_featured ?? false,
                'published_at' => $eventData->published_at,
            ];

            if ($eventData->id) { // Update
                $event = Event::findOrFail($eventData->id);
                $dataToUpdate['updated_by'] = Auth::id();
            } else { // Create
                $event = new Event;
                $dataToUpdate['created_by'] = Auth::id();
                $dataToUpdate['updated_by'] = Auth::id(); // Also set updated_by on create
            }

            $event->fill($dataToUpdate);
            $event->save();

            // Sync tags if provided
            if (! is_null($eventData->tag_ids)) {
                $event->tags()->sync($eventData->tag_ids);
            }

            // Handle removal of existing gallery items if updating and IDs are provided
            if ($eventData->id && ! empty($eventData->removed_gallery_ids)) {
                $existingMedia = $event->getMedia('gallery');
                foreach ($eventData->removed_gallery_ids as $mediaIdToRemove) {
                    $mediaItem = $existingMedia->firstWhere('id', (int) $mediaIdToRemove);
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

            if (! empty($eventData->gallery_images)) {
                foreach ($eventData->gallery_images as $galleryFile) {
                    if ($galleryFile) { // Ensure file is not null
                        $event->addMedia($galleryFile)
                            ->toMediaCollection('gallery');
                    }
                }
            }

            return $event->refresh();
        });
    }

    /**
     * Validate critical business rules to prevent silent failures
     *
     * @throws InvalidArgumentException
     */
    private function validateCriticalFields(EventData $eventData): void
    {
        // Validate action_type if provided
        if (! is_null($eventData->action_type)) {
            $validActionTypes = array_keys(Event::ACTION_TYPES);
            if (! in_array($eventData->action_type, $validActionTypes, true)) {
                throw new InvalidArgumentException(
                    "Invalid action_type '{$eventData->action_type}'. Must be one of: ".implode(', ', $validActionTypes)
                );
            }
        }

        // Validate event_status if provided
        if (! is_null($eventData->event_status) && ! in_array($eventData->event_status, Event::EVENT_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid event_status '{$eventData->event_status}'. Must be one of: ".implode(', ', Event::EVENT_STATUSES)
            );
        }

        // Validate visibility if provided
        if (! is_null($eventData->visibility) && ! in_array($eventData->visibility, Event::VISIBILITIES, true)) {
            throw new InvalidArgumentException(
                "Invalid visibility '{$eventData->visibility}'. Must be one of: ".implode(', ', Event::VISIBILITIES)
            );
        }

        // Validate visible_to_membership_levels array structure if provided
        if (! is_null($eventData->visible_to_membership_levels)) {
            if (! is_array($eventData->visible_to_membership_levels)) {
                throw new InvalidArgumentException('visible_to_membership_levels must be an array');
            }

            foreach ($eventData->visible_to_membership_levels as $levelId) {
                if (! is_int($levelId) && ! ctype_digit((string) $levelId)) {
                    throw new InvalidArgumentException('All membership level IDs must be integers');
                }
            }
        }

        // Log critical field updates for debugging
        if ($eventData->id) {
            Log::info('UpsertEventAction: Updating critical fields for event', [
                'event_id' => $eventData->id,
                'action_type' => $eventData->action_type,
                'visible_to_membership_levels' => $eventData->visible_to_membership_levels,
                'event_status' => $eventData->event_status,
            ]);
        }
    }
}
