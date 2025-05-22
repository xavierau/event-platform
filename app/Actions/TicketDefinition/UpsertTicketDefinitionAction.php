<?php

namespace App\Actions\TicketDefinition;

use App\DataTransferObjects\TicketDefinitionData;
use App\Models\TicketDefinition;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpsertTicketDefinitionAction
{
    public function execute(TicketDefinitionData $ticketData, ?int $ticketDefinitionId = null): TicketDefinition
    {
        return DB::transaction(function () use ($ticketData, $ticketDefinitionId) {
            $availabilityWindowStartUtc = null;
            if ($ticketData->availabilityWindowStart) {
                try {
                    // Assuming no specific timezone is provided with ticket definitions, use app default for parsing local time
                    $availabilityWindowStartUtc = Carbon::parse($ticketData->availabilityWindowStart, config('app.timezone'))->utc();
                } catch (\Exception $e) {
                    Log::error('Error parsing availabilityWindowStart for UTC conversion', [
                        'availabilityWindowStart' => $ticketData->availabilityWindowStart,
                        'app_timezone' => config('app.timezone'),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $availabilityWindowEndUtc = null;
            if ($ticketData->availabilityWindowEnd) {
                try {
                    $availabilityWindowEndUtc = Carbon::parse($ticketData->availabilityWindowEnd, config('app.timezone'))->utc();
                } catch (\Exception $e) {
                    Log::error('Error parsing availabilityWindowEnd for UTC conversion', [
                        'availabilityWindowEnd' => $ticketData->availabilityWindowEnd,
                        'app_timezone' => config('app.timezone'),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $dataForModel = $ticketData->toArray();
            unset($dataForModel['id']);
            // Add UTC dates to the data for model
            $dataForModel['availability_window_start_utc'] = $availabilityWindowStartUtc;
            $dataForModel['availability_window_end_utc'] = $availabilityWindowEndUtc;

            if ($ticketDefinitionId) { // Update
                $ticketDefinition = TicketDefinition::findOrFail($ticketDefinitionId);
                // Add updated_by if you have this field in your model/table
                // $dataForModel['updated_by'] = Auth::id();
                Log::info('UpsertTicketDefinitionAction: Updating TicketDefinition ' . $ticketDefinitionId, $dataForModel);
                $ticketDefinition->update($dataForModel);
            } else { // Create
                // Add created_by and updated_by if you have these fields
                // $dataForModel['created_by'] = Auth::id();
                // $dataForModel['updated_by'] = Auth::id();
                Log::info('UpsertTicketDefinitionAction: Creating TicketDefinition', $dataForModel);
                $ticketDefinition = TicketDefinition::create($dataForModel);
            }

            // Sync event occurrences if IDs are explicitly provided (not null)
            // If eventOccurrenceIds is null, it means no change to associations is intended.
            if ($ticketData->eventOccurrenceIds !== null) {
                $ticketDefinition->eventOccurrences()->sync($ticketData->eventOccurrenceIds); // $ticketData->eventOccurrenceIds will be an array here (possibly empty)
                Log::info('UpsertTicketDefinitionAction: Synced EventOccurrences for TicketDefinition ' . $ticketDefinition->id, ['event_occurrence_ids' => $ticketData->eventOccurrenceIds]);
            }

            return $ticketDefinition->refresh();
        });
    }
}
