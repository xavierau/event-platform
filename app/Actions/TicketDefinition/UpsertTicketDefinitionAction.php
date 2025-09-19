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
            $effectiveTimezone = $ticketData->timezone ?? config('app.timezone');

            $availabilityWindowStartUtc = null;
            if ($ticketData->availability_window_start) {
                try {
                    $availabilityWindowStartUtc = Carbon::parse($ticketData->availability_window_start, $effectiveTimezone)->utc();
                } catch (\Exception $e) {
                    Log::error('Error parsing availabilityWindowStart for UTC conversion', [
                        'availabilityWindowStart' => $ticketData->availability_window_start,
                        'effective_timezone' => $effectiveTimezone,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $availabilityWindowEndUtc = null;
            if ($ticketData->availability_window_end) {
                try {
                    $availabilityWindowEndUtc = Carbon::parse($ticketData->availability_window_end, $effectiveTimezone)->utc();
                } catch (\Exception $e) {
                    Log::error('Error parsing availabilityWindowEnd for UTC conversion', [
                        'availabilityWindowEnd' => $ticketData->availability_window_end,
                        'effective_timezone' => $effectiveTimezone,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $dataForModel = $ticketData->toArray();
            unset($dataForModel['id']);

            // Remove membership_discounts from model data as it's handled separately
            $membershipDiscounts = $dataForModel['membership_discounts'] ?? null;
            unset($dataForModel['membership_discounts']);

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
            if ($ticketData->event_occurrence_ids !== null) {
                $ticketDefinition->eventOccurrences()->sync($ticketData->event_occurrence_ids);
                Log::info('UpsertTicketDefinitionAction: Synced EventOccurrences for TicketDefinition ' . $ticketDefinition->id, ['event_occurrence_ids' => $ticketData->event_occurrence_ids]);
            }

            // Sync membership discounts if provided
            if ($membershipDiscounts !== null) {
                $syncData = [];
                foreach ($membershipDiscounts as $discount) {
                    $syncData[$discount['membership_level_id']] = [
                        'discount_type' => $discount['discount_type'],
                        'discount_value' => $discount['discount_value'],
                    ];
                }

                try {
                    $ticketDefinition->membershipDiscounts()->sync($syncData);
                    Log::info('UpsertTicketDefinitionAction: Synced MembershipDiscounts for TicketDefinition ' . $ticketDefinition->id, ['membership_discounts' => $syncData]);
                } catch (\Exception $e) {
                    Log::warning('UpsertTicketDefinitionAction: Failed to sync membership discounts - relationship may not exist', [
                        'ticketDefinitionId' => $ticketDefinition->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $ticketDefinition->refresh();
        });
    }
}
