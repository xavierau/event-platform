<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => ['en' => 'Music Concerts', 'zh-TW' => '音樂演唱會'],
                'slug' => 'music-concerts',
                // 'parent_id' => null, // For top-level categories
            ],
            [
                'name' => ['en' => 'Tech Conferences', 'zh-TW' => '科技研討會'],
                'slug' => 'tech-conferences',
            ],
            [
                'name' => ['en' => 'Art Exhibitions', 'zh-TW' => '藝術展覽'],
                'slug' => 'art-exhibitions',
            ],
            [
                'name' => ['en' => 'Workshops', 'zh-TW' => '工作坊'],
                'slug' => 'workshops',
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::firstOrCreate(['slug' => $categoryData['slug']], $categoryData);
        }
        $this->command->info('Categories seeded.');
    }
}
