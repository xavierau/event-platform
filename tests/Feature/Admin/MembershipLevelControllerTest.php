<?php

namespace Tests\Feature\Admin;

use App\Modules\Membership\Models\MembershipLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MembershipLevelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // Create admin user with necessary permissions
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole(\App\Enums\RoleNameEnum::ADMIN->value);
    }

    public function test_index_displays_membership_levels()
    {
        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Premium Plan', 'zh-TW' => '高級方案'],
            'slug' => 'premium',
            'price' => 2999,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/admin/membership-levels');

        $response->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MembershipLevels/Index')
                ->has('membershipLevels', 1)
                ->where('membershipLevels.0.id', $membershipLevel->id)
                ->where('membershipLevels.0.name', ['en' => 'Premium Plan', 'zh-TW' => '高級方案'])
                ->where('membershipLevels.0.slug', 'premium')
                ->where('membershipLevels.0.price', 2999)
                ->has('pageTitle')
                ->has('breadcrumbs')
            );
    }

    public function test_create_page_displays()
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/admin/membership-levels/create');

        $response->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MembershipLevels/Create')
                ->has('pageTitle')
                ->has('breadcrumbs')
            );
    }

    public function test_store_creates_membership_level()
    {
        $membershipLevelData = [
            'name' => [
                'en' => 'Premium Plan',
                'zh-TW' => '高級方案',
                'zh-CN' => '高级方案',
            ],
            'slug' => 'premium',
            'description' => [
                'en' => 'Premium membership with extra features',
                'zh-TW' => '具有額外功能的高級會員',
                'zh-CN' => '具有额外功能的高级会员',
            ],
            'benefits' => [
                'en' => 'Access to premium content\nPriority support',
                'zh-TW' => '存取高級內容\n優先支援',
                'zh-CN' => '访问高级内容\n优先支持',
            ],
            'price' => 2999,
            'duration_months' => 12,
            'is_active' => true,
            'sort_order' => 1,
            'stripe_product_id' => 'prod_test123',
            'stripe_price_id' => 'price_test123',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post('/admin/membership-levels', $membershipLevelData);

        $response->assertRedirect(route('admin.membership-levels.index'));

        $this->assertDatabaseHas('membership_levels', [
            'slug' => 'premium',
            'price' => 2999,
            'is_active' => true,
            'sort_order' => 1,
            'stripe_product_id' => 'prod_test123',
            'stripe_price_id' => 'price_test123',
        ]);

        $membershipLevel = MembershipLevel::where('slug', 'premium')->first();
        $this->assertEquals([
            'en' => 'Premium Plan',
            'zh-TW' => '高級方案',
            'zh-CN' => '高级方案',
        ], $membershipLevel->name);
    }

    public function test_store_validates_required_fields()
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/membership-levels', []);

        $response->assertSessionHasErrors([
            'name.en',
            'slug',
            'price',
            'sort_order',
        ]);
    }

    public function test_show_displays_membership_level()
    {
        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Premium Plan', 'zh-TW' => '高級方案'],
            'slug' => 'premium',
            'benefits' => ['en' => 'Premium benefits', 'zh-TW' => '高級優惠'],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.membership-levels.show', $membershipLevel));

        $response->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MembershipLevels/Show')
                ->where('membershipLevel.id', $membershipLevel->id)
                ->where('membershipLevel.name', ['en' => 'Premium Plan', 'zh-TW' => '高級方案'])
                ->where('membershipLevel.slug', 'premium')
                ->has('pageTitle')
                ->has('breadcrumbs')
            );
    }

    public function test_edit_displays_membership_level()
    {
        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Premium Plan', 'zh-TW' => '高級方案'],
            'benefits' => ['en' => 'Premium benefits', 'zh-TW' => '高級優惠'],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.membership-levels.edit', $membershipLevel));

        $response->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MembershipLevels/Edit')
                ->where('membershipLevel.id', $membershipLevel->id)
                ->where('membershipLevel.name', ['en' => 'Premium Plan', 'zh-TW' => '高級方案'])
                ->has('pageTitle')
                ->has('breadcrumbs')
            );
    }

    public function test_update_modifies_membership_level()
    {
        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Old Plan'],
            'slug' => 'old-plan',
            'price' => 1999,
        ]);

        $updateData = [
            'name' => [
                'en' => 'Updated Premium Plan',
                'zh-TW' => '更新的高級方案',
            ],
            'slug' => 'updated-premium',
            'description' => [
                'en' => 'Updated description',
                'zh-TW' => '更新的描述',
            ],
            'benefits' => [
                'en' => 'Updated benefits',
                'zh-TW' => '更新的優惠',
            ],
            'price' => 3999,
            'duration_months' => 6,
            'is_active' => false,
            'sort_order' => 2,
            'stripe_product_id' => 'prod_updated123',
            'stripe_price_id' => 'price_updated123',
        ];

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.membership-levels.update', $membershipLevel), $updateData);

        $response->assertRedirect(route('admin.membership-levels.show', $membershipLevel));

        $membershipLevel->refresh();
        
        $this->assertEquals([
            'en' => 'Updated Premium Plan',
            'zh-TW' => '更新的高級方案',
        ], $membershipLevel->name);
        $this->assertEquals('updated-premium', $membershipLevel->slug);
        $this->assertEquals(3999, $membershipLevel->price);
        $this->assertFalse($membershipLevel->is_active);
        $this->assertEquals('prod_updated123', $membershipLevel->stripe_product_id);
        $this->assertEquals('price_updated123', $membershipLevel->stripe_price_id);
    }

    public function test_destroy_deletes_membership_level()
    {
        $membershipLevel = MembershipLevel::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('admin.membership-levels.destroy', $membershipLevel));

        $response->assertRedirect(route('admin.membership-levels.index'));

        $this->assertDatabaseMissing('membership_levels', [
            'id' => $membershipLevel->id,
        ]);
    }
}