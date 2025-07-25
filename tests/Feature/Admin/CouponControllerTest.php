<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleNameEnum;
use App\Models\Organizer;
use App\Models\User;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CouponControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;
    private Organizer $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required roles
        Role::create(['name' => RoleNameEnum::ADMIN->value]);
        Role::create(['name' => RoleNameEnum::USER->value]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleNameEnum::ADMIN);

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole(RoleNameEnum::USER);

        $this->organizer = Organizer::factory()->create();
    }

    public function test_admin_can_access_coupon_index()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.coupons.index'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/Coupons/Index')
                ->has('pageTitle')
                ->has('breadcrumbs')
                ->has('coupons')
        );
    }

    public function test_regular_user_cannot_access_coupon_index()
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.coupons.index'));

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_coupon_index()
    {
        $response = $this->get(route('admin.coupons.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_access_coupon_create()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.coupons.create'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/Coupons/Create')
                ->has('pageTitle')
                ->has('breadcrumbs')
                ->has('organizers')
        );
    }

    public function test_admin_can_store_coupon()
    {
        $couponData = [
            'organizer_id' => $this->organizer->id,
            'name' => 'Test Coupon',
            'description' => 'Test Description',
            'code' => 'TEST2024',
            'type' => CouponTypeEnum::SINGLE_USE->value,
            'discount_value' => 1000,
            'discount_type' => 'percentage',
            'max_issuance' => 100,
            'valid_from' => now()->format('Y-m-d H:i:s'),
            'expires_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.coupons.store'), $couponData);

        $response->assertRedirect(route('admin.coupons.index'));
        $response->assertSessionHas('success', 'Coupon created successfully.');

        $this->assertDatabaseHas('coupons', [
            'name' => 'Test Coupon',
            'code' => 'TEST2024',
            'organizer_id' => $this->organizer->id,
        ]);
    }

    public function test_admin_can_access_coupon_edit()
    {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.coupons.edit', $coupon));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/Coupons/Edit')
                ->has('pageTitle')
                ->has('breadcrumbs')
                ->has('coupon')
                ->has('organizers')
        );
    }

    public function test_admin_can_update_coupon()
    {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'name' => 'Original Name',
        ]);

        $updateData = [
            'organizer_id' => $this->organizer->id,
            'name' => 'Updated Coupon Name',
            'description' => 'Updated Description',
            'code' => $coupon->code, // Keep original code to avoid unique constraint
            'type' => CouponTypeEnum::MULTI_USE->value,
            'discount_value' => 1500,
            'discount_type' => 'percentage',
            'max_issuance' => 200,
            'valid_from' => now()->format('Y-m-d H:i:s'),
            'expires_at' => now()->addDays(60)->format('Y-m-d H:i:s'),
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.coupons.update', $coupon), $updateData);

        $response->assertRedirect(route('admin.coupons.index'));
        $response->assertSessionHas('success', 'Coupon updated successfully.');

        $coupon->refresh();
        $this->assertEquals('Updated Coupon Name', $coupon->name);
        $this->assertEquals(CouponTypeEnum::MULTI_USE, $coupon->type);
    }

    public function test_admin_can_delete_coupon()
    {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.coupons.destroy', $coupon));

        $response->assertRedirect(route('admin.coupons.index'));
        $response->assertSessionHas('success', 'Coupon deleted successfully.');

        $this->assertDatabaseMissing('coupons', [
            'id' => $coupon->id,
        ]);
    }

    public function test_admin_can_view_coupon_details()
    {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.coupons.show', $coupon));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/Coupons/Show')
                ->has('pageTitle')
                ->has('breadcrumbs')
                ->has('coupon')
                ->has('statistics')
        );
    }

    public function test_store_validates_required_fields()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.coupons.store'), []);

        $response->assertSessionHasErrors([
            'organizer_id',
            'name',
            'code',
            'type',
            'discount_value',
            'discount_type',
        ]);
    }

    public function test_store_validates_unique_code()
    {
        $existingCoupon = Coupon::factory()->create(['code' => 'EXISTING']);

        $couponData = [
            'organizer_id' => $this->organizer->id,
            'name' => 'Test Coupon',
            'description' => 'Test Description',
            'code' => 'EXISTING', // Duplicate code
            'type' => CouponTypeEnum::SINGLE_USE->value,
            'discount_value' => 1000,
            'discount_type' => 'percentage',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.coupons.store'), $couponData);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_update_allows_same_code_for_same_coupon()
    {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'code' => 'UNCHANGED',
        ]);

        $updateData = [
            'organizer_id' => $this->organizer->id,
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'code' => 'UNCHANGED', // Same code should be allowed
            'type' => CouponTypeEnum::MULTI_USE->value,
            'discount_value' => 1500,
            'discount_type' => 'percentage',
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.coupons.update', $coupon), $updateData);

        $response->assertRedirect(route('admin.coupons.index'));
        $response->assertSessionHas('success');
    }
}
