<?php

namespace App\Modules\TicketHold\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for PurchaseLink model.
 *
 * Transforms purchase link data for API responses and Inertia views.
 *
 * @mixin \App\Modules\TicketHold\Models\PurchaseLink
 */
class PurchaseLinkResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'full_url' => $this->full_url,
            'quantity_mode' => $this->quantity_mode->value,
            'quantity_mode_label' => $this->quantity_mode->label(),
            'quantity_limit' => $this->quantity_limit,
            'quantity_purchased' => $this->quantity_purchased,
            'remaining_quantity' => $this->remaining_quantity,
            'is_anonymous' => $this->is_anonymous,
            'is_expired' => $this->is_expired,
            'is_usable' => $this->is_usable,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'notes' => $this->when(
                $request->routeIs('admin.*'),
                $this->notes
            ),
            'created_at' => $this->created_at->toIso8601String(),
            'assigned_user' => new UserSimpleResource($this->whenLoaded('assignedUser')),
            'revoked_by' => new UserSimpleResource($this->whenLoaded('revokedByUser')),
            'ticket_hold' => new TicketHoldResource($this->whenLoaded('ticketHold')),
        ];
    }
}
