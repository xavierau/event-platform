<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleNameEnum;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromotionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles if they don't exist
        Role::firstOrCreate(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);

        // Create an admin user
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleNameEnum::ADMIN->value);

        Config::set('app.locale', 'en');
    }

    #[Test]
    public function it_displays_promotions_index_page()
    {
        $promotions = Promotion::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.promotions.index'));

        $response->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Admin/Promotion/Index')
                    ->has('promotions', 3)
            );
    }

    #[Test]
    public function it_displays_create_promotion_page()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.promotions.create'));

        $response->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Admin/Promotion/Create')
            );
    }

    #[Test]
    public function it_can_create_a_promotion()
    {
        Storage::fake('public');

        $data = [
            'title' => ['en' => 'Summer Sale', 'zh-TW' => '夏季特賣'],
            'subtitle' => ['en' => 'Up to 50% off', 'zh-TW' => '最高5折優惠'],
            'url' => 'https://example.com/summer-sale',
            'is_active' => true,
            'starts_at' => '2024-01-01 00:00:00',
            'ends_at' => '2024-12-31 23:59:59',
            'sort_order' => 1,
            'uploaded_banner_image' => UploadedFile::fake()->image('banner.jpg'),
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.promotions.store'), $data);

        $response->assertRedirect(route('admin.promotions.index'));

        $this->assertDatabaseHas('promotions', [
            'url' => 'https://example.com/summer-sale',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $promotion = Promotion::first();
        $this->assertEquals('Summer Sale', $promotion->getTranslation('title', 'en'));
        $this->assertEquals('夏季特賣', $promotion->getTranslation('title', 'zh-TW'));
        $this->assertCount(1, $promotion->getMedia('banner'));
    }

    #[Test]
    public function it_validates_required_fields_on_create()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.promotions.store'), []);

        $response->assertSessionHasErrors(['url']);
    }

    #[Test]
    public function it_displays_edit_promotion_page()
    {
        $promotion = Promotion::factory()->create([
            'title' => ['en' => 'Test Title EN', 'zh-TW' => '測試標題'],
            'subtitle' => ['en' => 'Test Subtitle EN', 'zh-TW' => '測試副標題'],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.promotions.edit', $promotion));

        $response->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Admin/Promotion/Edit')
                    ->has(
                        'promotion',
                        fn(Assert $prop) => $prop
                            ->where('id', $promotion->id)
                            ->where('title.en', 'Test Title EN')
                            ->where('title.zh-TW', '測試標題')
                            ->where('subtitle.en', 'Test Subtitle EN')
                            ->where('subtitle.zh-TW', '測試副標題')
                            ->etc()
                    )
            );
    }

    #[Test]
    public function it_can_update_a_promotion()
    {
        Storage::fake('public');

        $promotion = Promotion::factory()->create([
            'title' => ['en' => 'Old Title'],
            'subtitle' => ['en' => 'Old Subtitle'],
            'url' => 'https://example.com/old',
        ]);

        $data = [
            'title' => ['en' => 'New Title', 'zh-TW' => '新標題'],
            'subtitle' => ['en' => 'New Subtitle', 'zh-TW' => '新副標題'],
            'url' => 'https://example.com/new',
            'is_active' => true,
            'sort_order' => 2,
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.promotions.update', $promotion), $data);

        $response->assertRedirect(route('admin.promotions.index'));

        $promotion->refresh();
        $this->assertEquals('New Title', $promotion->getTranslation('title', 'en'));
        $this->assertEquals('新標題', $promotion->getTranslation('title', 'zh-TW'));
        $this->assertEquals('https://example.com/new', $promotion->url);
    }

    #[Test]
    public function it_can_delete_a_promotion()
    {
        Storage::fake('public');

        $promotion = Promotion::factory()->create();
        $promotion->addMedia(UploadedFile::fake()->image('banner.jpg'))->toMediaCollection('banner');

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.promotions.destroy', $promotion));

        $response->assertRedirect(route('admin.promotions.index'));

        $this->assertDatabaseMissing('promotions', ['id' => $promotion->id]);
    }

    #[Test]
    public function it_requires_authentication()
    {
        $response = $this->get(route('admin.promotions.index'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function it_validates_url_format()
    {
        $data = [
            'title' => ['en' => 'Test'],
            'subtitle' => ['en' => 'Test'],
            'url' => 'not-a-valid-url',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.promotions.store'), $data);

        $response->assertSessionHasErrors(['url']);
    }

    #[Test]
    public function it_validates_date_order()
    {
        $data = [
            'title' => ['en' => 'Test'],
            'subtitle' => ['en' => 'Test'],
            'url' => 'https://example.com',
            'starts_at' => '2024-12-31',
            'ends_at' => '2024-01-01', // Before starts_at
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.promotions.store'), $data);

        $response->assertSessionHasErrors(['ends_at']);
    }
}
