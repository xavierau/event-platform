<?php

namespace App\Modules\TicketHold\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Simple API Resource for EventOccurrence model.
 *
 * Provides a minimal representation of event occurrence data for nested resources.
 *
 * @mixin \App\Models\EventOccurrence
 */
class EventOccurrenceSimpleResource extends JsonResource
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
            'event_id' => $this->event_id,
            'event_name' => $this->whenLoaded(
                'event',
                fn () => $this->event->getTranslation('name', app()->getLocale())
            ),
            'event' => $this->when(
                $this->relationLoaded('event'),
                fn () => [
                    'id' => $this->event->id,
                    'name' => $this->event->getTranslations('name'),
                    'organizer_id' => $this->event->organizer_id,
                ]
            ),
            'start_at' => $this->start_at->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
        ];
    }
}
