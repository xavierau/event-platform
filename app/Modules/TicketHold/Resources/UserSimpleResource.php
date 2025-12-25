<?php

namespace App\Modules\TicketHold\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Simple API Resource for User model.
 *
 * Provides a minimal representation of user data for nested resources.
 *
 * @mixin \App\Models\User
 */
class UserSimpleResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->when(
                $request->routeIs('admin.*'),
                $this->email
            ),
        ];
    }
}
