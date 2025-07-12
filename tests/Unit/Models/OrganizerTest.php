<?php

namespace Tests\Unit\Models;

use App\Enums\OrganizerRoleEnum;
use App\Models\Country;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    /** @test */
    public function it_can_create_an_organizer_with_basic_information()
    {
        $user = User::factory()->create();
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);

        $organizer = Organizer::factory()->create([
            'name' => ['en' => 'Test Organizer', 'zh-TW' => '測試主辦方'],
            'slug' => 'test-organizer',
            'created_by' => $user->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
        ]);

        $this->assertDatabaseHas('organizers', [
            'id' => $organizer->id,
            'slug' => 'test-organizer',
            'created_by' => $user->id,
        ]);

        $organizer->refresh();
        $this->assertEquals('Test Organizer', $organizer->getTranslation('name', 'en'));
        $this->assertEquals('測試主辦方', $organizer->getTranslation('name', 'zh-TW'));
    }

    /** @test */
    public function it_belongs_to_a_creator()
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $organizer->creator);
        $this->assertEquals($user->id, $organizer->creator->id);
    }

    /** @test */
    public function it_belongs_to_country_and_state()
    {
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);
        $organizer = Organizer::factory()->create([
            'country_id' => $country->id,
            'state_id' => $state->id,
        ]);

        $this->assertInstanceOf(Country::class, $organizer->country);
        $this->assertInstanceOf(State::class, $organizer->state);
        $this->assertEquals($country->id, $organizer->country->id);
        $this->assertEquals($state->id, $organizer->state->id);
    }

    /** @test */
    public function it_can_have_many_users_with_different_roles()
    {
        $organizer = Organizer::factory()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $staff = User::factory()->create();

        // Create relationships with different roles
        OrganizerUser::factory()->forOrganizerAndUser($organizer, $owner)->owner()->create();
        OrganizerUser::factory()->forOrganizerAndUser($organizer, $manager)->manager()->create();
        OrganizerUser::factory()->forOrganizerAndUser($organizer, $staff)->staff()->create();

        $organizer->refresh();
        $organizer->load(['users', 'owners', 'managers', 'staff']);

        $this->assertCount(3, $organizer->users);
        $this->assertCount(1, $organizer->owners);
        $this->assertCount(1, $organizer->managers);
        $this->assertCount(1, $organizer->staff);
    }

    /** @test */
    public function it_can_check_if_user_is_member()
    {
        $organizer = Organizer::factory()->create();
        $member = User::factory()->create();
        $nonMember = User::factory()->create();

        OrganizerUser::factory()->forOrganizerAndUser($organizer, $member)->staff()->create();

        $this->assertTrue($organizer->hasMember($member));
        $this->assertFalse($organizer->hasMember($nonMember));
    }

    /** @test */
    public function it_can_get_user_role_in_organizer()
    {
        $organizer = Organizer::factory()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $nonMember = User::factory()->create();

        OrganizerUser::factory()->forOrganizerAndUser($organizer, $owner)->owner()->create();
        OrganizerUser::factory()->forOrganizerAndUser($organizer, $manager)->manager()->create();

        $this->assertEquals(OrganizerRoleEnum::OWNER, $organizer->getUserRole($owner));
        $this->assertEquals(OrganizerRoleEnum::MANAGER, $organizer->getUserRole($manager));
        $this->assertNull($organizer->getUserRole($nonMember));
    }

    /** @test */
    public function it_can_check_user_permissions()
    {
        $organizer = Organizer::factory()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $staff = User::factory()->create();
        $viewer = User::factory()->create();

        OrganizerUser::factory()->forOrganizerAndUser($organizer, $owner)->owner()->create();
        OrganizerUser::factory()->forOrganizerAndUser($organizer, $manager)->manager()->create();
        OrganizerUser::factory()->forOrganizerAndUser($organizer, $staff)->staff()->create();
        OrganizerUser::factory()->forOrganizerAndUser($organizer, $viewer)->viewer()->create();

        // Owner can manage everything
        $this->assertTrue($organizer->userCanManage($owner));
        $this->assertTrue($organizer->userCanManageUsers($owner));
        $this->assertTrue($organizer->userCanManageEvents($owner));

        // Manager can manage users and events but not organizer settings
        $this->assertFalse($organizer->userCanManage($manager));
        $this->assertTrue($organizer->userCanManageUsers($manager));
        $this->assertTrue($organizer->userCanManageEvents($manager));

        // Staff can only manage events
        $this->assertFalse($organizer->userCanManage($staff));
        $this->assertFalse($organizer->userCanManageUsers($staff));
        $this->assertTrue($organizer->userCanManageEvents($staff));

        // Viewer can't manage anything
        $this->assertFalse($organizer->userCanManage($viewer));
        $this->assertFalse($organizer->userCanManageUsers($viewer));
        $this->assertFalse($organizer->userCanManageEvents($viewer));
    }

    /** @test */
    public function it_can_scope_active_organizers()
    {
        // Get initial count of active organizers (may include migration-created organizers)
        $initialActiveCount = Organizer::active()->count();

        $activeOrganizer = Organizer::factory()->create(['is_active' => true]);
        $inactiveOrganizer = Organizer::factory()->create(['is_active' => false]);

        $activeOrganizers = Organizer::active()->get();

        $this->assertCount($initialActiveCount + 1, $activeOrganizers);
        $this->assertTrue($activeOrganizers->contains($activeOrganizer));
        $this->assertFalse($activeOrganizers->contains($inactiveOrganizer));
    }

    /** @test */
    public function it_can_search_organizers_by_name()
    {
        $organizer1 = Organizer::factory()->create([
            'name' => ['en' => 'Tech Events Corp', 'zh-TW' => '科技活動公司']
        ]);
        $organizer2 = Organizer::factory()->create([
            'name' => ['en' => 'Music Festival Group', 'zh-TW' => '音樂節團體']
        ]);

        $techResults = Organizer::searchByName('Tech')->get();
        $musicResults = Organizer::searchByName('Music')->get();
        $chineseResults = Organizer::searchByName('科技')->get();

        $this->assertCount(1, $techResults);
        $this->assertTrue($techResults->contains($organizer1));

        $this->assertCount(1, $musicResults);
        $this->assertTrue($musicResults->contains($organizer2));

        $this->assertCount(1, $chineseResults);
        $this->assertTrue($chineseResults->contains($organizer1));
    }

    /** @test */
    public function it_can_get_full_address()
    {
        $country = Country::factory()->create(['name' => 'Taiwan']);
        $state = State::factory()->create(['name' => 'Taipei', 'country_id' => $country->id]);

        $organizer = Organizer::factory()->create([
            'address_line_1' => '123 Main St',
            'address_line_2' => 'Suite 456',
            'city' => 'Taipei',
            'postal_code' => '10001',
            'country_id' => $country->id,
            'state_id' => $state->id,
        ]);

        $expectedAddress = '123 Main St, Suite 456, Taipei, Taipei, 10001, Taiwan';
        $this->assertEquals($expectedAddress, $organizer->full_address);
    }

    /** @test */
    public function it_handles_inactive_user_memberships()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();

        // Create inactive membership
        OrganizerUser::factory()->forOrganizerAndUser($organizer, $user)->owner()->inactive()->create();

        $this->assertFalse($organizer->hasMember($user));
        $this->assertNull($organizer->getUserRole($user));
        $this->assertFalse($organizer->userCanManage($user));
    }

    /** @test */
    public function it_can_count_users_by_role()
    {
        $organizer = Organizer::factory()->create();
        $owner = User::factory()->create();
        $manager1 = User::factory()->create();
        $manager2 = User::factory()->create();
        $staff = User::factory()->create();

        OrganizerUser::factory()->forOrganizerAndUser($organizer, $owner)->owner()->create();
        OrganizerUser::factory()->forOrganizerAndUser($organizer, $manager1)->manager()->create();
        OrganizerUser::factory()->forOrganizerAndUser($organizer, $manager2)->manager()->create();
        OrganizerUser::factory()->forOrganizerAndUser($organizer, $staff)->staff()->create();

        $ownersCount = $organizer->owners()->count();
        $managersCount = $organizer->managers()->count();
        $staffCount = $organizer->staff()->count();

        $this->assertEquals(1, $ownersCount);
        $this->assertEquals(2, $managersCount);
        $this->assertEquals(1, $staffCount);
    }
}
