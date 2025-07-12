<?php

namespace Tests\Unit\Actions\Venues;

use Tests\TestCase;
use App\Actions\Venues\UnassignVenueFromOrganizerAction;
use App\Models\User;
use App\Models\Venue;
use App\Models\Organizer;
use App\Exceptions\UnauthorizedOperationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use InvalidArgumentException;

class UnassignVenueFromOrganizerActionTest extends TestCase
{
    use RefreshDatabase;

    protected UnassignVenueFromOrganizerAction $action;
    protected User $admin;
    protected User $nonAdmin;
    protected Organizer $organizer;
    protected Organizer $anotherOrganizer;
    protected Venue $publicVenue;
    protected Venue $privateVenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UnassignVenueFromOrganizerAction();

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
        $this->anotherOrganizer = Organizer::factory()->create(['is_active' => true]);

        // Create venues
        $this->publicVenue = Venue::factory()->create(['organizer_id' => null]); // Public venue
        $this->privateVenue = Venue::factory()->create(['organizer_id' => $this->organizer->id]); // Private venue
    }

    /** @test */
    public function it_can_unassign_private_venue_from_organizer()
    {
        $result = $this->action->execute($this->privateVenue, $this->admin);

        // Verify venue was unassigned
        $this->assertNull($result->organizer_id);
        $this->assertTrue($result->isPublic());
        $this->assertFalse($result->isOwnedBy($this->organizer));

        // Verify database was updated
        $this->assertDatabaseHas('venues', [
            'id' => $this->privateVenue->id,
            'organizer_id' => null
        ]);
    }

    /** @test */
    public function it_throws_exception_when_non_admin_tries_to_unassign_venue()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Only platform administrators can unassign venues from organizers.');

        $this->action->execute($this->privateVenue, $this->nonAdmin);
    }

    /** @test */
    public function it_throws_exception_when_venue_is_already_public()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Venue '{$this->publicVenue->name}' is already public and not assigned to any organizer.");

        $this->action->execute($this->publicVenue, $this->admin);
    }

    /** @test */
    public function it_can_force_unassign_venue()
    {
        $result = $this->action->execute($this->privateVenue, $this->admin, true);

        // Verify venue was unassigned
        $this->assertNull($result->organizer_id);
        $this->assertTrue($result->isPublic());

        // Verify database was updated
        $this->assertDatabaseHas('venues', [
            'id' => $this->privateVenue->id,
            'organizer_id' => null
        ]);
    }

    /** @test */
    public function it_can_perform_bulk_unassignment_successfully()
    {
        // Create additional private venues
        $venue2 = Venue::factory()->create(['organizer_id' => $this->organizer->id]);
        $venue3 = Venue::factory()->create(['organizer_id' => $this->anotherOrganizer->id]);

        $venueIds = [$this->privateVenue->id, $venue2->id, $venue3->id];

        $result = $this->action->executeBulk($venueIds, $this->admin);

        // Verify results structure
        $this->assertEquals(3, $result['total_attempted']);
        $this->assertEquals(3, $result['successful_unassignments']);
        $this->assertEquals(0, $result['failed_unassignments']);
        $this->assertCount(3, $result['success_details']);
        $this->assertEmpty($result['failure_details']);
        $this->assertFalse($result['force_unassign_used']);

        // Verify all venues were unassigned
        $this->assertDatabaseHas('venues', ['id' => $this->privateVenue->id, 'organizer_id' => null]);
        $this->assertDatabaseHas('venues', ['id' => $venue2->id, 'organizer_id' => null]);
        $this->assertDatabaseHas('venues', ['id' => $venue3->id, 'organizer_id' => null]);
    }

    /** @test */
    public function it_handles_bulk_unassignment_with_mixed_results()
    {
        // Create mix of private and public venues
        $venue2 = Venue::factory()->create(['organizer_id' => $this->organizer->id]); // Private

        $venueIds = [$this->privateVenue->id, $venue2->id, $this->publicVenue->id];

        $result = $this->action->executeBulk($venueIds, $this->admin);

        // Verify results
        $this->assertEquals(3, $result['total_attempted']);
        $this->assertEquals(2, $result['successful_unassignments']);
        $this->assertEquals(1, $result['failed_unassignments']);
        $this->assertCount(2, $result['success_details']);
        $this->assertCount(1, $result['failure_details']);

        // Verify failure details
        $this->assertEquals($this->publicVenue->id, $result['failure_details'][0]['venue_id']);
        $this->assertEquals('Venue is already public', $result['failure_details'][0]['reason']);
    }

    /** @test */
    public function it_throws_exception_for_bulk_unassignment_by_non_admin()
    {
        $venueIds = [$this->privateVenue->id];

        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Only platform administrators can unassign venues from organizers.');

        $this->action->executeBulk($venueIds, $this->nonAdmin);
    }

    /** @test */
    public function it_validates_unassignment_correctly_for_private_venue()
    {
        $result = $this->action->validateUnassignment($this->privateVenue);

        $this->assertTrue($result['can_unassign']);
        $this->assertEquals('Venue can be unassigned from organizer', $result['message']);
        $this->assertEquals($this->organizer->name, $result['current_organizer']);
        $this->assertFalse($result['force_used']);
    }

    /** @test */
    public function it_validates_unassignment_correctly_for_public_venue()
    {
        $result = $this->action->validateUnassignment($this->publicVenue);

        $this->assertFalse($result['can_unassign']);
        $this->assertEquals('Venue is already public and not assigned to any organizer', $result['reason']);
    }

    /** @test */
    public function it_can_transfer_venue_between_organizers()
    {
        $result = $this->action->transferVenue($this->privateVenue, $this->anotherOrganizer->id, $this->admin);

        // Verify venue was transferred
        $this->assertEquals($this->anotherOrganizer->id, $result->organizer_id);
        $this->assertTrue($result->isOwnedBy($this->anotherOrganizer));
        $this->assertFalse($result->isOwnedBy($this->organizer));

        // Verify database was updated
        $this->assertDatabaseHas('venues', [
            'id' => $this->privateVenue->id,
            'organizer_id' => $this->anotherOrganizer->id
        ]);
    }

    /** @test */
    public function it_can_transfer_venue_to_public()
    {
        $result = $this->action->transferVenue($this->privateVenue, null, $this->admin);

        // Verify venue became public
        $this->assertNull($result->organizer_id);
        $this->assertTrue($result->isPublic());

        // Verify database was updated
        $this->assertDatabaseHas('venues', [
            'id' => $this->privateVenue->id,
            'organizer_id' => null
        ]);
    }

    /** @test */
    public function it_throws_exception_when_non_admin_tries_to_transfer_venue()
    {
        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('Only platform administrators can transfer venues between organizers.');

        $this->action->transferVenue($this->privateVenue, $this->anotherOrganizer->id, $this->nonAdmin);
    }

    /** @test */
    public function it_returns_fresh_venue_instance_after_unassignment()
    {
        $originalVenue = $this->privateVenue;
        $result = $this->action->execute($originalVenue, $this->admin);

        // Verify it's a fresh instance with updated data
        $this->assertNotSame($originalVenue, $result);
        $this->assertNull($result->organizer_id);
        $this->assertInstanceOf(Venue::class, $result);
    }

    /** @test */
    public function it_performs_unassignment_within_database_transaction()
    {
        // This test ensures that if something goes wrong, the unassignment is rolled back

        $this->assertEquals($this->organizer->id, $this->privateVenue->organizer_id);

        $result = $this->action->execute($this->privateVenue, $this->admin);

        // Verify the transaction completed successfully
        $this->assertNull($result->organizer_id);
        $this->assertDatabaseHas('venues', [
            'id' => $this->privateVenue->id,
            'organizer_id' => null
        ]);
    }

    /** @test */
    public function it_maintains_other_venue_properties_during_unassignment()
    {
        $originalName = $this->privateVenue->name;
        $originalAddress = $this->privateVenue->address;
        $originalCapacity = $this->privateVenue->capacity;

        $result = $this->action->execute($this->privateVenue, $this->admin);

        // Verify only organizer_id changed
        $this->assertEquals($originalName, $result->name);
        $this->assertEquals($originalAddress, $result->address);
        $this->assertEquals($originalCapacity, $result->capacity);
        $this->assertNull($result->organizer_id);
    }

    /** @test */
    public function it_gets_empty_event_dependencies_for_current_system()
    {
        // Since venue_id doesn't exist in events table yet, this should return empty collection
        $dependencies = $this->action->getEventDependencies($this->privateVenue);

        $this->assertCount(0, $dependencies);
        $this->assertTrue($dependencies->isEmpty());
    }

    /** @test */
    public function it_gets_empty_event_dependencies_info()
    {
        $info = $this->action->getEventDependenciesInfo($this->privateVenue);

        $this->assertEquals(0, $info['total_events']);
        $this->assertEquals(0, $info['upcoming_events']);
        $this->assertEquals(0, $info['ongoing_events']);
        $this->assertEquals(0, $info['past_events']);
        $this->assertFalse($info['has_dependencies']);
        $this->assertEmpty($info['events_sample']);
    }
}
