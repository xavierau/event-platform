<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SiteSetting::updateOrCreate(['key' => 'site_name'], [
            'value' => [
                'en' => 'Event Platform', // From your screenshot, default was 'ShowEasy'
                'zh-TW' => '活動平台',
                'zh-CN' => '活动平台',
            ],
        ]);

        SiteSetting::updateOrCreate(['key' => 'site_slogan'], [
            'value' => [
                'en' => 'Host Events. Sell Tickets.', // From your screenshot
                'zh-TW' => '舉辦活動，銷售門票。',
                'zh-CN' => '举办活动，销售门票。',
            ],
        ]);

        SiteSetting::updateOrCreate(['key' => 'extra_footer_credits'], [
            'value' => [
                'en' => 'Powered by EventPlatform', // From your screenshot, default was 'ShowEasy'
                'zh-TW' => '由 活動平台 驅動',
                'zh-CN' => '由 活动平台 驱动',
            ],
        ]);

        // SEO Tab Example
        SiteSetting::updateOrCreate(['key' => 'meta_title'], [
            'value' => [
                'en' => 'Event Platform - Your Ultimate Event Destination', // Default was 'ShowEasy'
                'zh-TW' => '活動平台 - 您的終極活動目的地',
                'zh-CN' => '活动平台 - 您的终极活动目的地',
            ],
        ]);

        // Regional Tab Examples
        SiteSetting::updateOrCreate(['key' => 'timezone'], [
            'value' => ['en' => 'Asia/Hong_Kong'], // Non-translatable, stored in 'en'
        ]);

        SiteSetting::updateOrCreate(['key' => 'currency_code'], [
            'value' => ['en' => 'HKD'], // Non-translatable
        ]);

        SiteSetting::updateOrCreate(['key' => 'date_format'], [
            'value' => ['en' => 'D M Y'], // Non-translatable
        ]);

        SiteSetting::updateOrCreate(['key' => 'time_format'], [
            'value' => ['en' => '12 Hours'], // Non-translatable
        ]);

        // Booking Tab Example (Boolean)
        SiteSetting::updateOrCreate(['key' => 'hide_expired_events'], [
            'value' => ['en' => true], // Storing boolean as true/false
        ]);

        // Mail Tab Example
        SiteSetting::updateOrCreate(['key' => 'mail_sender_email'], [
            'value' => ['en' => 'noreply@example.com'],
        ]);

        SiteSetting::updateOrCreate(['key' => 'mail_sender_name'], [
            'value' => [
                'en' => 'Event Platform Mailer',
                'zh-TW' => '活動平台郵件服務',
                'zh-CN' => '活动平台邮件服务',
            ],
        ]);

        // Chatbot Settings
        SiteSetting::updateOrCreate(['key' => 'enable_chatbot'], [
            'value' => ['en' => true],
        ]);

        // Add more settings here based on the screenshots...
        // Example for an image path (actual upload handled by UI)
        // SiteSetting::updateOrCreate(['key' => 'site_logo'], [
        //     'value' => ['en' => '/images/default_logo.png'],
        // ]);
    }
}
