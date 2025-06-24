<?php

namespace Tests\Unit\Services;

use App\Actions\Organizer\AcceptInvitationAction;
use App\Actions\Organizer\InviteUserToOrganizerAction;
use App\Actions\Organizer\RemoveUserFromOrganizerAction;
use App\Actions\Organizer\UpdateOrganizerUserRoleAction;
use App\Actions\Organizer\UpsertOrganizerAction;
use App\DataTransferObjects\Organizer\OrganizerData;
use App\Models\Organizer;
use App\Models\User;
use App\Services\OrganizerService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class OrganizerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizerService $organizerService;
    protected UpsertOrganizerAction $upsertAction;
    protected InviteUserToOrganizerAction $inviteUserAction;
    protected AcceptInvitationAction $acceptInvitationAction;
    protected RemoveUserFromOrganizerAction $removeUserAction;
    protected UpdateOrganizerUserRoleAction $updateUserRoleAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->upsertAction = app(UpsertOrganizerAction::class);
        $this->inviteUserAction = app(InviteUserToOrganizerAction::class);
        $this->acceptInvitationAction = app(AcceptInvitationAction::class);
        $this->removeUserAction = app(RemoveUserFromOrganizerAction::class);
        $this->updateUserRoleAction = app(UpdateOrganizerUserRoleAction::class);

        $this->organizerService = new OrganizerService(
            $this->upsertAction,
            $this->inviteUserAction,
            $this->acceptInvitationAction,
            $this->removeUserAction,
            $this->updateUserRoleAction
        );
    }

    public function test_can_create_organizer()
    {
        Storage::fake('public');

        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);
        $logo = UploadedFile::fake()->image('logo.jpg');

        $organizerData = OrganizerData::from([
            'name' => ['en' => 'Test Organizer', 'zh-TW' => '測試主辦方'],
            'slug' => 'test-organizer',
            'description' => ['en' => 'Test description', 'zh-TW' => '測試描述'],
            'contact_email' => 'test@example.com',
            'contact_phone' => '+1234567890',
            'is_active' => true,
            'created_by' => $user->id,
            'logo_upload' => $logo,
        ]);

        $organizer = $this->organizerService->createOrganizer($organizerData);

        $this->assertInstanceOf(Organizer::class, $organizer);
        $this->assertEquals('Test Organizer', $organizer->getTranslation('name', 'en'));
        $this->assertEquals('test-organizer', $organizer->slug);
        $this->assertEquals('test@example.com', $organizer->contact_email);
        $this->assertTrue($organizer->is_active);
        $this->assertEquals($user->id, $organizer->created_by);
        $organizer->refresh();
        $this->assertCount(1, $organizer->getMedia('logo'));
    }

    public function test_can_update_existing_organizer()
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create([
            'name' => ['en' => 'Original Name'],
            'slug' => 'original-slug',
            'created_by' => $user->id,
        ]);

        $updateData = OrganizerData::from([
            'id' => $organizer->id,
            'name' => ['en' => 'Updated Name', 'zh-TW' => '更新名稱'],
            'slug' => 'updated-slug',
            'description' => ['en' => 'Updated description'],
            'contact_email' => 'updated@example.com',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $updatedOrganizer = $this->organizerService->updateOrganizer($organizer->id, $updateData);

        $this->assertEquals($organizer->id, $updatedOrganizer->id);
        $this->assertEquals('Updated Name', $updatedOrganizer->getTranslation('name', 'en'));
        $this->assertEquals('updated-slug', $updatedOrganizer->slug);
        $this->assertEquals('updated@example.com', $updatedOrganizer->contact_email);
        // Verify creation metadata preserved
        $this->assertEquals($user->id, $updatedOrganizer->created_by);
        $this->assertEquals($organizer->created_at, $updatedOrganizer->created_at);
    }

    public function test_throws_exception_when_updating_nonexistent_organizer()
    {
        $user = User::factory()->create();

        $updateData = OrganizerData::from([
            'id' => 999,
            'name' => ['en' => 'Non-existent'],
            'slug' => 'non-existent',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Organizer with ID 999 not found for update operation.');

        $this->organizerService->updateOrganizer(999, $updateData);
    }

    public function test_can_find_organizer_by_id()
    {
        $organizer = Organizer::factory()->create();

        $foundOrganizer = $this->organizerService->findOrganizerById($organizer->id);

        $this->assertInstanceOf(Organizer::class, $foundOrganizer);
        $this->assertEquals($organizer->id, $foundOrganizer->id);
    }

    public function test_returns_null_when_organizer_not_found()
    {
        $result = $this->organizerService->findOrganizerById(999);

        $this->assertNull($result);
    }

    public function test_can_find_organizer_by_slug()
    {
        $organizer = Organizer::factory()->create(['slug' => 'test-organizer']);

        $foundOrganizer = $this->organizerService->getOrganizerBySlug('test-organizer');

        $this->assertInstanceOf(Organizer::class, $foundOrganizer);
        $this->assertEquals($organizer->id, $foundOrganizer->id);
        $this->assertEquals('test-organizer', $foundOrganizer->slug);
    }

    public function test_can_get_all_organizers()
    {
        $existingCount = Organizer::count();

        Organizer::factory()->count(3)->create(['is_active' => true]);
        Organizer::factory()->create(['is_active' => false]);

        $organizers = $this->organizerService->getAllOrganizers();

        $this->assertCount($existingCount + 4, $organizers);
    }

    public function test_can_filter_organizers_by_active_status()
    {
        $existingActiveCount = Organizer::where('is_active', true)->count();
        $existingInactiveCount = Organizer::where('is_active', false)->count();

        Organizer::factory()->count(3)->create(['is_active' => true]);
        Organizer::factory()->count(2)->create(['is_active' => false]);

        $activeOrganizers = $this->organizerService->getAllOrganizers(['is_active' => true]);
        $inactiveOrganizers = $this->organizerService->getAllOrganizers(['is_active' => false]);

        $this->assertCount($existingActiveCount + 3, $activeOrganizers);
        $this->assertCount($existingInactiveCount + 2, $inactiveOrganizers);

        foreach ($activeOrganizers as $organizer) {
            $this->assertTrue($organizer->is_active);
        }

        foreach ($inactiveOrganizers as $organizer) {
            $this->assertFalse($organizer->is_active);
        }
    }

    public function test_can_get_active_organizers_for_public_display()
    {
        $existingActiveCount = Organizer::where('is_active', true)->count();

        Organizer::factory()->count(3)->create(['is_active' => true]);
        Organizer::factory()->count(2)->create(['is_active' => false]);

        $publicOrganizers = $this->organizerService->getActiveOrganizers();

        $this->assertCount($existingActiveCount + 3, $publicOrganizers);
        foreach ($publicOrganizers as $organizer) {
            $this->assertTrue($organizer->is_active);
        }
    }

    public function test_can_delete_organizer()
    {
        $organizer = Organizer::factory()->create();

        $result = $this->organizerService->deleteOrganizer($organizer);

        $this->assertTrue($result);
        $this->assertSoftDeleted('organizers', ['id' => $organizer->id]);
    }

    public function test_can_get_organizers_for_user()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Create organizers for the user
        $organizer1 = Organizer::factory()->create();
        $organizer2 = Organizer::factory()->create();
        $otherOrganizer = Organizer::factory()->create();

        // Associate user with organizers using the service method
        $this->organizerService->addUserToOrganizer($organizer1, $user, 'manager');
        $this->organizerService->addUserToOrganizer($organizer2, $user, 'staff');
        $this->organizerService->addUserToOrganizer($otherOrganizer, $otherUser, 'owner');

        $userOrganizers = $this->organizerService->getOrganizersForUser($user);

        $this->assertCount(2, $userOrganizers);
        $this->assertTrue($userOrganizers->contains($organizer1));
        $this->assertTrue($userOrganizers->contains($organizer2));
        $this->assertFalse($userOrganizers->contains($otherOrganizer));
    }
}
