<?php

namespace App\Modules\TicketHold\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for TicketHold model.
 *
 * Transforms TicketHold model data for API responses and Inertia views.
 *
 * @mixin \App\Modules\TicketHold\Models\TicketHold
 */
class TicketHoldResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'internal_notes' => $this->when(
                $request->routeIs('admin.*'),
                $this->internal_notes
            ),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'is_expired' => $this->is_expired,
            'is_usable' => $this->is_usable,
            'total_allocated' => $this->total_allocated,
            'total_purchased' => $this->total_purchased,
            'total_remaining' => $this->total_remaining,
            'purchase_links_count' => $this->purchase_links_count ?? $this->whenLoaded('purchaseLinks', fn () => $this->purchaseLinks->count(), 0),
            'organizer' => new OrganizerSimpleResource($this->whenLoaded('organizer')),
            'event_occurrence' => new EventOccurrenceSimpleResource($this->whenLoaded('eventOccurrence')),
            'allocations' => HoldTicketAllocationResource::collection($this->whenLoaded('allocations')),
            'purchase_links' => PurchaseLinkResource::collection($this->whenLoaded('purchaseLinks')),
            'creator' => new UserSimpleResource($this->whenLoaded('creator')),
            'released_by' => new UserSimpleResource($this->whenLoaded('releasedByUser')),
        ];
    }
}
