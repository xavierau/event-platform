<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Venue;
use App\Models\Country;
use App\Models\Organizer;
use Database\Seeders\OrganizerSeeder;

class VenueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure dependencies exist
        $defaultCountry = Country::where('iso_code_2', 'US')->first();
        if (!$defaultCountry) {
            $this->command->error('Default country with iso_code_2 = US not found. Please ensure CountrySeeder has run first.');
            return;
        }

        // Ensure organizers exist for organizer-specific venues
        $organizers = Organizer::where('is_active', true)->get();
        if ($organizers->isEmpty()) {
            $this->command->info('No active organizers found. Running OrganizerSeeder first...');
            $this->call(OrganizerSeeder::class);
            $organizers = Organizer::where('is_active', true)->get();
        }

        // Get a sample organizer for organizer-specific venues
        $sampleOrganizer = $organizers->first();

        $venues = [
            // Public venues (organizer_id = null)
            [
                'name' => ['en' => 'Grand Convention Center', 'zh-TW' => '宏偉會議中心'],
                'slug' => 'grand-convention-center',
                'description' => ['en' => 'A large public convention center available to all organizers.', 'zh-TW' => '所有組織者都可使用的大型公共會議中心。'],
                'organizer_id' => null, // Public venue
                'address_line_1' => ['en' => '123 Main St', 'zh-TW' => '主要街道123號'],
                'city' => ['en' => 'New York', 'zh-TW' => '紐約'],
                'country_id' => $defaultCountry->id,
                'postal_code' => '10001',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'contact_email' => 'contact@grandconvention.com',
                'seating_capacity' => 2000,
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'City Community Hall', 'zh-TW' => '城市社區大廳'],
                'slug' => 'city-community-hall',
                'description' => ['en' => 'A public community hall for various events.', 'zh-TW' => '舉辦各種活動的公共社區大廳。'],
                'organizer_id' => null, // Public venue
                'address_line_1' => ['en' => '456 Community Dr', 'zh-TW' => '社區大道456號'],
                'city' => ['en' => 'Los Angeles', 'zh-TW' => '洛杉磯'],
                'country_id' => $defaultCountry->id,
                'postal_code' => '90210',
                'latitude' => 34.0522,
                'longitude' => -118.2437,
                'contact_email' => 'info@cityhall.gov',
                'seating_capacity' => 800,
                'is_active' => true,
            ],
            // Organizer-specific venues
            [
                'name' => ['en' => 'Tech Hub Center', 'zh-TW' => '科技中心'],
                'slug' => 'tech-hub-center',
                'description' => ['en' => 'Private venue for tech conferences and events.', 'zh-TW' => '舉辦科技會議和活動的私人場所。'],
                'organizer_id' => $sampleOrganizer->id,
                'address_line_1' => ['en' => '789 Innovation Dr', 'zh-TW' => '創新大道789號'],
                'city' => ['en' => 'San Francisco', 'zh-TW' => '舊金山'],
                'country_id' => $defaultCountry->id,
                'postal_code' => '94107',
                'latitude' => 37.7749,
                'longitude' => -122.4194,
                'contact_email' => 'info@techhub.com',
                'seating_capacity' => 500,
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Private Executive Lounge', 'zh-TW' => '私人行政休息室'],
                'slug' => 'private-executive-lounge',
                'description' => ['en' => 'Exclusive venue for corporate events and meetings.', 'zh-TW' => '專為企業活動和會議而設的專屬場所。'],
                'organizer_id' => $sampleOrganizer->id,
                'address_line_1' => ['en' => '101 Executive Blvd', 'zh-TW' => '行政大道101號'],
                'city' => ['en' => 'Chicago', 'zh-TW' => '芝加哥'],
                'country_id' => $defaultCountry->id,
                'postal_code' => '60601',
                'latitude' => 41.8781,
                'longitude' => -87.6298,
                'contact_email' => 'events@executivelounge.com',
                'seating_capacity' => 150,
                'is_active' => true,
            ],
        ];

        foreach ($venues as $venueData) {
            Venue::firstOrCreate(['slug' => $venueData['slug']], $venueData);
        }

        $publicVenuesCount = collect($venues)->where('organizer_id', null)->count();
        $organizerVenuesCount = collect($venues)->where('organizer_id', '!=', null)->count();

        $this->command->info("Venues seeded successfully:");
        $this->command->info("- {$publicVenuesCount} public venues (available to all organizers)");
        $this->command->info("- {$organizerVenuesCount} organizer-specific venues");
        $this->command->info('Total venues: ' . Venue::count());
    }
}
