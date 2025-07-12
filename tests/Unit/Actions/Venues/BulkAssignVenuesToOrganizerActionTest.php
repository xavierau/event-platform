<?php

namespace Tests\Unit\Actions\Venues;

use Tests\TestCase;
use App\Actions\Venues\BulkAssignVenuesToOrganizerAction;
use App\Models\User;
use App\Models\Venue;
use App\Models\Organizer;
use App\Exceptions\UnauthorizedOperationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use InvalidArgumentException;

class BulkAssignVenuesToOrganizerActionTest extends TestCase
{
    use RefreshDatabase;

    protected BulkAssignVenuesToOrganizerAction $action;
    protected User $admin;
    protected User $nonAdmin;
    protected Organizer $organizer;
    protected Organizer $inactiveOrganizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new BulkAssignVenuesToOrganizerAction();

        // Create roles
        Role::create(['name' => 'platform_admin', 'guard_name' => 'web']);
        Role::create(['name' => 'user', 'guard_name' => 'web']);

        // Create users
        $this->admin = User::factory()->create();
        $this->admin->assignRole('platform_admin');

        $this->nonAdmin = User::factory()->create();
        $this->nonAdmin->assignRole('user');

        // Create organizers
        $this->organizer = Organizer::factory()->create(['is_active' => true]);
        $this->inactiveOrganizer = Organizer::factory()->create(['is_active' => false]);
    }

    /** @test */
    public function it_can_execute_bulk_assignment_with_small_batch()
    {
        // Create public venues
        $venues = Venue::factory()->count(5)->create(['organizer_id' => null]);
        $venueIds = $venues->pluck('id')->toArray();

        $result = $this->action->execute($venueIds, $this->organizer, $this->admin);

        // Verify results structure
        $this->assertArrayHasKey('total_attempted', $result);
        $this->assertArrayHasKey('successful_assignments', $result);
        $this->assertArrayHasKey('failed_assignments', $result);
        $this->assertArrayHasKey('batches_processed', $result);
        $this->assertArrayHasKey('processing_time_seconds', $result);
        $this->assertArrayHasKey('progress_tracking', $result);

        // Verify assignments
        $this->assertEquals(5, $result['total_attempted']);
        $this->assertEquals(5, $result['successful_assignments']);
        $this->assertEquals(0, $result['failed_assignments']);
        $this->assertEquals(1, $result['batches_processed']);

        // Verify all venues were assigned
        foreach ($venueIds as $venueId) {
            $this->assertDatabaseHas('venues', [
                'id' => $venueId,
                'organizer_id' => $this->organizer->id
            ]);
        }
    }

    /** @test */
    public function it_processes_large_dataset_in_batches()
    {
        // Create 150 public venues (should be processed in multiple batches)
        $venues = Venue::factory()->count(150)->create(['organizer_id' => null]);
        $venueIds = $venues->pluck('id')->toArray();

        $result = $this->action->execute($venueIds, $this->organizer, $this->admin, ['batch_size' => 50]);

        // Verify batch processing
        $this->assertEquals(150, $result['total_attempted']);
        $this->assertEquals(150, $result['successful_assignments']);
        $this->assertEquals(0, $result['failed_assignments']);
        $this->assertEquals(3, $result['batches_processed']); // 150 / 50 = 3 batches

        // Verify progress tracking shows batches
        $this->assertCount(3, $result['progress_tracking']);
        $this->assertEquals(50, $result['progress_tracking'][0]['batch_size']);
        $this->assertEquals(50, $result['progress_tracking'][1]['batch_size']);
        $this->assertEquals(50, $result['progress_tracking'][2]['batch_size']);
    }

    /** @test */
    public function it_handles_mixed_success_and_failure_scenarios()
    {
        // Create mix of public and private venues
        $publicVenues = Venue::factory()->count(3)->create(['organizer_id' => null]);
        $privateVenues = Venue::factory()->count(2)->create(['organizer_id' => $this->organizer->id]);

        $allVenueIds = $publicVenues->pluck('id')->merge($privateVenues->pluck('id'))->toArray();

        $result = $this->action->execute($allVenueIds, $this->organizer, $this->admin);

        // Verify mixed results
        $this->assertEquals(5, $result['total_attempted']);
        $this->assertEquals(3, $result['successful_assignments']);
        $this->assertEquals(2, $result['failed_assignments']);

        // Verify failure details are captured
        $this->assertCount(2, $result['failure_details']);
        foreach ($result['failure_details'] as $failure) {
            $this->assertStringContainsString('already assigned', $failure['reason']);
        }
    }

    /** @test */
    public function it_tracks_processing_time_and_performance_metrics()
    {
        $venues = Venue::factory()->count(10)->create(['organizer_id' => null]);
        $venueIds = $venues->pluck('id')->toArray();

        $result = $this->action->execute($venueIds, $this->organizer, $this->admin);

        // Verify performance metrics
        $this->assertArrayHasKey('processing_time_seconds', $result);
        $this->assertArrayHasKey('average_time_per_venue', $result);
        $this->assertArrayHasKey('venues_per_second', $result);

        $this->assertIsFloat($result['processing_time_seconds']);
        $this->assertIsFloat($result['average_time_per_venue']);
        $this->assertIsFloat($result['venues_per_second']);
        $this->assertGreaterThan(0, $result['processing_time_seconds']);
    }

    /** @test */
    public function it_throws_exception_when_non_admin_attempts_bulk_assignment()
    {
        $venueIds = [1, 2, 3];

        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Only platform administrators can perform bulk venue assignments.');

        $this->action->execute($venueIds, $this->organizer, $this->nonAdmin);
    }

    /** @test */
    public function it_throws_exception_when_organizer_is_inactive()
    {
        $venueIds = [1, 2, 3];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot assign venues to inactive organizer");

        $this->action->execute($venueIds, $this->inactiveOrganizer, $this->admin);
    }

    /** @test */
    public function it_throws_exception_when_venue_ids_array_is_empty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Venue IDs array cannot be empty.');

        $this->action->execute([], $this->organizer, $this->admin);
    }

    /** @test */
    public function it_throws_exception_when_venue_ids_exceed_maximum_limit()
    {
        // Create array with more than maximum allowed venues
        $venueIds = range(1, 1001); // Assuming max limit is 1000

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot process more than 1000 venues in a single bulk operation.');

        $this->action->execute($venueIds, $this->organizer, $this->admin);
    }

    /** @test */
    public function it_handles_database_transaction_failure_gracefully()
    {
        $venues = Venue::factory()->count(3)->create(['organizer_id' => null]);
        $venueIds = $venues->pluck('id')->toArray();

        // Mock database to simulate transaction failure
        // Note: This is a simplified test - in reality, you might mock the database connection

        // Create an invalid organizer ID to trigger a constraint violation
        $invalidOrganizer = new Organizer();
        $invalidOrganizer->id = 999999; // Non-existent ID
        $invalidOrganizer->name = 'Invalid Organizer';
        $invalidOrganizer->is_active = true;
        $invalidOrganizer->exists = false;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Target organizer does not exist.');

        $this->action->execute($venueIds, $invalidOrganizer, $this->admin);
    }

    /** @test */
    public function it_can_validate_bulk_assignment_before_execution()
    {
        $publicVenues = Venue::factory()->count(2)->create(['organizer_id' => null]);
        $privateVenues = Venue::factory()->count(1)->create(['organizer_id' => $this->organizer->id]);

        $allVenueIds = $publicVenues->pluck('id')->merge($privateVenues->pluck('id'))->toArray();

        $validation = $this->action->validateBulkAssignment($allVenueIds, $this->organizer);

        // Verify validation results
        $this->assertArrayHasKey('total_venues', $validation);
        $this->assertArrayHasKey('assignable_venues', $validation);
        $this->assertArrayHasKey('non_assignable_venues', $validation);
        $this->assertArrayHasKey('validation_details', $validation);

        $this->assertEquals(3, $validation['total_venues']);
        $this->assertEquals(2, $validation['assignable_venues']);
        $this->assertEquals(1, $validation['non_assignable_venues']);
        $this->assertCount(3, $validation['validation_details']);
    }

    /** @test */
    public function it_supports_custom_batch_size_configuration()
    {
        $venues = Venue::factory()->count(20)->create(['organizer_id' => null]);
        $venueIds = $venues->pluck('id')->toArray();

        $result = $this->action->execute($venueIds, $this->organizer, $this->admin, ['batch_size' => 5]);

        // Verify custom batch size was used
        $this->assertEquals(4, $result['batches_processed']); // 20 / 5 = 4 batches

        foreach ($result['progress_tracking'] as $batchProgress) {
            $this->assertEquals(5, $batchProgress['batch_size']);
        }
    }

    /** @test */
    public function it_provides_detailed_progress_tracking_information()
    {
        $venues = Venue::factory()->count(15)->create(['organizer_id' => null]);
        $venueIds = $venues->pluck('id')->toArray();

        $result = $this->action->execute($venueIds, $this->organizer, $this->admin, ['batch_size' => 10]);

        // Verify progress tracking structure
        $this->assertCount(2, $result['progress_tracking']); // 15 venues in 2 batches (10 + 5)

        foreach ($result['progress_tracking'] as $index => $batchProgress) {
            $this->assertArrayHasKey('batch_number', $batchProgress);
            $this->assertArrayHasKey('batch_size', $batchProgress);
            $this->assertArrayHasKey('successful_in_batch', $batchProgress);
            $this->assertArrayHasKey('failed_in_batch', $batchProgress);
            $this->assertArrayHasKey('batch_processing_time', $batchProgress);

            $this->assertEquals($index + 1, $batchProgress['batch_number']);
        }
    }

    /** @test */
    public function it_handles_non_existent_venue_ids_gracefully()
    {
        $existingVenues = Venue::factory()->count(2)->create(['organizer_id' => null]);
        $nonExistentIds = [999998, 999999];
        $allIds = $existingVenues->pluck('id')->merge($nonExistentIds)->toArray();

        $result = $this->action->execute($allIds, $this->organizer, $this->admin);

        // Verify handling of non-existent venues
        $this->assertEquals(4, $result['total_attempted']);
        $this->assertEquals(2, $result['successful_assignments']);
        $this->assertEquals(2, $result['failed_assignments']);

        // Verify failure details mention non-existent venues
        $this->assertCount(2, $result['failure_details']);
        foreach ($result['failure_details'] as $failure) {
            $this->assertStringContainsString('not found', $failure['reason']);
        }
    }
}
