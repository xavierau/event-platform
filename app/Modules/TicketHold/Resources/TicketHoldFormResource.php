<?php

namespace App\Modules\TicketHold\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for TicketHold form data.
 *
 * Transforms TicketHold model data for edit forms with specific format requirements.
 *
 * @mixin \App\Modules\TicketHold\Models\TicketHold
 */
class TicketHoldFormResource extends JsonResource
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
            'event_occurrence_id' => $this->event_occurrence_id,
            'organizer_id' => $this->organizer_id,
            'name' => $this->name,
            'description' => $this->description,
            'internal_notes' => $this->internal_notes,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at?->format('Y-m-d\TH:i'),
            'allocations' => $this->whenLoaded(
                'allocations',
                fn () => $this->allocations->map(fn ($alloc) => [
                    'ticket_definition_id' => $alloc->ticket_definition_id,
                    'allocated_quantity' => $alloc->allocated_quantity,
                    'pricing_mode' => $alloc->pricing_mode->value,
                    'custom_price' => $alloc->custom_price,
                    'discount_percentage' => $alloc->discount_percentage,
                ])->toArray()
            ),
            'event_name' => $this->whenLoaded(
                'eventOccurrence',
                fn () => $this->eventOccurrence->event->getTranslation('name', app()->getLocale())
            ),
            'occurrence_date' => $this->whenLoaded(
                'eventOccurrence',
                fn () => $this->eventOccurrence->start_at->format('Y-m-d H:i')
            ),
        ];
    }
}
