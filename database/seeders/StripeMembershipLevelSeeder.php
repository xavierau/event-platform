<?php

namespace Database\Seeders;

use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Database\Seeder;

class StripeMembershipLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [
                'name' => ['en' => 'Free', 'zh-TW' => '免費', 'zh-CN' => '免费'],
                'slug' => 'free',
                'description' => [
                    'en' => 'Basic access to platform features',
                    'zh-TW' => '基本平台功能訪問',
                    'zh-CN' => '基本平台功能访问'
                ],
                'price' => 0,
                'points_cost' => 0,
                'kill_points_cost' => 0,
                'duration_months' => 12, // 12 months for free tier (indefinite)
                'stripe_product_id' => 'prod_free',
                'stripe_price_id' => env('STRIPE_PRICE_FREE', 'price_free'),
                'benefits' => [
                    'en' => 'Basic access to events\nEvent browsing\nBasic bookings\nProfile management',
                    'zh-TW' => '基本活動存取\n活動瀏覽\n基本預訂\n個人資料管理',
                    'zh-CN' => '基本活动访问\n活动浏览\n基本预订\n个人资料管理'
                ],
                'max_users' => null,
                'is_active' => true,
                'sort_order' => 1,
                'metadata' => [
                    'is_default' => true,
                    'trial_days' => 0,
                ],
            ],
            [
                'name' => ['en' => 'Premium', 'zh-TW' => '高級', 'zh-CN' => '高级'],
                'slug' => 'premium',
                'description' => [
                    'en' => 'Enhanced features and priority support',
                    'zh-TW' => '增強功能和優先支援',
                    'zh-CN' => '增强功能和优先支持'
                ],
                'price' => 2900, // $29.00
                'points_cost' => 2900,
                'kill_points_cost' => 0,
                'duration_months' => 1,
                'stripe_product_id' => 'prod_premium',
                'stripe_price_id' => env('STRIPE_PRICE_PREMIUM', 'price_premium_monthly'),
                'benefits' => [
                    'en' => 'Premium events access\nEarly access to bookings\nMember discounts\nPriority support\nAdvanced booking features\nExclusive content',
                    'zh-TW' => '高級活動存取\n優先預訂\n會員折扣\n優先支援\n進階預訂功能\n獨家內容',
                    'zh-CN' => '高级活动访问\n优先预订\n会员折扣\n优先支持\n高级预订功能\n独家内容'
                ],
                'max_users' => null,
                'is_active' => true,
                'sort_order' => 2,
                'metadata' => [
                    'is_popular' => true,
                    'trial_days' => 14,
                    'discount_percentage' => 10,
                ],
            ],
            [
                'name' => ['en' => 'VIP', 'zh-TW' => 'VIP', 'zh-CN' => 'VIP'],
                'slug' => 'vip',
                'description' => [
                    'en' => 'All-inclusive premium experience',
                    'zh-TW' => '全包高級體驗',
                    'zh-CN' => '全包高级体验'
                ],
                'price' => 9900, // $99.00
                'points_cost' => 9900,
                'kill_points_cost' => 0,
                'duration_months' => 1,
                'stripe_product_id' => 'prod_vip',
                'stripe_price_id' => env('STRIPE_PRICE_VIP', 'price_vip_monthly'),
                'benefits' => [
                    'en' => 'VIP events access\nPriority booking\nExclusive content\nPersonal concierge\nUnlimited bookings\nPremium support\nBackstage access\nSpecial perks',
                    'zh-TW' => 'VIP活動存取\n優先預訂\n獨家內容\n個人禮賓服務\n無限預訂\n高級支援\n後台存取\n特殊優惠',
                    'zh-CN' => 'VIP活动访问\n优先预订\n独家内容\n个人礼宾服务\n无限预订\n高级支持\n后台访问\n特殊优惠'
                ],
                'max_users' => 1000,
                'is_active' => true,
                'sort_order' => 3,
                'metadata' => [
                    'is_premium' => true,
                    'trial_days' => 7,
                    'discount_percentage' => 20,
                    'concierge_included' => true,
                ],
            ],
        ];
        
        foreach ($levels as $level) {
            MembershipLevel::updateOrCreate(
                ['slug' => $level['slug']],
                $level
            );
        }
    }
}
