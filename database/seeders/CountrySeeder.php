<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [
                'name' => ['en' => 'United States', 'zh-TW' => '美國'],
                'iso_code_2' => 'US',
                'iso_code_3' => 'USA',
                'phone_code' => '+1',
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Canada', 'zh-TW' => '加拿大'],
                'iso_code_2' => 'CA',
                'iso_code_3' => 'CAN',
                'phone_code' => '+1',
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'United Kingdom', 'zh-TW' => '英國'],
                'iso_code_2' => 'GB',
                'iso_code_3' => 'GBR',
                'phone_code' => '+44',
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Taiwan', 'zh-TW' => '台灣'],
                'iso_code_2' => 'TW',
                'iso_code_3' => 'TWN',
                'phone_code' => '+886',
                'is_active' => true,
            ],
            // Add more countries as needed
        ];

        foreach ($countries as $countryData) {
            Country::updateOrCreate(
                ['iso_code_2' => $countryData['iso_code_2']], // Use a unique key for lookup
                $countryData
            );
        }
        $this->command->info('Countries seeded.');
    }
}
