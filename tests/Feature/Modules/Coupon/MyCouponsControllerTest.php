<?php

namespace Tests\Feature\Modules\Coupon;

use App\Models\User;
use App\Models\Organizer;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Enums\RedemptionMethodEnum;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyCouponsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organizer $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organizer = Organizer::factory()->create();
    }

    /** @test */
    public function authenticated_user_can_view_my_coupons_page()
    {
        $response = $this->actingAs($this->user)
            ->get('/my-coupons');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Public/MyCoupons')
                ->has('coupons')
                ->has('statistics')
                ->has('filters')
            );
    }

    /** @test */
    public function guest_cannot_access_my_coupons_page()
    {
        $response = $this->get('/my-coupons');

        $response->assertRedirect('/login');
    }

    /** @test */
    public function displays_user_coupons_with_correct_data_structure()
    {
        // Create coupons with different statuses
        $activeCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'name' => 'Active Coupon',
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
        ]);

        $pinCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'name' => 'PIN Coupon',
            'redemption_methods' => [RedemptionMethodEnum::PIN->value],
            'merchant_pin' => '123456',
        ]);

        $userCoupon1 = UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $activeCoupon->id,
            'unique_code' => 'ACTIVE123',
            'times_can_be_used' => 3,
            'times_used' => 1,
        ]);

        $userCoupon2 = UserCoupon::factory()->expired()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $pinCoupon->id,
            'unique_code' => 'EXPIRED456',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/my-coupons');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Public/MyCoupons')
                ->has('coupons.data', 2)
                // Verify both coupons exist regardless of order
                ->whereContains('coupons.data', fn ($coupon) => 
                    $coupon['coupon']['name'] === 'PIN Coupon' && $coupon['coupon']['has_pin'] === true
                )
                ->whereContains('coupons.data', fn ($coupon) => 
                    $coupon['coupon']['name'] === 'Active Coupon' && 
                    $coupon['coupon']['has_pin'] === false &&
                    $coupon['times_used'] === 1 &&
                    $coupon['times_can_be_used'] === 3
                )
            );
    }

    /** @test */
    public function displays_correct_statistics()
    {
        // Create various coupon types
        $activeCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
        ]);

        // Active coupon
        UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $activeCoupon->id,
        ]);

        // Expired coupon
        UserCoupon::factory()->expired()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $activeCoupon->id,
        ]);

        // Fully used coupon
        UserCoupon::factory()->fullyUsed()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $activeCoupon->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/my-coupons');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('statistics.total', 3)
                ->where('statistics.active', 1)
                ->where('statistics.expired', 1)
                ->where('statistics.fully_used', 1)
            );
    }

    /** @test */
    public function can_filter_coupons_by_status()
    {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
        ]);

        // Create active and expired coupons
        UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'unique_code' => 'ACTIVE123',
        ]);

        UserCoupon::factory()->expired()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'unique_code' => 'EXPIRED456',
        ]);

        // Test active filter
        $response = $this->actingAs($this->user)
            ->get('/my-coupons?status=active');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('filters.status', 'active')
                ->has('coupons.data', 1)
                ->where('coupons.data.0.unique_code', 'ACTIVE123')
            );

        // Test expired filter
        $response = $this->actingAs($this->user)
            ->get('/my-coupons?status=expired');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('filters.status', 'expired')
                ->has('coupons.data', 1)
                ->where('coupons.data.0.unique_code', 'EXPIRED456')
            );
    }

    /** @test */
    public function can_search_coupons_by_name_and_code()
    {
        $coupon1 = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'name' => 'Summer Sale Coupon',
            'code' => 'SUMMER2024',
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
        ]);

        $coupon2 = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'name' => 'Winter Discount',
            'code' => 'WINTER2024',
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
        ]);

        UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon1->id,
            'unique_code' => 'USER_SUMMER',
        ]);

        UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon2->id,
            'unique_code' => 'USER_WINTER',
        ]);

        // Search by coupon name
        $response = $this->actingAs($this->user)
            ->get('/my-coupons?search=Summer');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('filters.search', 'Summer')
                ->has('coupons.data', 1)
                ->where('coupons.data.0.coupon.name', 'Summer Sale Coupon')
            );

        // Search by unique code
        $response = $this->actingAs($this->user)
            ->get('/my-coupons?search=USER_WINTER');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('filters.search', 'USER_WINTER')
                ->has('coupons.data', 1)
                ->where('coupons.data.0.unique_code', 'USER_WINTER')
            );
    }

    /** @test */
    public function shows_redemption_methods_correctly()
    {
        // QR only coupon
        $qrCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
            'merchant_pin' => null,
        ]);

        // PIN only coupon
        $pinCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::PIN->value],
            'merchant_pin' => '123456',
        ]);

        // Both methods coupon
        $bothCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::QR->value, RedemptionMethodEnum::PIN->value],
            'merchant_pin' => '789012',
        ]);

        UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $qrCoupon->id,
            'unique_code' => 'QR_ONLY',
        ]);

        UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $pinCoupon->id,
            'unique_code' => 'PIN_ONLY',
        ]);

        UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $bothCoupon->id,
            'unique_code' => 'BOTH_METHODS',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/my-coupons');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('coupons.data', 3)
                // Test that all three redemption method types are present
                ->whereContains('coupons.data', fn ($coupon) => 
                    $coupon['coupon']['redemption_methods'] === [RedemptionMethodEnum::QR->value]
                )
                ->whereContains('coupons.data', fn ($coupon) => 
                    $coupon['coupon']['redemption_methods'] === [RedemptionMethodEnum::PIN->value]
                )
                ->whereContains('coupons.data', fn ($coupon) => 
                    $coupon['coupon']['redemption_methods'] === [RedemptionMethodEnum::QR->value, RedemptionMethodEnum::PIN->value]
                )
            );
    }

    /** @test */
    public function includes_usage_logs_in_coupon_data()
    {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
        ]);

        $userCoupon = UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'unique_code' => 'WITH_LOGS',
            'times_used' => 2,
        ]);

        // Create usage logs
        $userCoupon->usageLogs()->create([
            'user_id' => $this->user->id,
            'used_at' => now()->subHour(),
            'location' => 'Store A',
            'details' => ['note' => 'First usage'],
        ]);

        $userCoupon->usageLogs()->create([
            'user_id' => $this->user->id,
            'used_at' => now(),
            'location' => 'Store B',
            'details' => ['note' => 'Second usage'],
        ]);

        $response = $this->actingAs($this->user)
            ->get('/my-coupons');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('coupons.data', 1)
                ->has('coupons.data.0.usage_logs', 2)
                ->has('coupons.data.0.usage_logs.0.location')
                ->has('coupons.data.0.usage_logs.1.location')
            );
    }

    /** @test */
    public function does_not_show_other_users_coupons()
    {
        $otherUser = User::factory()->create();
        
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
        ]);

        // Create coupon for other user
        UserCoupon::factory()->active()->create([
            'user_id' => $otherUser->id,
            'coupon_id' => $coupon->id,
            'unique_code' => 'OTHER_USER',
        ]);

        // Create coupon for current user
        UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'unique_code' => 'CURRENT_USER',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/my-coupons');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('coupons.data', 1)
                ->where('coupons.data.0.unique_code', 'CURRENT_USER')
            );
    }

    /** @test */
    public function pagination_works_correctly()
    {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
        ]);

        // Create 15 coupons (more than page size of 12)
        for ($i = 1; $i <= 15; $i++) {
            UserCoupon::factory()->active()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
                'unique_code' => "COUPON_{$i}",
            ]);
        }

        $response = $this->actingAs($this->user)
            ->get('/my-coupons');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('coupons.data', 12) // First page shows 12 items
                ->has('coupons.links')
            );
    }
}