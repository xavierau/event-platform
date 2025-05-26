<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            PermissionSeeder::class, // Creates all granular permissions
            // Core Seeders - run in order of dependency
            RolePermissionSeeder::class, // Must run first to create roles
            CountrySeeder::class,
            StateSeeder::class, // If states depend on countries, ensure order
            SiteSettingSeeder::class,

            // User Seeders (New Structure)
            PlatformAdminSeeder::class,
            OrganizerSeeder::class,
            GeneralUserSeeder::class,

            // Application Data Seeders
            CategorySeeder::class,
            TagSeeder::class,
            VenueSeeder::class,
            EventSeeder::class, // Depends on Users (for organizer_id), Categories
            TicketDefinitionSeeder::class,
            EventOccurrenceSeeder::class, // Depends on Events, Venues, TicketDefinitions
            // ... other seeders
        ]);

        // If UserSeeder.php still exists and had other logic, decide if it needs to be deleted or its logic moved.
        // For now, we assume its user creation logic is covered by the new three seeders.
    }
}
