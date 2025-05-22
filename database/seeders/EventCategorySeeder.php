<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class EventCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $categories = [
            [
                'name' => ['en' => 'Music Concerts', 'zh-TW' => '音樂演唱會', 'zh-CN' => '音乐演唱会'],
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Tech Conferences', 'zh-TW' => '科技研討會', 'zh-CN' => '科技研讨会'],
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Art Exhibitions', 'zh-TW' => '藝術展覽', 'zh-CN' => '艺术展览'],
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Food Festivals', 'zh-TW' => '美食節', 'zh-CN' => '美食节'],
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Sports Events', 'zh-TW' => '體育賽事', 'zh-CN' => '体育赛事'],
                'is_active' => false, // Example of an inactive category
            ],
            [
                'name' => ['en' => 'Workshops & Classes', 'zh-TW' => '工作坊與課程', 'zh-CN' => '工作坊与课程'],
                'parent_id' => null, // Explicitly null, though default
                'is_active' => true,
            ],
        ];

        foreach ($categories as $categoryData) {
            $slug_en = Str::slug($categoryData['name']['en']);
            Category::create([
                'name' => $categoryData['name'],
                'slug' => $slug_en, // Slug is not translatable based on Category model
                'parent_id' => $categoryData['parent_id'] ?? null,
                'is_active' => $categoryData['is_active'],
            ]);
        }

        // Example of a sub-category
        $parentWorkshop = Category::where('slug', Str::slug('Workshops & Classes'))->first();
        if ($parentWorkshop) {
            Category::create([
                'name' => ['en' => 'Coding Workshops', 'zh-TW' => '程式設計工作坊', 'zh-CN' => '编程工作坊'],
                'slug' => Str::slug('Coding Workshops'),
                'parent_id' => $parentWorkshop->id,
                'is_active' => true,
            ]);
            Category::create([
                'name' => ['en' => 'Photography Classes', 'zh-TW' => '攝影課程', 'zh-CN' => '摄影课程'],
                'slug' => Str::slug('Photography Classes'),
                'parent_id' => $parentWorkshop->id,
                'is_active' => true,
            ]);
        }
    }
}
