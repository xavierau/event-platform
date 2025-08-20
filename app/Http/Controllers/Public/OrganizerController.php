<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use Inertia\Inertia;
use Inertia\Response;

class OrganizerController extends Controller
{
    /**
     * Show organizer profile page with comments.
     */
    public function show(Organizer $organizer): Response
    {
        // Load organizer with events and comments
        $organizer->load([
            'events' => function ($query) {
                $query->where('event_status', 'published')
                    ->with(['eventOccurrences' => function ($q) {
                        $q->where('start_at_utc', '>', now()->utc())
                            ->orderBy('start_at_utc', 'asc');
                    }])
                    ->orderBy('created_at', 'desc')
                    ->limit(6); // Show latest 6 events
            }
        ]);

        // Load approved comments with user relationship, ordered by latest
        $comments = $organizer->comments()
            ->approved()
            ->with(['user' => fn($query) => $query->select('id', 'name')])
            ->latest()
            ->get();

        return Inertia::render('Public/OrganizerProfile', [
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'description' => $organizer->description,
                'contact_email' => $organizer->contact_email,
                'contact_phone' => $organizer->contact_phone,
                'website_url' => $organizer->website_url,
                'logo_url' => $organizer->getFirstMediaUrl('logo'),
                'banner_url' => $organizer->getFirstMediaUrl('banner'),
                'events' => $organizer->events->map(function ($event) {
                    $firstOccurrence = $event->eventOccurrences->first();
                    return [
                        'id' => $event->id,
                        'name' => $event->name,
                        'href' => route('events.show', $event->id),
                        'image_url' => $event->getFirstMediaUrl('portrait_poster'),
                        'price_range' => $event->getPriceRange(),
                        'next_occurrence_date' => $firstOccurrence?->start_at_utc?->format('Y.m.d'),
                        'venue_name' => $firstOccurrence?->venue?->name,
                    ];
                })->toArray(),
                'comments' => $comments->toArray(),
                'comment_config' => $organizer->comment_config,
                'total_events' => $organizer->events()->where('event_status', 'published')->count(),
            ]
        ]);
    }
}