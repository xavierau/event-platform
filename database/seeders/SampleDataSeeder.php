<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder creates a comprehensive development/testing environment
     * with realistic data for the Event Platform.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting comprehensive sample data seeding...');

        // Phase 1: Core System Data
        $this->command->info('📍 Phase 1: Setting up core system data...');
        $this->call([
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            CountrySeeder::class,
            StateSeeder::class,
            SiteSettingSeeder::class,
        ]);

        // Phase 2: User Management
        $this->command->info('👥 Phase 2: Creating users and roles...');
        $this->call([
            PlatformAdminSeeder::class,
            GeneralUserSeeder::class,
        ]);

        // Phase 3: Organizer System
        $this->command->info('🏢 Phase 3: Setting up organizer ecosystem...');
        $this->call([
            OrganizerSeeder::class, // Creates organizers with team structures
        ]);

        // Phase 4: Location and Venue Setup
        $this->command->info('🏢 Phase 4: Creating venues and locations...');
        $this->call([
            VenueSeeder::class, // Creates public and organizer-specific venues
        ]);

        // Phase 5: Event Categories and Tags
        $this->command->info('🏷️ Phase 5: Setting up categories and tags...');
        $this->call([
            CategorySeeder::class,
            TagSeeder::class,
        ]);

        // Phase 6: Events and Event System
        $this->command->info('🎉 Phase 6: Creating events and event system...');
        $this->call([
            EventSeeder::class, // Uses organizer entities
            EventCategorySeeder::class,
            EventOccurrenceSeeder::class,
            TicketDefinitionSeeder::class,
        ]);

        // Phase 7: Booking and Transaction System
        $this->command->info('💳 Phase 7: Setting up booking and payment system...');
        $this->call([
            TransactionSeeder::class,
            BookingSeeder::class,
            PromotionSeeder::class,
        ]);

        // Phase 8: Content Management
        $this->command->info('📝 Phase 8: Creating content management data...');
        $this->call([
            CmsPageSeeder::class,
            ContactSubmissionSeeder::class,
        ]);

        // Phase 9: Additional Features (Optional)
        $this->command->info('✨ Phase 9: Setting up additional features...');
        // Note: Wallet and Membership seeders would go here when implemented

        $this->command->info('✅ Sample data seeding completed successfully!');
        $this->displaySummary();
    }

    /**
     * Display a summary of the seeded data.
     */
    private function displaySummary(): void
    {
        $this->command->info('');
        $this->command->info('📊 SEEDING SUMMARY:');
        $this->command->info('==================');

        // Count key entities
        $organizers = \App\Models\Organizer::count();
        $organizerUsers = DB::table('organizer_users')->count();
        $events = \App\Models\Event::count();
        $venues = \App\Models\Venue::count();
        $publicVenues = \App\Models\Venue::whereNull('organizer_id')->count();
        $privateVenues = \App\Models\Venue::whereNotNull('organizer_id')->count();
        $users = \App\Models\User::count();
        $categories = \App\Models\Category::count();
        $bookings = \App\Models\Booking::count();
        $pages = class_exists('\App\Modules\CMS\Models\CmsPage') ? \App\Modules\CMS\Models\CmsPage::count() : 0;

        $this->command->info("👥 Users: {$users}");
        $this->command->info("🏢 Organizers: {$organizers}");
        $this->command->info("🤝 Organizer memberships: {$organizerUsers}");
        $this->command->info("🎉 Events: {$events}");
        $this->command->info("🏢 Total venues: {$venues} ({$publicVenues} public, {$privateVenues} organizer-specific)");
        $this->command->info("🏷️ Categories: {$categories}");
        $this->command->info("🎫 Bookings: {$bookings}");
        $this->command->info("📝 CMS Pages: {$pages}");

        $this->command->info('');
        $this->command->info('🎯 Sample data is ready for development and testing!');
        $this->command->info('📚 Check the following for sample accounts:');
        $this->command->info('   - Admin accounts: john@eventcorp.com, alex@musicfestgroup.com');
        $this->command->info('   - Staff accounts: sarah@eventcorp.com, michael@eventcorp.com');
        $this->command->info('   - Community organizer: maria@communityconnect.org');
        $this->command->info('   - All passwords: "password"');
    }
}
