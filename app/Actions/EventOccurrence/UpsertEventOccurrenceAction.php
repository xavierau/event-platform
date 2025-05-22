<?php

namespace App\Actions\EventOccurrence;

use App\DataTransferObjects\EventOccurrenceData;
use App\Models\EventOccurrence;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Added for debugging potential parse issues

class UpsertEventOccurrenceAction
{
    public function execute(EventOccurrenceData $occurrenceData, ?int $occurrenceId = null, ?int $eventIdForCreate = null): EventOccurrence
    {
        return DB::transaction(function () use ($occurrenceData, $occurrenceId, $eventIdForCreate) {
            $startAtUtc = null;
            if ($occurrenceData->start_at && $occurrenceData->timezone) {
                try {
                    $startAtUtc = Carbon::parse($occurrenceData->start_at, $occurrenceData->timezone)->utc();
                } catch (\Exception $e) {
                    Log::error('Error parsing start_at for UTC conversion', [
                        'start_at' => $occurrenceData->start_at,
                        'timezone' => $occurrenceData->timezone,
                        'error' => $e->getMessage(),
                    ]);
                    // Optionally rethrow or handle as an invalid input, depending on desired strictness
                }
            }

            $endAtUtc = null;
            if ($occurrenceData->end_at && $occurrenceData->timezone) {
                try {
                    $endAtUtc = Carbon::parse($occurrenceData->end_at, $occurrenceData->timezone)->utc();
                } catch (\Exception $e) {
                    Log::error('Error parsing end_at for UTC conversion', [
                        'end_at' => $occurrenceData->end_at,
                        'timezone' => $occurrenceData->timezone,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $dataToUpdate = [
                'name' => $occurrenceData->name,
                'description' => $occurrenceData->description,
                'start_at' => $occurrenceData->start_at, // Raw string
                'end_at' => $occurrenceData->end_at,     // Raw string
                'start_at_utc' => $startAtUtc,
                'end_at_utc' => $endAtUtc,
                'venue_id' => $occurrenceData->venue_id,
                'is_online' => $occurrenceData->is_online,
                'online_meeting_link' => $occurrenceData->online_meeting_link,
                'capacity' => $occurrenceData->capacity,
                'status' => $occurrenceData->status,
                'timezone' => $occurrenceData->timezone,
            ];

            if ($occurrenceId) { // Update
                $occurrence = EventOccurrence::findOrFail($occurrenceId);
                $dataToUpdate['updated_by'] = Auth::id();
            } else { // Create
                $occurrence = new EventOccurrence();
                $dataToUpdate['event_id'] = $eventIdForCreate ?? $occurrenceData->event_id;
                if (!$dataToUpdate['event_id']) {
                    throw new \InvalidArgumentException('Event ID is required to create an occurrence.');
                }
                $dataToUpdate['created_by'] = Auth::id();
                $dataToUpdate['updated_by'] = Auth::id();
            }

            $occurrence->fill($dataToUpdate);
            $occurrence->save();

            // Handle any related logic here, e.g., syncing tickets if this occurrence has direct ticket definitions.

            return $occurrence->refresh();
        });
    }
}
