<?php

namespace App\Modules\TicketHold\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for HoldTicketAllocation model.
 *
 * Transforms ticket allocation data for API responses and Inertia views.
 *
 * @mixin \App\Modules\TicketHold\Models\HoldTicketAllocation
 */
class HoldTicketAllocationResource extends JsonResource
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
            'ticket_definition_id' => $this->ticket_definition_id,
            'ticket_name' => $this->whenLoaded(
                'ticketDefinition',
                fn () => $this->ticketDefinition->getTranslation('name', app()->getLocale())
            ),
            'allocated_quantity' => $this->allocated_quantity,
            'purchased_quantity' => $this->purchased_quantity,
            'remaining_quantity' => $this->remaining_quantity,
            'is_available' => $this->is_available,
            'pricing_mode' => $this->pricing_mode->value,
            'pricing_mode_label' => $this->pricing_mode->label(),
            'custom_price' => $this->custom_price,
            'discount_percentage' => $this->discount_percentage,
            'ticket_definition' => new TicketDefinitionSimpleResource($this->whenLoaded('ticketDefinition')),
        ];
    }
}
