<?php

namespace App\Actions\Venues;

use App\Enums\RoleNameEnum;
use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Action for sophisticated bulk venue assignments to organizers.
 *
 * Features:
 * - Batch processing for large operations
 * - Detailed progress tracking
 * - Performance metrics
 * - Comprehensive error handling
 * - Validation before execution
 * - Configurable batch sizes
 */
class BulkAssignVenuesToOrganizerAction
{
    /** @var int Maximum number of venues that can be processed in a single operation */
    private const MAX_VENUES_PER_OPERATION = 1000;

    /** @var int Default batch size for processing */
    private const DEFAULT_BATCH_SIZE = 100;

    /**
     * Execute bulk venue assignment to an organizer.
     *
     * @param  array  $venueIds  Array of venue IDs to assign
     * @param  Organizer  $organizer  The target organizer
     * @param  User  $admin  The admin performing the action
     * @param  array  $options  Configuration options (batch_size, etc.)
     * @return array Comprehensive results with progress tracking and performance metrics
     *
     * @throws UnauthorizedOperationException If user is not authorized
     * @throws InvalidArgumentException If validation fails
     */
    public function execute(array $venueIds, Organizer $organizer, User $admin, array $options = []): array
    {
        $startTime = microtime(true);

        // Validate inputs
        $this->validateInputs($venueIds, $organizer, $admin);

        // Extract options
        $batchSize = $options['batch_size'] ?? self::DEFAULT_BATCH_SIZE;

        // Initialize results structure
        $results = [
            'total_attempted' => count($venueIds),
            'successful_assignments' => 0,
            'failed_assignments' => 0,
            'batches_processed' => 0,
            'processing_time_seconds' => 0.0,
            'average_time_per_venue' => 0.0,
            'venues_per_second' => 0.0,
            'progress_tracking' => [],
            'success_details' => [],
            'failure_details' => [],
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
            ],
        ];

        // Process venues in batches
        $batches = array_chunk($venueIds, $batchSize);

        foreach ($batches as $batchIndex => $batchVenueIds) {
            $batchStartTime = microtime(true);

            $batchResults = $this->processBatch($batchVenueIds, $organizer, $admin, $batchIndex + 1);

            $batchEndTime = microtime(true);
            $batchProcessingTime = $batchEndTime - $batchStartTime;

            // Update overall results
            $results['successful_assignments'] += $batchResults['successful_assignments'];
            $results['failed_assignments'] += $batchResults['failed_assignments'];
            $results['batches_processed']++;

            // Merge details
            $results['success_details'] = array_merge($results['success_details'], $batchResults['success_details']);
            $results['failure_details'] = array_merge($results['failure_details'], $batchResults['failure_details']);

            // Track batch progress
            $results['progress_tracking'][] = [
                'batch_number' => $batchIndex + 1,
                'batch_size' => count($batchVenueIds),
                'successful_in_batch' => $batchResults['successful_assignments'],
                'failed_in_batch' => $batchResults['failed_assignments'],
                'batch_processing_time' => round($batchProcessingTime, 4),
            ];
        }

        // Calculate final performance metrics
        $endTime = microtime(true);
        $totalProcessingTime = $endTime - $startTime;

        $results['processing_time_seconds'] = round($totalProcessingTime, 4);
        $results['average_time_per_venue'] = $results['total_attempted'] > 0
            ? round($totalProcessingTime / $results['total_attempted'], 6)
            : 0.0;
        $results['venues_per_second'] = $totalProcessingTime > 0
            ? round($results['total_attempted'] / $totalProcessingTime, 2)
            : 0.0;

        return $results;
    }

    /**
     * Validate bulk assignment before execution.
     *
     * @param  array  $venueIds  Array of venue IDs to validate
     * @param  Organizer  $organizer  The target organizer
     * @return array Validation results with detailed breakdown
     */
    public function validateBulkAssignment(array $venueIds, Organizer $organizer): array
    {
        $results = [
            'total_venues' => count($venueIds),
            'assignable_venues' => 0,
            'non_assignable_venues' => 0,
            'validation_details' => [],
        ];

        foreach ($venueIds as $venueId) {
            try {
                $venue = Venue::find($venueId);

                if (! $venue) {
                    $results['non_assignable_venues']++;
                    $results['validation_details'][] = [
                        'venue_id' => $venueId,
                        'venue_name' => 'Unknown',
                        'can_assign' => false,
                        'reason' => 'Venue not found',
                    ];

                    continue;
                }

                $validation = $this->validateSingleVenueAssignment($venue, $organizer);

                if ($validation['can_assign']) {
                    $results['assignable_venues']++;
                } else {
                    $results['non_assignable_venues']++;
                }

                $results['validation_details'][] = array_merge([
                    'venue_id' => $venueId,
                    'venue_name' => $venue->name,
                ], $validation);
            } catch (\Exception $e) {
                $results['non_assignable_venues']++;
                $results['validation_details'][] = [
                    'venue_id' => $venueId,
                    'venue_name' => 'Unknown',
                    'can_assign' => false,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Validate inputs for bulk assignment.
     *
     * @param  array  $venueIds  Array of venue IDs
     * @param  Organizer  $organizer  Target organizer
     * @param  User  $admin  Admin performing action
     *
     * @throws UnauthorizedOperationException
     * @throws InvalidArgumentException
     */
    private function validateInputs(array $venueIds, Organizer $organizer, User $admin): void
    {
        // Validate authorization
        if (! $admin->hasRole(RoleNameEnum::ADMIN)) {
            throw new UnauthorizedOperationException(
                'Only platform administrators can perform bulk venue assignments.'
            );
        }

        // Validate venue IDs array
        if (empty($venueIds)) {
            throw new InvalidArgumentException('Venue IDs array cannot be empty.');
        }

        // Validate maximum limit
        if (count($venueIds) > self::MAX_VENUES_PER_OPERATION) {
            throw new InvalidArgumentException(
                'Cannot process more than '.self::MAX_VENUES_PER_OPERATION.' venues in a single bulk operation.'
            );
        }

        // Validate organizer
        if (! $organizer->exists) {
            throw new InvalidArgumentException('Target organizer does not exist.');
        }

        if (! $organizer->is_active) {
            throw new InvalidArgumentException(
                "Cannot assign venues to inactive organizer '{$organizer->name}'."
            );
        }
    }

    /**
     * Process a single batch of venues.
     *
     * @param  array  $batchVenueIds  Venue IDs in this batch
     * @param  Organizer  $organizer  Target organizer
     * @param  User  $admin  Admin performing action
     * @param  int  $batchNumber  Current batch number
     * @return array Batch processing results
     */
    private function processBatch(array $batchVenueIds, Organizer $organizer, User $admin, int $batchNumber): array
    {
        $batchResults = [
            'successful_assignments' => 0,
            'failed_assignments' => 0,
            'success_details' => [],
            'failure_details' => [],
        ];

        // Process each venue in the batch
        foreach ($batchVenueIds as $venueId) {
            try {
                $venue = Venue::find($venueId);

                if (! $venue) {
                    $batchResults['failed_assignments']++;
                    $batchResults['failure_details'][] = [
                        'venue_id' => $venueId,
                        'venue_name' => 'Unknown',
                        'reason' => 'Venue not found',
                        'batch_number' => $batchNumber,
                    ];

                    continue;
                }

                // Check if venue can be assigned
                if (! $venue->isPublic()) {
                    $batchResults['failed_assignments']++;
                    $batchResults['failure_details'][] = [
                        'venue_id' => $venueId,
                        'venue_name' => $venue->name,
                        'reason' => 'Venue already assigned to an organizer',
                        'batch_number' => $batchNumber,
                    ];

                    continue;
                }

                // Execute assignment within transaction
                DB::transaction(function () use ($venue, $organizer, &$batchResults, $batchNumber) {
                    $venue->organizer_id = $organizer->id;
                    $venue->save();

                    $batchResults['successful_assignments']++;
                    $batchResults['success_details'][] = [
                        'venue_id' => $venue->id,
                        'venue_name' => $venue->name,
                        'assigned_to' => $organizer->name,
                        'batch_number' => $batchNumber,
                    ];
                });
            } catch (\Exception $e) {
                $batchResults['failed_assignments']++;
                $batchResults['failure_details'][] = [
                    'venue_id' => $venueId,
                    'venue_name' => $venue->name ?? 'Unknown',
                    'reason' => $e->getMessage(),
                    'batch_number' => $batchNumber,
                ];
            }
        }

        return $batchResults;
    }

    /**
     * Validate if a single venue can be assigned to an organizer.
     *
     * @param  Venue  $venue  The venue to check
     * @param  Organizer  $organizer  The target organizer
     * @return array Validation result
     */
    private function validateSingleVenueAssignment(Venue $venue, Organizer $organizer): array
    {
        // Check if venue is public
        if (! $venue->isPublic()) {
            return [
                'can_assign' => false,
                'reason' => 'Venue is already assigned to an organizer',
                'current_organizer' => $venue->organizer->name ?? 'Unknown',
            ];
        }

        // Check if organizer is active
        if (! $organizer->is_active) {
            return [
                'can_assign' => false,
                'reason' => 'Target organizer is inactive',
            ];
        }

        return [
            'can_assign' => true,
            'message' => 'Venue can be assigned to organizer',
        ];
    }
}
