<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventDetailResource extends JsonResource
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
            'category_tag' => $this->category?->getTranslation('name', app()->getLocale()),
            'duration_info' => $this->duration_info,
            'price_range' => $this->getPriceRange(),
            'discount_info' => $this->discount_info,
            'main_poster_url' => $this->getFirstMediaUrl('portrait_poster'),
            'thumbnail_url' => $this->getFirstMediaUrl('portrait_poster', 'thumb'),
            'landscape_poster_url' => $this->getFirstMediaUrl('landscape_poster'),
            'description_html' => $this->getTranslation('description', app()->getLocale()),
            'venue_name' => $this->getPrimaryVenue()?->getTranslation('name', app()->getLocale()),
            'venue_address' => $this->getPrimaryVenue()?->address,
            'occurrences' => EventOccurrenceResource::collection($this->eventOccurrences),
        ];
    }
}
