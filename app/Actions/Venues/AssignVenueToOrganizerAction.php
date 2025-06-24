<?php

namespace App\Actions\Venues;

use App\Models\Venue;
use App\Models\Organizer;
use App\Models\User;
use App\Exceptions\UnauthorizedOperationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;


/**
 * Action to assign a public venue to a specific organizer (make it private).
 *
 * Business Logic:
 * - Public venues (organizer_id = null) can be used by any organizer
 * - Private venues (organizer_id = X) can only be used by that specific organizer
 * - Only platform admins can perform venue assignments to prevent abuse
 * - Validates venue is currently public before assignment
 * - Ensures target organizer exists and is active
 */
class AssignVenueToOrganizerAction
{
    /**
     * Assign a public venue to an organizer (convert public to private).
     *
     * @param Venue $venue The venue to assign (must be public)
     * @param Organizer $organizer The target organizer
     * @param User $admin The admin performing the action
     * @return Venue The updated venue
     *
     * @throws UnauthorizedOperationException If user is not authorized
     * @throws InvalidArgumentException If venue is not public or organizer is inactive
     */
    public function execute(Venue $venue, Organizer $organizer, User $admin): Venue
    {
        // Validate authorization - only platform admins can assign venues
        if (!$admin->hasRole('platform_admin')) {
            throw new UnauthorizedOperationException(
                'Only platform administrators can assign venues to organizers.'
            );
        }

        // Validate venue is currently public
        if (!$venue->isPublic()) {
            throw new InvalidArgumentException(
                "Venue '{$venue->name}' is already assigned to an organizer. Use transfer functionality instead."
            );
        }

        // Validate organizer is active
        if (!$organizer->is_active) {
            throw new InvalidArgumentException(
                "Cannot assign venue to inactive organizer '{$organizer->name}'."
            );
        }

        // Validate organizer exists and is not deleted
        if (!$organizer->exists) {
            throw new InvalidArgumentException(
                'Target organizer does not exist.'
            );
        }

        return DB::transaction(function () use ($venue, $organizer, $admin) {
            // Assign venue to organizer (convert from public to private)
            $venue->organizer_id = $organizer->id;
            $venue->save();

            return $venue->fresh();
        });
    }

    /**
     * Bulk assign multiple public venues to an organizer.
     *
     * @param array $venueIds Array of venue IDs to assign
     * @param Organizer $organizer The target organizer
     * @param User $admin The admin performing the action
     * @return array Results with success/failure counts and details
     *
     * @throws UnauthorizedOperationException If user is not authorized
     */
    public function executeBulk(array $venueIds, Organizer $organizer, User $admin): array
    {
        // Validate authorization
        if (!$admin->hasRole('platform_admin')) {
            throw new UnauthorizedOperationException(
                'Only platform administrators can assign venues to organizers.'
            );
        }

        // Validate organizer is active
        if (!$organizer->is_active) {
            throw new InvalidArgumentException(
                "Cannot assign venues to inactive organizer '{$organizer->name}'."
            );
        }

        $results = [
            'total_attempted' => count($venueIds),
            'successful_assignments' => 0,
            'failed_assignments' => 0,
            'success_details' => [],
            'failure_details' => [],
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name
            ]
        ];

        // Process each venue individually to collect detailed results
        foreach ($venueIds as $venueId) {
            try {
                $venue = Venue::findOrFail($venueId);

                // Skip already assigned venues
                if (!$venue->isPublic()) {
                    $results['failed_assignments']++;
                    $results['failure_details'][] = [
                        'venue_id' => $venueId,
                        'venue_name' => $venue->name,
                        'reason' => 'Venue already assigned to an organizer'
                    ];
                    continue;
                }

                // Execute assignment
                $this->execute($venue, $organizer, $admin);

                $results['successful_assignments']++;
                $results['success_details'][] = [
                    'venue_id' => $venueId,
                    'venue_name' => $venue->name,
                    'assigned_to' => $organizer->name
                ];
            } catch (\Exception $e) {
                $results['failed_assignments']++;
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
     * Validate if a venue can be assigned to an organizer.
     *
     * @param Venue $venue The venue to check
     * @param Organizer $organizer The target organizer
     * @return array Validation result with success status and message
     */
    public function validateAssignment(Venue $venue, Organizer $organizer): array
    {
        // Check if venue is public
        if (!$venue->isPublic()) {
            return [
                'can_assign' => false,
                'reason' => 'Venue is already assigned to an organizer',
                'current_organizer' => $venue->organizer->name ?? 'Unknown'
            ];
        }

        // Check if organizer is active
        if (!$organizer->is_active) {
            return [
                'can_assign' => false,
                'reason' => 'Target organizer is inactive'
            ];
        }

        return [
            'can_assign' => true,
            'message' => 'Venue can be assigned to organizer'
        ];
    }
}
