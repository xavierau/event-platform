<?php

namespace Database\Seeders;

use App\Models\Organizer;
use App\Models\User;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Enums\RedemptionMethodEnum;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎫 Seeding coupon templates and user coupons...');

        // Get organizers for coupon creation
        $organizers = Organizer::limit(3)->get();
        
        if ($organizers->isEmpty()) {
            $this->command->warn('No organizers found. Creating sample organizer for coupons...');
            $organizers = collect([Organizer::factory()->create()]);
        }

        // Create various coupon templates
        $couponTemplates = [];

        // 1. QR-only discount coupons
        $couponTemplates[] = Coupon::create([
            'organizer_id' => $organizers->random()->id,
            'name' => '20% Off Summer Sale',
            'description' => 'Get 20% off any summer merchandise. Valid until end of August.',
            'code' => 'SUMMER20',
            'type' => 'multi_use',
            'discount_value' => 20,
            'discount_type' => 'percentage',
            'max_issuance' => 500,
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
            'merchant_pin' => null,
            'valid_from' => now()->subDays(7),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        // 2. PIN-only exclusive coupons
        $couponTemplates[] = Coupon::create([
            'organizer_id' => $organizers->random()->id,
            'name' => 'VIP Member Exclusive',
            'description' => 'Exclusive discount for VIP members. PIN redemption only.',
            'code' => 'VIPONLY',
            'type' => 'multi_use',
            'discount_value' => 1500, // $15.00 fixed discount
            'discount_type' => 'fixed',
            'max_issuance' => 100,
            'redemption_methods' => [RedemptionMethodEnum::PIN->value],
            'merchant_pin' => '123456',
            'valid_from' => now()->subDays(3),
            'expires_at' => now()->addDays(60),
            'is_active' => true,
        ]);

        // 3. Both QR and PIN methods
        $couponTemplates[] = Coupon::create([
            'organizer_id' => $organizers->random()->id,
            'name' => 'Flexible Discount',
            'description' => 'Use QR code or PIN to redeem this flexible discount coupon.',
            'code' => 'FLEXIBLE',
            'type' => 'multi_use',
            'discount_value' => 10,
            'discount_type' => 'percentage',
            'max_issuance' => 300,
            'redemption_methods' => [RedemptionMethodEnum::QR->value, RedemptionMethodEnum::PIN->value],
            'merchant_pin' => '789012',
            'valid_from' => now()->subDays(5),
            'expires_at' => now()->addDays(45),
            'is_active' => true,
        ]);

        // 4. Single-use welcome coupon
        $couponTemplates[] = Coupon::create([
            'organizer_id' => $organizers->random()->id,
            'name' => 'Welcome New Customer',
            'description' => 'Welcome discount for first-time customers.',
            'code' => 'WELCOME10',
            'type' => 'single_use',
            'discount_value' => 10,
            'discount_type' => 'percentage',
            'max_issuance' => 1000,
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
            'merchant_pin' => null,
            'valid_from' => now()->subDays(10),
            'expires_at' => now()->addDays(90),
            'is_active' => true,
        ]);

        // 5. Multi-use loyalty coupon
        $couponTemplates[] = Coupon::create([
            'organizer_id' => $organizers->random()->id,
            'name' => 'Loyalty Rewards',
            'description' => 'Loyalty program coupon - use up to 5 times.',
            'code' => 'LOYAL5X',
            'type' => 'multi_use',
            'discount_value' => 500, // $5.00 fixed discount
            'discount_type' => 'fixed',
            'max_issuance' => 200,
            'redemption_methods' => [RedemptionMethodEnum::QR->value, RedemptionMethodEnum::PIN->value],
            'merchant_pin' => '456789',
            'valid_from' => now()->subDays(15),
            'expires_at' => now()->addDays(120),
            'is_active' => true,
        ]);

        // 6. Expired coupon for testing
        $couponTemplates[] = Coupon::create([
            'organizer_id' => $organizers->random()->id,
            'name' => 'Expired Holiday Special',
            'description' => 'This coupon has expired - for demo purposes.',
            'code' => 'EXPIRED',
            'type' => 'single_use',
            'discount_value' => 25,
            'discount_type' => 'percentage',
            'max_issuance' => 100,
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
            'merchant_pin' => null,
            'valid_from' => now()->subDays(90),
            'expires_at' => now()->subDays(7),
            'is_active' => false,
        ]);

        $this->command->info('✅ Created ' . count($couponTemplates) . ' coupon templates');

        // Now issue coupons to users
        $users = User::limit(20)->get();
        
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Creating sample users for coupon issuance...');
            $users = User::factory(10)->create();
        }

        $totalUserCoupons = 0;

        foreach ($couponTemplates as $coupon) {
            $this->command->info("📝 Issuing coupons for: {$coupon->name}");
            
            // Issue to random subset of users
            $usersForThisCoupon = $users->random(rand(5, min(15, $users->count())));
            
            foreach ($usersForThisCoupon as $user) {
                // Create various scenarios
                $scenarios = [
                    'active' => 60,      // 60% active coupons
                    'used' => 25,        // 25% partially or fully used
                    'expired' => 15,     // 15% expired
                ];
                
                $scenario = $this->weightedRandom($scenarios);
                
                // Determine usage count based on scenario
                $timesCanBeUsed = $coupon->type === 'single_use' ? 1 : rand(1, 5);
                $timesUsed = 0;
                $status = UserCouponStatusEnum::ACTIVE;
                $expiresAt = $coupon->expires_at;
                
                switch ($scenario) {
                    case 'used':
                        $timesUsed = rand(1, min(3, $timesCanBeUsed));
                        if ($timesUsed >= $timesCanBeUsed) {
                            $status = UserCouponStatusEnum::FULLY_USED;
                        }
                        break;
                        
                    case 'expired':
                        $status = UserCouponStatusEnum::EXPIRED;
                        $expiresAt = now()->subDays(rand(1, 30));
                        break;
                        
                    default: // active
                        // Keep default values
                        break;
                }
                
                $userCoupon = UserCoupon::create([
                    'user_id' => $user->id,
                    'coupon_id' => $coupon->id,
                    'unique_code' => $this->generateUniqueCode(),
                    'status' => $status,
                    'times_can_be_used' => $timesCanBeUsed,
                    'times_used' => $timesUsed,
                    'expires_at' => $expiresAt,
                    'issued_at' => now()->subDays(rand(0, 30)),
                ]);
                
                // Create usage logs for used coupons
                if ($timesUsed > 0) {
                    for ($i = 0; $i < $timesUsed; $i++) {
                        $userCoupon->usageLogs()->create([
                            'user_id' => $user->id,
                            'used_at' => now()->subDays(rand(1, 20)),
                            'location' => $this->getRandomLocation(),
                            'details' => [
                                'method' => collect($coupon->redemption_methods)->random(),
                                'discount_amount' => rand(5, 50),
                                'order_total' => rand(50, 200),
                            ],
                        ]);
                    }
                }
                
                $totalUserCoupons++;
            }
        }

        $this->command->info("✅ Created {$totalUserCoupons} user coupon instances");
        $this->command->info('🎉 Coupon seeding completed successfully!');
    }

    /**
     * Generate a unique coupon code
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (UserCoupon::where('unique_code', $code)->exists());
        
        return $code;
    }

    /**
     * Weighted random selection
     */
    private function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $random = rand(1, $total);
        
        $current = 0;
        foreach ($weights as $key => $weight) {
            $current += $weight;
            if ($random <= $current) {
                return $key;
            }
        }
        
        return array_key_first($weights);
    }

    /**
     * Get random location for usage logs
     */
    private function getRandomLocation(): string
    {
        $locations = [
            'Downtown Store',
            'Mall Location',
            'Online Store',
            'Airport Branch',
            'City Center',
            'Shopping District',
            'Outlet Store',
            'Express Location',
            'Main Branch',
            'Mobile Cart',
        ];
        
        return $locations[array_rand($locations)];
    }
}