<?php

namespace Tests\Unit\Actions\Organizer;

use Tests\TestCase;
use App\Actions\Organizer\UpsertOrganizerAction;
use App\DataTransferObjects\Organizer\OrganizerData;
use App\Models\Organizer;
use App\Models\User;
use App\Models\Country;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpsertOrganizerActionTest extends TestCase
{
    use RefreshDatabase;

    private UpsertOrganizerAction $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpsertOrganizerAction();
        $this->user = User::factory()->create();

        // Create required reference data
        Country::factory()->create(['id' => 1, 'name' => 'Test Country']);
        State::factory()->create(['id' => 1, 'name' => 'Test State', 'country_id' => 1]);

        Storage::fake('public');
    }

    /** @test */
    public function it_can_create_a_new_organizer()
    {
        $organizerData = new OrganizerData(
            name: ['en' => 'Test Organizer', 'zh-TW' => '測試主辦方'],
            slug: 'test-organizer',
            description: ['en' => 'Test Description'],
            contact_email: 'test@example.com',
            contact_phone: '+1-234-567-8900',
            website_url: 'https://example.com',
            social_media_links: [
                'facebook' => 'https://facebook.com/test',
                'twitter' => 'https://twitter.com/test'
            ],
            address_line_1: '123 Main St',
            address_line_2: 'Suite 100',
            city: 'Test City',
            state: 'Test State',
            postal_code: '12345',
            country_id: 1,
            state_id: 1,
            is_active: true,
            contract_details: [
                'terms' => 'Test terms',
                'rate_structure' => '10% commission'
            ],
            created_by: $this->user->id,
            logo_upload: null,
            id: null,
        );

        $organizer = $this->action->execute($organizerData);

        $this->assertInstanceOf(Organizer::class, $organizer);
        $this->assertEquals('Test Organizer', $organizer->getTranslation('name', 'en'));
        $this->assertEquals('測試主辦方', $organizer->getTranslation('name', 'zh-TW'));
        $this->assertEquals('test-organizer', $organizer->slug);
        $this->assertEquals('test@example.com', $organizer->contact_email);
        $this->assertEquals('+1-234-567-8900', $organizer->contact_phone);
        $this->assertEquals('https://example.com', $organizer->website_url);
        $this->assertTrue($organizer->is_active);
        $this->assertEquals($this->user->id, $organizer->created_by);
        $this->assertNotNull($organizer->created_at);
        $this->assertNotNull($organizer->updated_at);

        $this->assertDatabaseHas('organizers', [
            'slug' => 'test-organizer',
            'contact_email' => 'test@example.com',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_update_an_existing_organizer()
    {
        $organizer = Organizer::factory()->create([
            'name' => ['en' => 'Original Name'],
            'slug' => 'original-slug',
            'contact_email' => 'original@example.com',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $organizerData = new OrganizerData(
            name: ['en' => 'Updated Organizer', 'zh-TW' => '更新的主辦方'],
            slug: 'updated-organizer',
            description: ['en' => 'Updated Description'],
            contact_email: 'updated@example.com',
            contact_phone: '+1-987-654-3210',
            website_url: 'https://updated.com',
            social_media_links: ['instagram' => 'https://instagram.com/updated'],
            address_line_1: '456 Updated St',
            address_line_2: null,
            city: 'Updated City',
            state: 'Updated State',
            postal_code: '67890',
            country_id: 1,
            state_id: 1,
            is_active: false, // Changed to inactive
            contract_details: ['terms' => 'Updated terms'],
            created_by: $this->user->id,
            logo_upload: null,
            id: $organizer->id,
        );

        $updatedOrganizer = $this->action->execute($organizerData);

        $this->assertEquals($organizer->id, $updatedOrganizer->id);
        $this->assertEquals('Updated Organizer', $updatedOrganizer->getTranslation('name', 'en'));
        $this->assertEquals('更新的主辦方', $updatedOrganizer->getTranslation('name', 'zh-TW'));
        $this->assertEquals('updated-organizer', $updatedOrganizer->slug);
        $this->assertEquals('updated@example.com', $updatedOrganizer->contact_email);
        $this->assertFalse($updatedOrganizer->is_active);

        // Should preserve original creation data
        $this->assertEquals($organizer->created_by, $updatedOrganizer->created_by);
        $this->assertEquals($organizer->created_at->format('Y-m-d H:i:s'), $updatedOrganizer->created_at->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('organizers', [
            'id' => $organizer->id,
            'slug' => 'updated-organizer',
            'contact_email' => 'updated@example.com',
            'is_active' => false,
        ]);
    }

    /** @test */
    public function it_generates_slug_from_name_when_slug_is_empty()
    {
        $organizerData = new OrganizerData(
            name: ['en' => 'Auto Generated Slug'],
            slug: '', // Empty slug should trigger auto-generation
            description: null,
            contact_email: null,
            contact_phone: null,
            website_url: null,
            social_media_links: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: $this->user->id,
            logo_upload: null,
            id: null,
        );

        $organizer = $this->action->execute($organizerData);

        $this->assertEquals('auto-generated-slug', $organizer->slug);
    }

    /** @test */
    public function it_ensures_slug_uniqueness_when_auto_generating()
    {
        // Create an organizer with the slug we want to test conflict with
        Organizer::factory()->create(['slug' => 'duplicate-name']);
        Organizer::factory()->create(['slug' => 'duplicate-name-1']);

        $organizerData = new OrganizerData(
            name: ['en' => 'Duplicate Name'],
            slug: '', // Empty slug should trigger auto-generation
            description: null,
            contact_email: null,
            contact_phone: null,
            website_url: null,
            social_media_links: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: $this->user->id,
            logo_upload: null,
            id: null,
        );

        $organizer = $this->action->execute($organizerData);

        $this->assertEquals('duplicate-name-2', $organizer->slug);
    }

    /** @test */
    public function it_can_handle_logo_upload_for_new_organizer()
    {
        $logoFile = UploadedFile::fake()->image('logo.jpg', 300, 300);

        $organizerData = new OrganizerData(
            name: ['en' => 'Organizer with Logo'],
            slug: 'organizer-with-logo',
            description: null,
            contact_email: null,
            contact_phone: null,
            website_url: null,
            social_media_links: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: $this->user->id,
            logo_upload: $logoFile,
            id: null,
        );

        $organizer = $this->action->execute($organizerData);

        $this->assertNotNull($organizer->getFirstMedia('logo'));
        $this->assertEquals('logo', $organizer->getFirstMedia('logo')->name);
    }

    /** @test */
    public function it_can_replace_logo_when_updating_organizer()
    {
        $organizer = Organizer::factory()->create([
            'name' => ['en' => 'Organizer for Logo Update'],
            'slug' => 'organizer-logo-update',
            'created_by' => $this->user->id,
        ]);

        // Add initial logo
        $initialLogo = UploadedFile::fake()->image('initial-logo.jpg', 200, 200);
        $organizer->addMedia($initialLogo)->toMediaCollection('logo');

        $this->assertCount(1, $organizer->getMedia('logo'));

        // Update with new logo
        $newLogo = UploadedFile::fake()->image('new-logo.png', 400, 400);

        $organizerData = new OrganizerData(
            name: ['en' => 'Organizer for Logo Update'],
            slug: 'organizer-logo-update',
            description: null,
            contact_email: null,
            contact_phone: null,
            website_url: null,
            social_media_links: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: $this->user->id,
            logo_upload: $newLogo,
            id: $organizer->id,
        );

        $updatedOrganizer = $this->action->execute($organizerData);

        // Should still have only one logo (replaced, not added)
        $updatedOrganizer->refresh();
        $this->assertCount(1, $updatedOrganizer->getMedia('logo'));
        $this->assertEquals('new-logo', $updatedOrganizer->getFirstMedia('logo')->name);
    }

    /** @test */
    public function it_uses_create_method_for_explicit_creation()
    {
        $organizerData = new OrganizerData(
            name: ['en' => 'Explicit Create'],
            slug: 'explicit-create',
            description: null,
            contact_email: null,
            contact_phone: null,
            website_url: null,
            social_media_links: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: $this->user->id,
            logo_upload: null,
            id: 999, // Even with ID, create() should force creation
        );

        $organizer = $this->action->create($organizerData);

        $this->assertInstanceOf(Organizer::class, $organizer);
        $this->assertEquals('Explicit Create', $organizer->getTranslation('name', 'en'));
        $this->assertEquals('explicit-create', $organizer->slug);

        // Should be a new record, not ID 999
        $this->assertNotEquals(999, $organizer->id);
    }

    /** @test */
    public function it_uses_update_method_for_explicit_updates()
    {
        $organizer = Organizer::factory()->create([
            'name' => ['en' => 'Original Update Test'],
            'slug' => 'original-update-test',
            'created_by' => $this->user->id,
        ]);

        $organizerData = new OrganizerData(
            name: ['en' => 'Explicit Update'],
            slug: 'explicit-update',
            description: null,
            contact_email: null,
            contact_phone: null,
            website_url: null,
            social_media_links: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: $this->user->id,
            logo_upload: null,
            id: null, // Even without ID, update() should force update with provided ID
        );

        $updatedOrganizer = $this->action->update($organizer->id, $organizerData);

        $this->assertEquals($organizer->id, $updatedOrganizer->id);
        $this->assertEquals('Explicit Update', $updatedOrganizer->getTranslation('name', 'en'));
        $this->assertEquals('explicit-update', $updatedOrganizer->slug);
    }

    /** @test */
    public function it_handles_translatable_fields_correctly()
    {
        $organizerData = new OrganizerData(
            name: [
                'en' => 'Multilingual Organizer',
                'zh-TW' => '多語言主辦方',
                'zh-CN' => '多语言主办方'
            ],
            slug: 'multilingual-organizer',
            description: [
                'en' => 'English description',
                'zh-TW' => '繁體中文描述',
                'zh-CN' => '简体中文描述'
            ],
            contact_email: null,
            contact_phone: null,
            website_url: null,
            social_media_links: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: $this->user->id,
            logo_upload: null,
            id: null,
        );

        $organizer = $this->action->execute($organizerData);

        $this->assertEquals('Multilingual Organizer', $organizer->getTranslation('name', 'en'));
        $this->assertEquals('多語言主辦方', $organizer->getTranslation('name', 'zh-TW'));
        $this->assertEquals('多语言主办方', $organizer->getTranslation('name', 'zh-CN'));

        $this->assertEquals('English description', $organizer->getTranslation('description', 'en'));
        $this->assertEquals('繁體中文描述', $organizer->getTranslation('description', 'zh-TW'));
        $this->assertEquals('简体中文描述', $organizer->getTranslation('description', 'zh-CN'));
    }
}
