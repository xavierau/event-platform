<?php

namespace App\Services;

use App\Actions\TicketDefinition\UpsertTicketDefinitionAction;
use App\DataTransferObjects\TicketDefinitionData;
use App\Models\TicketDefinition;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class TicketDefinitionService
{
    protected UpsertTicketDefinitionAction $upsertTicketDefinitionAction;

    public function __construct(UpsertTicketDefinitionAction $upsertTicketDefinitionAction)
    {
        $this->upsertTicketDefinitionAction = $upsertTicketDefinitionAction;
    }

    public function getAllTicketDefinitions(array $filters = [], int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        // TODO: Implement filtering based on $filters if needed
        return TicketDefinition::query()
            ->with($with)
            ->latest()
            ->paginate($perPage);
    }

    public function findTicketDefinitionById(int $id, array $with = []): ?TicketDefinition
    {
        return TicketDefinition::with($with)->find($id);
    }

    public function createTicketDefinition(TicketDefinitionData $ticketDefinitionData): TicketDefinition
    {
        Log::info('TicketDefinitionService: Creating ticket definition via action', $ticketDefinitionData->toArray());
        return $this->upsertTicketDefinitionAction->execute($ticketDefinitionData);
    }

    public function updateTicketDefinition(int $ticketDefinitionId, TicketDefinitionData $ticketDefinitionData): TicketDefinition
    {
        Log::info('TicketDefinitionService: Updating ticket definition ' . $ticketDefinitionId . ' via action', $ticketDefinitionData->toArray());
        return $this->upsertTicketDefinitionAction->execute($ticketDefinitionData, $ticketDefinitionId);
    }

    public function deleteTicketDefinition(int $ticketDefinitionId): void
    {
        $ticketDefinition = TicketDefinition::findOrFail($ticketDefinitionId);
        // Add any related cleanup logic if necessary before deleting
        $ticketDefinition->delete();
        Log::info('TicketDefinitionService: Deleted ticket definition ' . $ticketDefinitionId);
    }
}
