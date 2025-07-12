<?php

namespace Tests\Feature\Admin;

use App\Models\Country;
use App\Models\Organizer;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Country $country;
    protected State $state;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // Create admin user with necessary permissions
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole(\App\Enums\RoleNameEnum::ADMIN->value);

        // Create country and state for testing
        $this->country = Country::factory()->create();
        $this->state = State::factory()->create(['country_id' => $this->country->id]);
    }

    public function test_index_displays_organizers()
    {
        // Create some organizers
        $organizers = Organizer::factory()->count(3)->create();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.organizers.index'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/Organizers/Index')
                ->has('organizers.data', 4) // 3 created + 1 default organizer from migration
                ->has('pageTitle')
                ->has('breadcrumbs')
        );
    }

    public function test_create_displays_form()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.organizers.create'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/Organizers/Create')
                ->has('countries')
                ->has('states')
                ->has('pageTitle')
                ->has('breadcrumbs')
        );
    }

    public function test_store_creates_organizer()
    {
        Storage::fake('public');

        $organizerData = [
            'name' => [
                'en' => 'Test Organizer EN',
                'zh-TW' => 'Test Organizer TW',
            ],
            'slug' => 'test-organizer',
            'description' => [
                'en' => 'Test description EN',
                'zh-TW' => 'Test description TW',
            ],
            'contact_email' => 'test@example.com',
            'contact_phone' => '+1234567890',
            'website_url' => 'https://example.com',
            'address_line_1' => '123 Test St',
            'city' => 'Test City',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_active' => true,
            'logo_upload' => UploadedFile::fake()->image('logo.jpg'),
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.organizers.store'), $organizerData);

        $response->assertRedirect(route('admin.organizers.index'));
        $response->assertSessionHas('success', 'Organizer created successfully.');

        $this->assertDatabaseHas('organizers', [
            'slug' => 'test-organizer',
            'contact_email' => 'test@example.com',
            'created_by' => $this->adminUser->id,
        ]);

        // Check if organizer was created and has logo
        $organizer = Organizer::where('slug', 'test-organizer')->first();
        $this->assertNotNull($organizer);
        $this->assertTrue($organizer->hasMedia('logo'));
    }

    public function test_show_displays_organizer()
    {
        $organizer = Organizer::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.organizers.show', $organizer));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/Organizers/Show')
                ->has('organizer')
                ->has('pageTitle')
                ->has('breadcrumbs')
        );
    }

    public function test_edit_displays_form()
    {
        $organizer = Organizer::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.organizers.edit', $organizer));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/Organizers/Edit')
                ->has('organizer')
                ->has('countries')
                ->has('states')
                ->has('pageTitle')
                ->has('breadcrumbs')
        );
    }

    public function test_update_modifies_organizer()
    {
        $organizer = Organizer::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        $existingData = $organizer->toArray();

        $updateData = [
            'name' => [
                'en' => 'Updated Organizer EN',
                'zh-TW' => 'Updated Organizer TW',
            ],
            'slug' => $organizer->slug, // Keep same slug
            'description' => [
                'en' => 'Updated description EN',
            ],
            'contact_email' => 'updated@example.com',
            'is_active' => false,
        ];

        $finalData = array_merge($existingData, $updateData);

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.organizers.update', $organizer), $finalData);

        $response->assertRedirect(route('admin.organizers.index'));
        $response->assertSessionHas('success', 'Organizer updated successfully.');

        $organizer->refresh();
        $this->assertEquals('updated@example.com', $organizer->contact_email);
        $this->assertFalse($organizer->is_active);
    }

    public function test_destroy_deletes_organizer()
    {
        $organizer = Organizer::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('admin.organizers.destroy', $organizer));

        $response->assertRedirect(route('admin.organizers.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('organizers', ['id' => $organizer->id]);
    }

    public function test_index_can_filter_by_search()
    {
        Organizer::factory()->create([
            'name' => ['en' => 'Searchable Organizer']
        ]);
        Organizer::factory()->create([
            'name' => ['en' => 'Other Organizer']
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.organizers.index', ['search' => 'Searchable']));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->has('organizers.data', 1)
                ->where('filters.search', 'Searchable')
        );
    }

    public function test_index_can_filter_by_active_status()
    {
        Organizer::factory()->create(['is_active' => true]);
        Organizer::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.organizers.index', ['is_active' => '1']));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->has('organizers.data', 2)
                ->where('filters.is_active', '1')
        );
    }
}
