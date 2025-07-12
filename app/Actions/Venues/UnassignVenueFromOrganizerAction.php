<?php

namespace App\Actions\Venues;

use App\Models\Venue;
use App\Models\User;
use App\Models\Event;
use App\Exceptions\UnauthorizedOperationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Action to unassign a venue from its organizer (convert private to public).
 *
 * Business Logic:
 * - Private venues (organizer_id = X) can only be used by that specific organizer
 * - Public venues (organizer_id = null) can be used by any organizer
 * - Only platform admins can perform venue unassignments to prevent abuse
 * - Validates venue is currently assigned before unassignment
 * - Checks for existing event dependencies to prevent data inconsistency
 */
class UnassignVenueFromOrganizerAction
{
    /**
     * Unassign a venue from its organizer (convert private to public).
     *
     * @param Venue $venue The venue to unassign (must be currently assigned)
     * @param User $admin The admin performing the action
     * @param bool $forceUnassign Whether to force unassignment even with existing events
     * @return Venue The updated venue
     *
     * @throws UnauthorizedOperationException If user is not authorized
     * @throws InvalidArgumentException If venue is already public or has event dependencies
     */
    public function execute(Venue $venue, User $admin, bool $forceUnassign = false): Venue
    {
        // Validate authorization - only platform admins can unassign venues
        if (!$admin->hasRole('platform_admin')) {
            throw new UnauthorizedOperationException(
                'Only platform administrators can unassign venues from organizers.'
            );
        }

        // Validate venue is currently assigned to an organizer
        if ($venue->isPublic()) {
            throw new InvalidArgumentException(
                "Venue '{$venue->name}' is already public and not assigned to any organizer."
            );
        }

        // Check for existing event dependencies unless forced
        // Note: Event-venue relationship not yet implemented, so no dependencies to check
        if (!$forceUnassign) {
            $eventCount = $this->getEventDependencies($venue)->count();
            if ($eventCount > 0) {
                throw new InvalidArgumentException(
                    "Cannot unassign venue '{$venue->name}' because it has {$eventCount} associated events. Use force option to override."
                );
            }
        }

        return DB::transaction(function () use ($venue, $admin) {
            $originalOrganizerName = $venue->organizer->name ?? 'Unknown';

            // Unassign venue from organizer (convert from private to public)
            $venue->organizer_id = null;
            $venue->save();

            return $venue->fresh();
        });
    }

    /**
     * Bulk unassign multiple venues from their organizers.
     *
     * @param array $venueIds Array of venue IDs to unassign
     * @param User $admin The admin performing the action
     * @param bool $forceUnassign Whether to force unassignment even with existing events
     * @return array Results with success/failure counts and details
     *
     * @throws UnauthorizedOperationException If user is not authorized
     */
    public function executeBulk(array $venueIds, User $admin, bool $forceUnassign = false): array
    {
        // Validate authorization
        if (!$admin->hasRole('platform_admin')) {
            throw new UnauthorizedOperationException(
                'Only platform administrators can unassign venues from organizers.'
            );
        }

        $results = [
            'total_attempted' => count($venueIds),
            'successful_unassignments' => 0,
            'failed_unassignments' => 0,
            'success_details' => [],
            'failure_details' => [],
            'force_unassign_used' => $forceUnassign
        ];

        // Process each venue individually to collect detailed results
        foreach ($venueIds as $venueId) {
            try {
                $venue = Venue::findOrFail($venueId);

                // Skip already public venues
                if ($venue->isPublic()) {
                    $results['failed_unassignments']++;
                    $results['failure_details'][] = [
                        'venue_id' => $venueId,
                        'venue_name' => $venue->name,
                        'reason' => 'Venue is already public'
                    ];
                    continue;
                }

                $originalOrganizerName = $venue->organizer->name ?? 'Unknown';

                // Execute unassignment
                $this->execute($venue, $admin, $forceUnassign);

                $results['successful_unassignments']++;
                $results['success_details'][] = [
                    'venue_id' => $venueId,
                    'venue_name' => $venue->name,
                    'previous_organizer' => $originalOrganizerName
                ];
            } catch (\Exception $e) {
                $results['failed_unassignments']++;
                $results['failure_details'][] = [
                    'venue_id' => $venueId,
                    'venue_name' => $venue->name ?? 'Unknown',
                    'reason' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Validate if a venue can be unassigned from its organizer.
     *
     * @param Venue $venue The venue to check
     * @param bool $forceUnassign Whether force unassignment is intended
     * @return array Validation result with success status and message
     */
    public function validateUnassignment(Venue $venue, bool $forceUnassign = false): array
    {
        // Check if venue is assigned to an organizer
        if ($venue->isPublic()) {
            return [
                'can_unassign' => false,
                'reason' => 'Venue is already public and not assigned to any organizer'
            ];
        }

        // Check for event dependencies if not forcing
        if (!$forceUnassign) {
            $events = $this->getEventDependencies($venue);
            $eventCount = $events->count();

            if ($eventCount > 0) {
                return [
                    'can_unassign' => false,
                    'reason' => "Venue has {$eventCount} associated events",
                    'event_count' => $eventCount,
                    'can_force' => true,
                    'events_sample' => $events->take(3)->pluck('name')->toArray()
                ];
            }
        }

        return [
            'can_unassign' => true,
            'message' => 'Venue can be unassigned from organizer',
            'current_organizer' => $venue->organizer->name ?? 'Unknown',
            'force_used' => $forceUnassign
        ];
    }

    /**
     * Get event dependencies for a venue.
     *
     * @param Venue $venue The venue to check
     * @return \Illuminate\Database\Eloquent\Collection Collection of events using this venue
     */
    public function getEventDependencies(Venue $venue)
    {
        // Note: Event-venue relationship not yet implemented in current system
        // This method will be functional once venue_id is added to events table
        try {
            return Event::where('venue_id', $venue->id)
                ->select('id', 'name', 'start_date', 'end_date')
                ->get();
        } catch (\Exception $e) {
            // Return empty collection if venue_id column doesn't exist yet
            return collect();
        }
    }

    /**
     * Get detailed event dependencies information for a venue.
     *
     * @param Venue $venue The venue to check
     * @return array Detailed information about event dependencies
     */
    public function getEventDependenciesInfo(Venue $venue): array
    {
        $events = $this->getEventDependencies($venue);
        $now = now();

        $upcomingEvents = $events->where('start_date', '>', $now);
        $pastEvents = $events->where('end_date', '<', $now);
        $ongoingEvents = $events->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now);

        return [
            'total_events' => $events->count(),
            'upcoming_events' => $upcomingEvents->count(),
            'ongoing_events' => $ongoingEvents->count(),
            'past_events' => $pastEvents->count(),
            'events_sample' => $events->take(5)->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                ];
            })->toArray(),
            'has_dependencies' => $events->count() > 0
        ];
    }

    /**
     * Transfer venue from one organizer to another (reassignment).
     *
     * @param Venue $venue The venue to transfer
     * @param int $newOrganizerId The target organizer ID (null for public)
     * @param User $admin The admin performing the action
     * @param bool $forceTransfer Whether to force transfer even with event dependencies
     * @return Venue The updated venue
     *
     * @throws UnauthorizedOperationException If user is not authorized
     * @throws InvalidArgumentException If venue transfer is invalid
     */
    public function transferVenue(Venue $venue, ?int $newOrganizerId, User $admin, bool $forceTransfer = false): Venue
    {
        // Validate authorization
        if (!$admin->hasRole('platform_admin')) {
            throw new UnauthorizedOperationException(
                'Only platform administrators can transfer venues between organizers.'
            );
        }

        // Check for existing event dependencies unless forced
        // Note: Event-venue relationship not yet implemented, so no dependencies to check
        if (!$forceTransfer) {
            $eventCount = $this->getEventDependencies($venue)->count();
            if ($eventCount > 0) {
                throw new InvalidArgumentException(
                    "Cannot transfer venue '{$venue->name}' because it has {$eventCount} associated events. Use force option to override."
                );
            }
        }

        return DB::transaction(function () use ($venue, $newOrganizerId, $admin) {
            $originalOrganizerName = $venue->organizer->name ?? 'Public';

            // Update venue assignment
            $venue->organizer_id = $newOrganizerId;
            $venue->save();

            $newOrganizerName = $newOrganizerId ?
                ($venue->fresh()->organizer->name ?? 'Unknown') :
                'Public';

            return $venue->fresh();
        });
    }
}
