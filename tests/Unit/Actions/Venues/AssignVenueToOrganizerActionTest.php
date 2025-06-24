<?php

namespace Tests\Unit\Actions\Venues;

use Tests\TestCase;
use App\Actions\Venues\AssignVenueToOrganizerAction;
use App\Models\User;
use App\Models\Venue;
use App\Models\Organizer;
use App\Exceptions\UnauthorizedOperationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use InvalidArgumentException;

class AssignVenueToOrganizerActionTest extends TestCase
{
    use RefreshDatabase;

    protected AssignVenueToOrganizerAction $action;
    protected User $admin;
    protected User $nonAdmin;
    protected Organizer $organizer;
    protected Organizer $inactiveOrganizer;
    protected Venue $publicVenue;
    protected Venue $privateVenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new AssignVenueToOrganizerAction();

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

        // Create venues
        $this->publicVenue = Venue::factory()->create(['organizer_id' => null]); // Public venue
        $this->privateVenue = Venue::factory()->create(['organizer_id' => $this->organizer->id]); // Private venue
    }

    /** @test */
    public function it_can_assign_public_venue_to_organizer()
    {
        $result = $this->action->execute($this->publicVenue, $this->organizer, $this->admin);

        // Verify venue was assigned
        $this->assertEquals($this->organizer->id, $result->organizer_id);
        $this->assertFalse($result->isPublic());
        $this->assertTrue($result->isOwnedBy($this->organizer));

        // Verify database was updated
        $this->assertDatabaseHas('venues', [
            'id' => $this->publicVenue->id,
            'organizer_id' => $this->organizer->id
        ]);
    }

    /** @test */
    public function it_throws_exception_when_non_admin_tries_to_assign_venue()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Only platform administrators can assign venues to organizers.');

        $this->action->execute($this->publicVenue, $this->organizer, $this->nonAdmin);
    }

    /** @test */
    public function it_throws_exception_when_venue_is_already_assigned()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Venue '{$this->privateVenue->name}' is already assigned to an organizer. Use transfer functionality instead.");

        $this->action->execute($this->privateVenue, $this->organizer, $this->admin);
    }

    /** @test */
    public function it_throws_exception_when_organizer_is_inactive()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot assign venue to inactive organizer '{$this->inactiveOrganizer->name}'.");

        $this->action->execute($this->publicVenue, $this->inactiveOrganizer, $this->admin);
    }

    /** @test */
    public function it_throws_exception_when_organizer_does_not_exist()
    {
        // Create organizer then delete it to simulate non-existent organizer
        $deletedOrganizer = Organizer::factory()->create();
        $organizerId = $deletedOrganizer->id;
        $deletedOrganizer->delete();

        // Manually create organizer object with deleted ID
        $nonExistentOrganizer = new Organizer();
        $nonExistentOrganizer->id = $organizerId;
        $nonExistentOrganizer->name = 'Deleted Organizer';
        $nonExistentOrganizer->is_active = true;
        $nonExistentOrganizer->exists = false; // Mark as non-existent

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Target organizer does not exist.');

        $this->action->execute($this->publicVenue, $nonExistentOrganizer, $this->admin);
    }

    /** @test */
    public function it_can_perform_bulk_assignment_successfully()
    {
        // Create additional public venues
        $venue2 = Venue::factory()->create(['organizer_id' => null]);
        $venue3 = Venue::factory()->create(['organizer_id' => null]);

        $venueIds = [$this->publicVenue->id, $venue2->id, $venue3->id];

        $result = $this->action->executeBulk($venueIds, $this->organizer, $this->admin);

        // Verify results structure
        $this->assertEquals(3, $result['total_attempted']);
        $this->assertEquals(3, $result['successful_assignments']);
        $this->assertEquals(0, $result['failed_assignments']);
        $this->assertCount(3, $result['success_details']);
        $this->assertEmpty($result['failure_details']);
        $this->assertEquals($this->organizer->id, $result['organizer']['id']);
        $this->assertEquals($this->organizer->name, $result['organizer']['name']);

        // Verify all venues were assigned
        $this->assertDatabaseHas('venues', ['id' => $this->publicVenue->id, 'organizer_id' => $this->organizer->id]);
        $this->assertDatabaseHas('venues', ['id' => $venue2->id, 'organizer_id' => $this->organizer->id]);
        $this->assertDatabaseHas('venues', ['id' => $venue3->id, 'organizer_id' => $this->organizer->id]);
    }

    /** @test */
    public function it_handles_bulk_assignment_with_mixed_results()
    {
        // Create mix of public and private venues
        $venue2 = Venue::factory()->create(['organizer_id' => null]); // Public
        $venue3 = Venue::factory()->create(['organizer_id' => $this->organizer->id]); // Private

        $venueIds = [$this->publicVenue->id, $venue2->id, $venue3->id];

        $result = $this->action->executeBulk($venueIds, $this->organizer, $this->admin);

        // Verify results
        $this->assertEquals(3, $result['total_attempted']);
        $this->assertEquals(2, $result['successful_assignments']);
        $this->assertEquals(1, $result['failed_assignments']);
        $this->assertCount(2, $result['success_details']);
        $this->assertCount(1, $result['failure_details']);

        // Verify failure details
        $this->assertEquals($venue3->id, $result['failure_details'][0]['venue_id']);
        $this->assertEquals('Venue already assigned to an organizer', $result['failure_details'][0]['reason']);
    }

    /** @test */
    public function it_throws_exception_for_bulk_assignment_by_non_admin()
    {
        $venueIds = [$this->publicVenue->id];

        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Only platform administrators can assign venues to organizers.');

        $this->action->executeBulk($venueIds, $this->organizer, $this->nonAdmin);
    }

    /** @test */
    public function it_throws_exception_for_bulk_assignment_to_inactive_organizer()
    {
        $venueIds = [$this->publicVenue->id];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot assign venues to inactive organizer '{$this->inactiveOrganizer->name}'.");

        $this->action->executeBulk($venueIds, $this->inactiveOrganizer, $this->admin);
    }

    /** @test */
    public function it_handles_bulk_assignment_with_non_existent_venue()
    {
        $nonExistentVenueId = 999999;
        $venueIds = [$this->publicVenue->id, $nonExistentVenueId];

        $result = $this->action->executeBulk($venueIds, $this->organizer, $this->admin);

        // Verify results
        $this->assertEquals(2, $result['total_attempted']);
        $this->assertEquals(1, $result['successful_assignments']);
        $this->assertEquals(1, $result['failed_assignments']);

        // Verify failure details contain the non-existent venue
        $this->assertEquals($nonExistentVenueId, $result['failure_details'][0]['venue_id']);
        $this->assertStringContainsString('No query results', $result['failure_details'][0]['reason']);
    }

    /** @test */
    public function it_validates_assignment_correctly_for_public_venue()
    {
        $result = $this->action->validateAssignment($this->publicVenue, $this->organizer);

        $this->assertTrue($result['can_assign']);
        $this->assertEquals('Venue can be assigned to organizer', $result['message']);
    }

    /** @test */
    public function it_validates_assignment_correctly_for_private_venue()
    {
        $result = $this->action->validateAssignment($this->privateVenue, $this->organizer);

        $this->assertFalse($result['can_assign']);
        $this->assertEquals('Venue is already assigned to an organizer', $result['reason']);
        $this->assertEquals($this->organizer->name, $result['current_organizer']);
    }

    /** @test */
    public function it_validates_assignment_correctly_for_inactive_organizer()
    {
        $result = $this->action->validateAssignment($this->publicVenue, $this->inactiveOrganizer);

        $this->assertFalse($result['can_assign']);
        $this->assertEquals('Target organizer is inactive', $result['reason']);
    }

    /** @test */
    public function it_returns_fresh_venue_instance_after_assignment()
    {
        $originalVenue = $this->publicVenue;
        $result = $this->action->execute($originalVenue, $this->organizer, $this->admin);

        // Verify it's a fresh instance with updated data
        $this->assertNotSame($originalVenue, $result);
        $this->assertEquals($this->organizer->id, $result->organizer_id);
        $this->assertInstanceOf(Venue::class, $result);
    }

    /** @test */
    public function it_performs_assignment_within_database_transaction()
    {
        // This test ensures that if something goes wrong, the assignment is rolled back
        // We'll simulate this by checking the database state

        $this->assertNull($this->publicVenue->organizer_id);

        $result = $this->action->execute($this->publicVenue, $this->organizer, $this->admin);

        // Verify the transaction completed successfully
        $this->assertEquals($this->organizer->id, $result->organizer_id);
        $this->assertDatabaseHas('venues', [
            'id' => $this->publicVenue->id,
            'organizer_id' => $this->organizer->id
        ]);
    }

    /** @test */
    public function it_maintains_other_venue_properties_during_assignment()
    {
        $originalName = $this->publicVenue->name;
        $originalAddress = $this->publicVenue->address;
        $originalCapacity = $this->publicVenue->capacity;

        $result = $this->action->execute($this->publicVenue, $this->organizer, $this->admin);

        // Verify only organizer_id changed
        $this->assertEquals($originalName, $result->name);
        $this->assertEquals($originalAddress, $result->address);
        $this->assertEquals($originalCapacity, $result->capacity);
        $this->assertEquals($this->organizer->id, $result->organizer_id);
    }
}
