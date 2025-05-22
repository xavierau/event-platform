<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tag;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            [
                'name' => ['en' => 'Laravel', 'zh-TW' => 'Laravel框架'],
                'slug' => 'laravel',
            ],
            [
                'name' => ['en' => 'Vue.js', 'zh-TW' => 'Vue.js前端'],
                'slug' => 'vuejs',
            ],
            [
                'name' => ['en' => 'Live Music', 'zh-TW' => '現場音樂'],
                'slug' => 'live-music',
            ],
            [
                'name' => ['en' => 'Networking', 'zh-TW' => '商務社交'],
                'slug' => 'networking',
            ],
        ];

        foreach ($tags as $tagData) {
            Tag::firstOrCreate(['slug' => $tagData['slug']], $tagData);
        }
        $this->command->info('Tags seeded.');
    }
}
