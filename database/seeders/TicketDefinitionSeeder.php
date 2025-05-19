<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TicketDefinition;

class TicketDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TicketDefinition::factory()->create([
            'name' => ['en' => 'General Admission', 'zh-TW' => '普通票', 'zh-CN' => '普通票'],
            'price' => 2500, // e.g., 25.00
            'currency' => 'USD',
            'status' => 'active',
        ]);

        TicketDefinition::factory()->create([
            'name' => ['en' => 'VIP Pass', 'zh-TW' => 'VIP通行證', 'zh-CN' => 'VIP通行证'],
            'price' => 7500, // e.g., 75.00
            'currency' => 'USD',
            'status' => 'active',
        ]);

        TicketDefinition::factory()->create([
            'name' => ['en' => 'Early Bird Special', 'zh-TW' => '早鳥優惠', 'zh-CN' => '早鸟优惠'],
            'price' => 2000, // e.g., 20.00
            'currency' => 'USD',
            'status' => 'active',
            'availability_window_start_utc' => now()->subDays(10),
            'availability_window_end_utc' => now()->addDays(20),
        ]);

        TicketDefinition::factory()->create([
            'name' => ['en' => 'Student Discount', 'zh-TW' => '學生折扣', 'zh-CN' => '学生折扣'],
            'price' => 1500, // e.g., 15.00
            'currency' => 'USD',
            'status' => 'active',
            'description' => ['en' => 'Requires valid student ID at entry.', 'zh-TW' => '入場時需出示有效學生證。', 'zh-CN' => '入场时需出示有效学生证。'],
        ]);

        // Create a few more generic ones
        TicketDefinition::factory(5)->create();
    }
}
