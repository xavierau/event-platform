<?php

namespace App\Services;

use App\Actions\Wishlist\AddToWishlistAction;
use App\Actions\Wishlist\RemoveFromWishlistAction;
use App\DataTransferObjects\WishlistData;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class WishlistService
{
    public function __construct(
        protected AddToWishlistAction $addToWishlistAction,
        protected RemoveFromWishlistAction $removeFromWishlistAction
    ) {}

    /**
     * Add an event to user's wishlist
     */
    public function addToWishlist(int $userId, int $eventId): bool
    {
        $wishlistData = WishlistData::validateAndCreate([
            'user_id' => $userId,
            'event_id' => $eventId,
        ]);

        return $this->addToWishlistAction->execute($wishlistData);
    }

    /**
     * Remove an event from user's wishlist
     */
    public function removeFromWishlist(int $userId, int $eventId): bool
    {
        $wishlistData = WishlistData::validateAndCreate([
            'user_id' => $userId,
            'event_id' => $eventId,
        ]);

        return $this->removeFromWishlistAction->execute($wishlistData);
    }

    /**
     * Toggle an event in user's wishlist (add if not present, remove if present)
     */
    public function toggleWishlist(int $userId, int $eventId): array
    {
        $isInWishlist = $this->isInWishlist($userId, $eventId);

        if ($isInWishlist) {
            $result = $this->removeFromWishlist($userId, $eventId);
            return [
                'added' => false,
                'in_wishlist' => false,
                'success' => $result,
            ];
        } else {
            $result = $this->addToWishlist($userId, $eventId);
            return [
                'added' => true,
                'in_wishlist' => true,
                'success' => $result,
            ];
        }
    }

    /**
     * Check if an event is in user's wishlist
     */
    public function isInWishlist(int $userId, int $eventId): bool
    {
        $user = User::findOrFail($userId);
        return $user->wishlistedEvents()->where('event_id', $eventId)->exists();
    }

    /**
     * Get user's wishlist events
     */
    public function getUserWishlist(int $userId, array $with = []): Collection
    {
        $user = User::findOrFail($userId);

        $query = $user->wishlistedEvents();

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get count of events in user's wishlist
     */
    public function getUserWishlistCount(int $userId): int
    {
        $user = User::findOrFail($userId);
        return $user->wishlistedEvents()->count();
    }

    /**
     * Clear all events from user's wishlist
     */
    public function clearUserWishlist(int $userId): bool
    {
        $user = User::findOrFail($userId);
        $user->wishlistedEvents()->detach();
        return true;
    }

    /**
     * Get user's wishlist formatted for frontend display.
     */
    public function getUserWishlistFormatted(int $userId): array
    {
        $user = User::findOrFail($userId);

        return $user->wishlistedEvents()
            ->where('event_status', 'published')
            ->with([
                'category',
                'organizer',
                'media',
                'eventOccurrences' => function ($query) {
                    $nowUtc = now()->utc();
                    $query->where('start_at_utc', '>=', $nowUtc)
                        ->whereIn('status', ['active', 'scheduled'])
                        ->orderBy('start_at_utc', 'asc');
                },
                'eventOccurrences.venue',
                'eventOccurrences.ticketDefinitions'
            ])
            ->get()
            ->map(function ($event) {
                $firstOccurrence = $event->eventOccurrences->first();
                $lastOccurrence = $event->eventOccurrences->last();
                $ticketData = $event->eventOccurrences->flatMap(function ($occurrence) {
                    return $occurrence->ticketDefinitions->map(function ($ticket) {
                        return [
                            'price' => $ticket->price,
                            'currency' => $ticket->currency
                        ];
                    });
                });

                $prices = $ticketData->pluck('price');
                $currency = $ticketData->first()['currency'] ?? 'USD';

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'href' => route('events.show', $event->id),
                    'image_url' => $event->getFirstMediaUrl('portrait_poster') ?: $event->getFirstMediaUrl('event_thumbnail') ?: 'https://via.placeholder.com/300x400.png?text=Event',
                    'price_from' => $prices->min() ? $prices->min() / 100 : null,
                    'price_to' => $prices->max() ? $prices->max() / 100 : null,
                    'currency' => $currency,
                    'date_range' => $this->formatDateRange(
                        $firstOccurrence ? $firstOccurrence->start_at_utc : null,
                        $lastOccurrence ? $lastOccurrence->start_at_utc : null,
                        $event->eventOccurrences->count()
                    ),
                    'venue_name' => $firstOccurrence && $firstOccurrence->venue
                        ? $firstOccurrence->venue->name
                        : null,
                    'category_name' => $event->category ? $event->category->name : null,
                    // Additional backend fields
                    'description' => $event->description,
                    'event_status' => $event->event_status,
                    'category' => $event->category ? [
                        'id' => $event->category->id,
                        'name' => $event->category->name,
                        'slug' => $event->category->slug,
                    ] : null,
                    'organizer' => $event->organizer ? [
                        'id' => $event->organizer->id,
                        'name' => $event->organizer->name,
                    ] : null,
                    'venue' => $firstOccurrence && $firstOccurrence->venue ? [
                        'id' => $firstOccurrence->venue->id,
                        'name' => $firstOccurrence->venue->name,
                    ] : null,
                    'created_at' => $event->created_at->toISOString(),
                    'updated_at' => $event->updated_at->toISOString(),
                ];
            })
            ->toArray();
    }

    /**
     * Helper to format a date range (copied from EventService for consistency).
     */
    private function formatDateRange($startDate, $endDate, int $occurrenceCount = 1): ?string
    {
        if (!$startDate) return null;
        $start = $this->carbonSafeParse($startDate);

        if ($occurrenceCount === 1 || !$endDate || $start->isSameDay($this->carbonSafeParse($endDate))) {
            return $start->translatedFormat('Y.m.d'); // Single date
        }
        $end = $this->carbonSafeParse($endDate);
        if ($start->isSameMonth($end)) {
            return $start->translatedFormat('Y.m.d') . '-' . $end->translatedFormat('d'); // e.g., 2025.06.13-15
        }
        return $start->translatedFormat('Y.m.d') . '-' . $end->translatedFormat('Y.m.d'); // e.g., 2025.06.13-2025.07.15
    }

    /**
     * Helper for safe Carbon parsing (copied from EventService for consistency).
     */
    private function carbonSafeParse($date, $timezone = null)
    {
        if ($date instanceof \Carbon\Carbon) {
            return $date;
        }
        try {
            return \Carbon\Carbon::parse($date, $timezone);
        } catch (\Exception $e) {
            return now()->utc(); // Fallback to UTC for consistency
        }
    }
}
