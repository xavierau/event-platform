<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [
                'name' => ['en' => 'Hong Kong', 'zh-TW' => '香港', 'zh-CN' => '香港'],
                'iso_code_2' => 'HK',
                'iso_code_3' => 'HKG',
                'phone_code' => '+852',
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Macau', 'zh-TW' => '澳門', 'zh-CN' => '澳门'],
                'iso_code_2' => 'MO',
                'iso_code_3' => 'MAC',
                'phone_code' => '+853',
                'is_active' => true,
            ],
        ];

        // Remove existing countries first to ensure only HK and Macau are present if re-running
        // Country::query()->delete(); // Or be more specific if needed

        foreach ($countries as $countryData) {
            Country::updateOrCreate(
                ['iso_code_2' => $countryData['iso_code_2']], // Unique key
                $countryData
            );
        }
    }
}
