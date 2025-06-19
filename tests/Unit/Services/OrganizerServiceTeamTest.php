<?php

namespace Tests\Unit\Services;

use App\Actions\Organizer\AcceptInvitationAction;
use App\Actions\Organizer\InviteUserToOrganizerAction;
use App\Actions\Organizer\RemoveUserFromOrganizerAction;
use App\Actions\Organizer\UpdateOrganizerUserRoleAction;
use App\Actions\Organizer\UpsertOrganizerAction;
use App\DataTransferObjects\Organizer\InviteUserData;
use App\Models\Organizer;
use App\Models\User;
use App\Models\Venue;
use App\Services\OrganizerService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class OrganizerServiceTeamTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizerService $organizerService;
    protected $mockUpsertAction;
    protected $mockInviteAction;
    protected $mockAcceptAction;
    protected $mockRemoveAction;
    protected $mockUpdateRoleAction;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock actions
        $this->mockUpsertAction = Mockery::mock(UpsertOrganizerAction::class);
        $this->mockInviteAction = Mockery::mock(InviteUserToOrganizerAction::class);
        $this->mockAcceptAction = Mockery::mock(AcceptInvitationAction::class);
        $this->mockRemoveAction = Mockery::mock(RemoveUserFromOrganizerAction::class);
        $this->mockUpdateRoleAction = Mockery::mock(UpdateOrganizerUserRoleAction::class);

        // Create service with mocked dependencies
        $this->organizerService = new OrganizerService(
            $this->mockUpsertAction,
            $this->mockInviteAction,
            $this->mockAcceptAction,
            $this->mockRemoveAction,
            $this->mockUpdateRoleAction
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_invite_user_to_organizer()
    {
        $inviteUserData = InviteUserData::from([
            'email' => 'test@example.com',
            'organizer_id' => 1,
            'role_in_organizer' => 'staff',
            'invited_by' => 1
        ]);

        $this->mockInviteAction
            ->shouldReceive('execute')
            ->once()
            ->with($inviteUserData)
            ->andReturn(true);

        $result = $this->organizerService->inviteUser($inviteUserData);

        $this->assertTrue($result);
    }

    public function test_can_accept_invitation()
    {
        $organizerId = 1;
        $userId = 2;

        $this->mockAcceptAction
            ->shouldReceive('execute')
            ->once()
            ->with($organizerId, $userId)
            ->andReturn(true);

        $result = $this->organizerService->acceptInvitation($organizerId, $userId);

        $this->assertTrue($result);
    }

    public function test_can_remove_user_from_organizer()
    {
        $organizerId = 1;
        $userToRemoveId = 2;
        $removedBy = 3;

        $this->mockRemoveAction
            ->shouldReceive('execute')
            ->once()
            ->with($organizerId, $userToRemoveId, $removedBy)
            ->andReturn(true);

        $result = $this->organizerService->removeUser($organizerId, $userToRemoveId, $removedBy);

        $this->assertTrue($result);
    }

    public function test_can_remove_user_with_reason()
    {
        $organizerId = 1;
        $userToRemoveId = 2;
        $removedBy = 3;
        $reason = 'Violation of terms';

        $this->mockRemoveAction
            ->shouldReceive('executeWithReason')
            ->once()
            ->with($organizerId, $userToRemoveId, $removedBy, $reason)
            ->andReturn(true);

        $result = $this->organizerService->removeUserWithReason($organizerId, $userToRemoveId, $removedBy, $reason);

        $this->assertTrue($result);
    }

    public function test_can_update_user_role()
    {
        $organizerId = 1;
        $userId = 2;
        $newRole = 'manager';
        $updatedBy = 3;

        $this->mockUpdateRoleAction
            ->shouldReceive('execute')
            ->once()
            ->with($organizerId, $userId, $newRole, $updatedBy)
            ->andReturn(true);

        $result = $this->organizerService->updateUserRole($organizerId, $userId, $newRole, $updatedBy);

        $this->assertTrue($result);
    }

    public function test_can_update_user_role_with_permissions()
    {
        $organizerId = 1;
        $userId = 2;
        $newRole = 'staff';
        $customPermissions = ['manage_events', 'view_analytics'];
        $updatedBy = 3;

        $this->mockUpdateRoleAction
            ->shouldReceive('executeWithPermissions')
            ->once()
            ->with($organizerId, $userId, $newRole, $customPermissions, $updatedBy)
            ->andReturn(true);

        $result = $this->organizerService->updateUserRoleWithPermissions(
            $organizerId,
            $userId,
            $newRole,
            $customPermissions,
            $updatedBy
        );

        $this->assertTrue($result);
    }

    public function test_get_organizer_users_returns_active_users_by_default()
    {
        $organizer = Organizer::factory()->create();
        $activeUser = User::factory()->create();
        $inactiveUser = User::factory()->create();

        // Attach users with different statuses
        $organizer->users()->attach($activeUser->id, [
            'role_in_organizer' => 'staff',
            'is_active' => true,
            'joined_at' => now()
        ]);

        $organizer->users()->attach($inactiveUser->id, [
            'role_in_organizer' => 'staff',
            'is_active' => false,
            'joined_at' => now()
        ]);

        $result = $this->organizerService->getOrganizerUsers($organizer->id);

        $this->assertCount(1, $result);
        $this->assertEquals($activeUser->id, $result->first()->id);
    }

    public function test_get_organizer_users_can_include_inactive_users()
    {
        $organizer = Organizer::factory()->create();
        $activeUser = User::factory()->create();
        $inactiveUser = User::factory()->create();

        // Attach users with different statuses
        $organizer->users()->attach($activeUser->id, [
            'role_in_organizer' => 'staff',
            'is_active' => true,
            'joined_at' => now()
        ]);

        $organizer->users()->attach($inactiveUser->id, [
            'role_in_organizer' => 'staff',
            'is_active' => false,
            'joined_at' => now()
        ]);

        $result = $this->organizerService->getOrganizerUsers($organizer->id, false);

        $this->assertCount(2, $result);
    }

    // ===========================
    // VENUE MANAGEMENT TESTS (ORG-009.3)
    // Note: These tests are simplified to avoid foreign key constraints
    // until venue migration is updated to properly reference organizers table
    // ===========================

    public function test_can_assign_venue_to_organizer()
    {
        $this->markTestSkipped('Venue assignment tests skipped until ORG-004.1 migration is complete (venue organizer_id should reference organizers table)');
    }

    public function test_cannot_assign_nonexistent_venue()
    {
        $organizer = Organizer::factory()->create();

        $result = $this->organizerService->assignVenue($organizer->id, 999);

        $this->assertFalse($result);
    }

    public function test_cannot_assign_venue_to_nonexistent_organizer()
    {
        $result = $this->organizerService->assignVenue(999, 1);

        $this->assertFalse($result);
    }

    public function test_can_unassign_venue_from_organizer()
    {
        $this->markTestSkipped('Venue assignment tests skipped until ORG-004.1 migration is complete (venue organizer_id should reference organizers table)');
    }

    public function test_cannot_unassign_nonexistent_venue()
    {
        $result = $this->organizerService->unassignVenue(999);

        $this->assertFalse($result);
    }

    public function test_get_available_venues_returns_only_public_venues()
    {
        $this->markTestSkipped('Venue query tests skipped until ORG-004.1 migration is complete (venue organizer_id should reference organizers table)');
    }

    public function test_get_organizer_venues_returns_only_organizer_venues()
    {
        $this->markTestSkipped('Venue query tests skipped until ORG-004.1 migration is complete (venue organizer_id should reference organizers table)');
    }

    public function test_get_accessible_venues_returns_organizer_and_public_venues()
    {
        $this->markTestSkipped('Venue query tests skipped until ORG-004.1 migration is complete (venue organizer_id should reference organizers table)');
    }

    // ===========================
    // QUERY AND UTILITY TESTS (ORG-009.4)
    // ===========================

    public function test_get_user_organizers_returns_active_organizers()
    {
        $user = User::factory()->create();
        $activeOrganizer = Organizer::factory()->create(['is_active' => true]);
        $inactiveOrganizer = Organizer::factory()->create(['is_active' => false]);

        // Attach user to both organizers
        $user->organizers()->attach($activeOrganizer->id, [
            'role_in_organizer' => 'staff',
            'is_active' => true,
            'joined_at' => now()
        ]);

        $user->organizers()->attach($inactiveOrganizer->id, [
            'role_in_organizer' => 'staff',
            'is_active' => true,
            'joined_at' => now()
        ]);

        $result = $this->organizerService->getUserOrganizers($user->id);

        $this->assertCount(1, $result);
        $this->assertEquals($activeOrganizer->id, $result->first()->id);
    }

    public function test_get_user_organizers_with_inactive_organizers()
    {
        $user = User::factory()->create();
        $activeOrganizer = Organizer::factory()->create(['is_active' => true]);
        $inactiveOrganizer = Organizer::factory()->create(['is_active' => false]);

        // Attach user to both organizers
        $user->organizers()->attach($activeOrganizer->id, [
            'role_in_organizer' => 'staff',
            'is_active' => true,
            'joined_at' => now()
        ]);

        $user->organizers()->attach($inactiveOrganizer->id, [
            'role_in_organizer' => 'staff',
            'is_active' => true,
            'joined_at' => now()
        ]);

        $result = $this->organizerService->getUserOrganizers($user->id, false);

        $this->assertCount(2, $result);
    }

    public function test_get_user_organizers_returns_empty_for_nonexistent_user()
    {
        $result = $this->organizerService->getUserOrganizers(999);

        $this->assertCount(0, $result);
    }

    public function test_search_organizers_finds_by_name()
    {
        $organizer = Organizer::factory()->create([
            'name' => ['en' => 'Test Event Company', 'zh-TW' => '測試活動公司'],
            'is_active' => true
        ]);

        $result = $this->organizerService->searchOrganizers('Test Event');

        $this->assertCount(1, $result);
        $this->assertEquals($organizer->id, $result->first()->id);
    }

    public function test_search_organizers_finds_by_description()
    {
        $organizer = Organizer::factory()->create([
            'name' => ['en' => 'Company Name', 'zh-TW' => '公司名稱'],
            'description' => ['en' => 'We organize amazing events', 'zh-TW' => '我們舉辦精彩活動'],
            'is_active' => true
        ]);

        $result = $this->organizerService->searchOrganizers('amazing events');

        $this->assertCount(1, $result);
        $this->assertEquals($organizer->id, $result->first()->id);
    }

    public function test_search_organizers_finds_by_email()
    {
        $organizer = Organizer::factory()->create([
            'contact_email' => 'contact@example.com',
            'is_active' => true
        ]);

        $result = $this->organizerService->searchOrganizers('contact@example');

        $this->assertCount(1, $result);
        $this->assertEquals($organizer->id, $result->first()->id);
    }

    public function test_search_organizers_applies_filters()
    {
        $activeOrganizer = Organizer::factory()->create([
            'name' => ['en' => 'Test Company', 'zh-TW' => '測試公司'],
            'is_active' => true
        ]);

        $inactiveOrganizer = Organizer::factory()->create([
            'name' => ['en' => 'Test Inactive Company', 'zh-TW' => '測試非活躍公司'],
            'is_active' => false
        ]);

        $result = $this->organizerService->searchOrganizers('Test', ['is_active' => true]);

        $this->assertCount(1, $result);
        $this->assertEquals($activeOrganizer->id, $result->first()->id);
    }

    public function test_get_organizer_stats_returns_comprehensive_data()
    {
        $organizer = Organizer::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Add users with different statuses
        $organizer->users()->attach($user1->id, [
            'role_in_organizer' => 'owner',
            'is_active' => true,
            'invitation_accepted_at' => now(),
            'joined_at' => now()
        ]);

        $organizer->users()->attach($user2->id, [
            'role_in_organizer' => 'manager',
            'is_active' => true,
            'invitation_accepted_at' => now(),
            'joined_at' => now()
        ]);

        $organizer->users()->attach($user3->id, [
            'role_in_organizer' => 'staff',
            'is_active' => true,
            'invitation_accepted_at' => null, // Pending invitation
            'joined_at' => now()
        ]);

        // Note: Venue stats are calculated but venues not created due to migration constraints

        $result = $this->organizerService->getOrganizerStats($organizer->id);

        $this->assertArrayHasKey('organizer_id', $result);
        $this->assertArrayHasKey('organizer_name', $result);
        $this->assertArrayHasKey('total_users', $result);
        $this->assertArrayHasKey('active_users', $result);
        $this->assertArrayHasKey('pending_invitations', $result);
        $this->assertArrayHasKey('owned_venues', $result);
        $this->assertArrayHasKey('role_distribution', $result);

        $this->assertEquals($organizer->id, $result['organizer_id']);
        $this->assertEquals(3, $result['total_users']);
        $this->assertEquals(3, $result['active_users']);
        $this->assertEquals(1, $result['pending_invitations']);
        $this->assertEquals(0, $result['owned_venues']); // No venues created due to constraints
        $this->assertEquals(0, $result['active_owned_venues']); // No venues created due to constraints

        $this->assertArrayHasKey('owner', $result['role_distribution']);
        $this->assertArrayHasKey('manager', $result['role_distribution']);
        $this->assertArrayHasKey('staff', $result['role_distribution']);
        $this->assertEquals(1, $result['role_distribution']['owner']);
        $this->assertEquals(1, $result['role_distribution']['manager']);
        $this->assertEquals(1, $result['role_distribution']['staff']);
    }

    public function test_get_organizer_stats_returns_empty_for_nonexistent_organizer()
    {
        $result = $this->organizerService->getOrganizerStats(999);

        $this->assertEmpty($result);
    }

    public function test_get_multiple_organizer_stats()
    {
        $organizer1 = Organizer::factory()->create();
        $organizer2 = Organizer::factory()->create();

        $result = $this->organizerService->getMultipleOrganizerStats([$organizer1->id, $organizer2->id, 999]);

        $this->assertCount(2, $result); // Should filter out nonexistent organizer
        $this->assertEquals($organizer1->id, $result[0]['organizer_id']);
        $this->assertEquals($organizer2->id, $result[1]['organizer_id']);
    }

    public function test_get_organizer_metrics()
    {
        $organizer = Organizer::factory()->create();

        $result = $this->organizerService->getOrganizerMetrics($organizer->id);

        $this->assertArrayHasKey('organizer_id', $result);
        $this->assertArrayHasKey('team_size_history', $result);
        $this->assertArrayHasKey('recent_invitations', $result);
        $this->assertArrayHasKey('recent_role_changes', $result);
        $this->assertEquals($organizer->id, $result['organizer_id']);
    }
}
