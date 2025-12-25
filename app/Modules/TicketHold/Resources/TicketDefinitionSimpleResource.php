<?php

namespace App\Modules\TicketHold\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Simple API Resource for TicketDefinition model.
 *
 * Provides a minimal representation of ticket definition data for nested resources.
 *
 * @mixin \App\Models\TicketDefinition
 */
class TicketDefinitionSimpleResource extends JsonResource
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
            'name' => $this->getTranslation('name', app()->getLocale()),
            'price' => $this->price,
            'quantity' => $this->quantity,
            'total_quantity' => $this->total_quantity,
            'available_quantity' => $this->available_quantity ?? $this->quantity,
        ];
    }
}
