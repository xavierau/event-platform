<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Early Bird Promotion
        $earlyBird = Promotion::create([
            'title' => [
                'en' => 'Early Bird Special',
                'zh-TW' => '早鳥優惠'
            ],
            'subtitle' => [
                'en' => 'Save 20% on all events booked before the end of the month',
                'zh-TW' => '月底前預訂所有活動可享8折優惠'
            ],
            'url' => 'https://example.com/early-bird',
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => now()->endOfMonth(),
            'sort_order' => 1,
        ]);

        // Summer Festival Promotion
        $summerFest = Promotion::create([
            'title' => [
                'en' => 'Summer Festival 2024',
                'zh-TW' => '2024夏日音樂節'
            ],
            'subtitle' => [
                'en' => 'Experience the hottest music festival of the year',
                'zh-TW' => '體驗年度最熱門的音樂節'
            ],
            'url' => 'https://example.com/summer-festival',
            'is_active' => true,
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addMonths(2),
            'sort_order' => 2,
        ]);

        // VIP Membership Promotion
        $vipMembership = Promotion::create([
            'title' => [
                'en' => 'VIP Membership Benefits',
                'zh-TW' => 'VIP會員專屬優惠'
            ],
            'subtitle' => [
                'en' => 'Exclusive access and priority booking for members',
                'zh-TW' => '會員專享優先預訂權'
            ],
            'url' => 'https://example.com/vip-membership',
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 3,
        ]);

        // Past Promotion (inactive)
        $pastPromo = Promotion::create([
            'title' => [
                'en' => 'New Year Celebration',
                'zh-TW' => '新年慶典'
            ],
            'subtitle' => [
                'en' => 'Ring in the new year with us',
                'zh-TW' => '與我們一起迎接新年'
            ],
            'url' => 'https://example.com/new-year',
            'is_active' => false,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
            'sort_order' => 99,
        ]);

        // Add sample banner images if in development
        if (app()->environment('local', 'development')) {
            Storage::fake('public');

            // Create fake banner images
            $banners = [
                ['promotion' => $earlyBird, 'filename' => 'early-bird-banner.jpg'],
                ['promotion' => $summerFest, 'filename' => 'summer-festival-banner.jpg'],
                ['promotion' => $vipMembership, 'filename' => 'vip-membership-banner.jpg'],
            ];

            foreach ($banners as $banner) {
                $promotion = $banner['promotion'];
                $filename = $banner['filename'];
                $file = UploadedFile::fake()->image($filename, 1920, 600);
                $promotion->addMedia($file)->toMediaCollection('banner');
            }
        }
    }
}
