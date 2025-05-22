<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Venue;
use App\Models\Country;
use App\Models\User; // Assuming Organizer is a User

class VenueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Attempt to find a default country (should be created by CountrySeeder)
        $defaultCountry = Country::where('iso_code_2', 'US')->first();
        if (!$defaultCountry) {
            $this->command->error('Default country with iso_code_2 = US not found. Please ensure CountrySeeder has run and created it. Skipping venue seeding.');
            return;
        }

        // Ensure an organizer user exists (e.g., from OrganizerSeeder)
        $defaultOrganizer = User::where('email', 'organizer1@example.com')->first();
        if (!$defaultOrganizer) {
            $this->command->warn('Default organizer (organizer1@example.com) not found. Venue seeding will proceed but venues may not have an organizer.');
        }

        $venues = [
            [
                'name' => ['en' => 'Grand Hall', 'zh-TW' => '宏偉大廳'],
                'slug' => 'grand-hall',
                'description' => ['en' => 'A large hall for major events.', 'zh-TW' => '舉辦大型活動的大廳。'],
                'organizer_id' => $defaultOrganizer?->id,
                'address_line_1' => ['en' => '123 Main St', 'zh-TW' => '主要街道123號'],
                'city' => ['en' => 'New York', 'zh-TW' => '紐約'],
                'country_id' => $defaultCountry->id,
                'postal_code' => '10001',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'contact_email' => 'contact@grandhall.com',
                'seating_capacity' => 1000,
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Tech Hub Center', 'zh-TW' => '科技中心'],
                'slug' => 'tech-hub-center',
                'description' => ['en' => 'Modern venue for tech conferences.', 'zh-TW' => '舉辦科技會議的現代化場所。'],
                'organizer_id' => $defaultOrganizer?->id,
                'address_line_1' => ['en' => '456 Innovation Dr', 'zh-TW' => '創新大道456號'],
                'city' => ['en' => 'San Francisco', 'zh-TW' => '舊金山'],
                'country_id' => $defaultCountry->id,
                'postal_code' => '94107',
                'latitude' => 37.7749,
                'longitude' => -122.4194,
                'contact_email' => 'info@techhub.com',
                'seating_capacity' => 500,
                'is_active' => true,
            ],
        ];

        foreach ($venues as $venueData) {
            Venue::firstOrCreate(['slug' => $venueData['slug']], $venueData);
        }
        $this->command->info('Venues seeded (if default country and organizer were found).');
    }
}
