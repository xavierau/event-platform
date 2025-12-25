<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
use App\Modules\TicketHold\Models\PurchaseLinkPurchase;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Database\Seeder;

class TicketHoldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data for idempotency
        $this->clearExistingData();

        // Ensure we have required dependencies
        $occurrence = $this->getOrCreateEventOccurrence();
        $organizer = $this->getOrCreateOrganizer();
        $users = $this->getOrCreateUsers();
        $ticketDefinitions = $this->getOrCreateTicketDefinitions($occurrence);

        if (! $occurrence || ! $organizer || $users->isEmpty() || $ticketDefinitions->isEmpty()) {
            $this->command->warn('Unable to create TicketHold seed data due to missing dependencies.');

            return;
        }

        $adminUser = $users->first();

        // Scenario 1: Active Hold with Multiple Links
        $this->createActiveHoldWithMultipleLinks(
            $occurrence,
            $organizer,
            $adminUser,
            $users,
            $ticketDefinitions
        );

        // Scenario 2: Exhausted Hold
        $this->createExhaustedHold(
            $occurrence,
            $organizer,
            $adminUser,
            $users,
            $ticketDefinitions
        );

        // Scenario 3: Released Hold
        $this->createReleasedHold(
            $occurrence,
            $organizer,
            $adminUser,
            $users,
            $ticketDefinitions
        );

        // Scenario 4: Hold with Analytics Data
        $this->createHoldWithAnalyticsData(
            $occurrence,
            $organizer,
            $adminUser,
            $users,
            $ticketDefinitions
        );

        $this->command->info('TicketHold sample data seeded successfully!');
    }

    /**
     * Clear existing ticket hold data for idempotency.
     */
    private function clearExistingData(): void
    {
        // Delete in order of dependencies (children first)
        PurchaseLinkPurchase::query()->delete();
        PurchaseLinkAccess::query()->delete();
        PurchaseLink::withTrashed()->forceDelete();
        HoldTicketAllocation::query()->delete();
        TicketHold::withTrashed()->forceDelete();

        $this->command->info('Cleared existing TicketHold data.');
    }

    /**
     * Get or create an event occurrence for seeding.
     */
    private function getOrCreateEventOccurrence(): ?EventOccurrence
    {
        $occurrence = EventOccurrence::with('ticketDefinitions')->first();

        if (! $occurrence) {
            $this->command->warn('No EventOccurrence found. Please run EventOccurrenceSeeder first.');
        }

        return $occurrence;
    }

    /**
     * Get or create an organizer for seeding.
     */
    private function getOrCreateOrganizer(): ?Organizer
    {
        $organizer = Organizer::first();

        if (! $organizer) {
            $this->command->warn('No Organizer found. Please run OrganizerSeeder first.');
        }

        return $organizer;
    }

    /**
     * Get or create users for seeding.
     */
    private function getOrCreateUsers()
    {
        $users = User::take(5)->get();

        if ($users->count() < 5) {
            $this->command->warn('Less than 5 users found. Some scenarios may have limited data.');
        }

        return $users;
    }

    /**
     * Get or create ticket definitions for the occurrence.
     */
    private function getOrCreateTicketDefinitions(EventOccurrence $occurrence)
    {
        $definitions = $occurrence->ticketDefinitions;

        if ($definitions->isEmpty()) {
            $definitions = TicketDefinition::take(3)->get();
        }

        if ($definitions->isEmpty()) {
            $this->command->warn('No TicketDefinitions found. Please run TicketDefinitionSeeder first.');
        }

        return $definitions;
    }

    /**
     * Scenario 1: Active Hold with Multiple Links
     *
     * - 1 Active ticket hold for an event occurrence
     * - 3 ticket allocations (VIP, Standard, Early Bird) with different pricing modes
     * - 5 purchase links (2 anonymous, 2 user-assigned, 1 expired)
     * - Sample access records and purchases
     */
    private function createActiveHoldWithMultipleLinks(
        EventOccurrence $occurrence,
        Organizer $organizer,
        User $adminUser,
        $users,
        $ticketDefinitions
    ): void {
        $this->command->info('Creating Scenario 1: Active Hold with Multiple Links...');

        // Create the active hold
        $hold = TicketHold::factory()
            ->active()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($adminUser)
            ->create([
                'name' => 'VIP Corporate Event Hold',
                'description' => 'Reserved tickets for corporate sponsors and VIP guests',
                'internal_notes' => 'Contact John for sponsor allocations',
                'expires_at' => now()->addMonth(),
            ]);

        // Create 3 allocations with different pricing modes
        $allocations = [];
        $pricingModes = [
            ['mode' => 'originalPrice', 'name' => 'VIP Tickets - Full Price'],
            ['mode' => 'discounted', 'name' => 'Standard Tickets - 20% Off', 'discount' => 20],
            ['mode' => 'free', 'name' => 'Complimentary Guest Tickets'],
        ];

        foreach ($ticketDefinitions->take(3) as $index => $ticketDef) {
            $factory = HoldTicketAllocation::factory()
                ->forHold($hold)
                ->forTicketDefinition($ticketDef)
                ->withQuantity(25);

            $modeConfig = $pricingModes[$index] ?? $pricingModes[0];

            if ($modeConfig['mode'] === 'discounted') {
                $factory = $factory->discounted($modeConfig['discount']);
            } elseif ($modeConfig['mode'] === 'free') {
                $factory = $factory->free();
            } else {
                $factory = $factory->originalPrice();
            }

            $allocations[] = $factory->create();
        }

        // Create 5 purchase links
        // Link 1: Anonymous with fixed quantity
        $link1 = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->anonymous()
            ->fixedQuantity(5)
            ->create([
                'name' => 'Sponsor Package A',
                'notes' => 'Reserved for ABC Corporation',
                'metadata' => ['sponsor' => 'ABC Corp', 'package' => 'Gold'],
            ]);

        // Link 2: Anonymous with unlimited quantity (from pool)
        $link2 = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->anonymous()
            ->unlimited()
            ->neverExpires()
            ->create([
                'name' => 'General VIP Access',
                'notes' => 'Open link for VIP guest purchases',
                'metadata' => ['source' => 'vip_portal'],
            ]);

        // Links 3 & 4: User-assigned links
        $assignedUsers = $users->slice(1, 2);
        foreach ($assignedUsers as $i => $user) {
            $link = PurchaseLink::factory()
                ->forHold($hold)
                ->active()
                ->withUser($user)
                ->maxQuantity(3)
                ->create([
                    'name' => "Personal Link - {$user->name}",
                    'notes' => 'Assigned to registered VIP member',
                ]);

            // Create access records
            PurchaseLinkAccess::factory()
                ->forLink($link)
                ->forUser($user)
                ->count(rand(1, 3))
                ->create();

            // Create a purchase for one of the user links
            if ($i === 0) {
                $this->createSamplePurchase($link, $user, 2);
            }
        }

        // Link 5: Expired link
        PurchaseLink::factory()
            ->forHold($hold)
            ->expired()
            ->anonymous()
            ->maxQuantity(10)
            ->create([
                'name' => 'Early Bird Expired',
                'notes' => 'Early bird offer that has ended',
                'expires_at' => now()->subWeek(),
            ]);

        // Create access records for anonymous links
        PurchaseLinkAccess::factory()
            ->forLink($link1)
            ->anonymous()
            ->count(5)
            ->create();

        PurchaseLinkAccess::factory()
            ->forLink($link2)
            ->anonymous()
            ->count(10)
            ->create();

        // Create a purchase for link1
        $this->createSamplePurchase($link1, null, 3);

        $this->command->info('  - Created active hold with 3 allocations and 5 links');
    }

    /**
     * Scenario 2: Exhausted Hold
     *
     * - 1 Exhausted ticket hold
     * - 2 allocations fully purchased
     * - 3 exhausted purchase links
     * - Purchase records
     */
    private function createExhaustedHold(
        EventOccurrence $occurrence,
        Organizer $organizer,
        User $adminUser,
        $users,
        $ticketDefinitions
    ): void {
        $this->command->info('Creating Scenario 2: Exhausted Hold...');

        // Create the exhausted hold
        $hold = TicketHold::factory()
            ->exhausted()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($adminUser)
            ->create([
                'name' => 'Flash Sale Hold - SOLD OUT',
                'description' => 'Limited time flash sale tickets - completely sold out',
                'internal_notes' => 'All tickets sold within 2 hours',
            ]);

        // Create 2 fully purchased allocations
        foreach ($ticketDefinitions->take(2) as $ticketDef) {
            $quantity = rand(10, 20);
            HoldTicketAllocation::factory()
                ->forHold($hold)
                ->forTicketDefinition($ticketDef)
                ->fixedPrice(rand(2000, 5000))
                ->withQuantity($quantity)
                ->withPurchased($quantity) // Fully purchased
                ->create();
        }

        // Create 3 exhausted purchase links
        for ($i = 0; $i < 3; $i++) {
            $limit = rand(5, 10);
            $link = PurchaseLink::factory()
                ->forHold($hold)
                ->exhausted()
                ->anonymous()
                ->create([
                    'name' => 'Flash Sale Link '.($i + 1),
                    'quantity_mode' => QuantityModeEnum::MAXIMUM,
                    'quantity_limit' => $limit,
                    'quantity_purchased' => $limit,
                ]);

            // Create access and purchase records
            $accessCount = rand(5, 15);
            PurchaseLinkAccess::factory()
                ->forLink($link)
                ->anonymous()
                ->count($accessCount)
                ->create();

            // Create purchase records
            $purchaseCount = rand(2, 4);
            for ($j = 0; $j < $purchaseCount; $j++) {
                $purchaseUser = $users->random();
                $this->createSamplePurchase($link, $purchaseUser, rand(1, 3));
            }
        }

        $this->command->info('  - Created exhausted hold with 2 allocations and 3 exhausted links');
    }

    /**
     * Scenario 3: Released Hold
     *
     * - 1 Released ticket hold
     * - Allocations that were partially used
     * - Revoked purchase links
     */
    private function createReleasedHold(
        EventOccurrence $occurrence,
        Organizer $organizer,
        User $adminUser,
        $users,
        $ticketDefinitions
    ): void {
        $this->command->info('Creating Scenario 3: Released Hold...');

        // Create the released hold
        $hold = TicketHold::factory()
            ->released()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($adminUser)
            ->create([
                'name' => 'Cancelled Partnership Hold',
                'description' => 'Partnership tickets released back to public inventory',
                'internal_notes' => 'Partnership with XYZ Corp cancelled',
                'released_at' => now()->subDays(3),
                'released_by' => $adminUser->id,
            ]);

        // Create partially used allocations
        foreach ($ticketDefinitions->take(2) as $ticketDef) {
            $allocated = rand(15, 30);
            $purchased = rand(3, 8);
            HoldTicketAllocation::factory()
                ->forHold($hold)
                ->forTicketDefinition($ticketDef)
                ->discounted(15)
                ->withQuantity($allocated)
                ->withPurchased($purchased)
                ->create();
        }

        // Create revoked links
        for ($i = 0; $i < 2; $i++) {
            $link = PurchaseLink::factory()
                ->forHold($hold)
                ->revoked()
                ->create([
                    'name' => 'Partnership Link '.($i + 1),
                    'notes' => 'Revoked due to partnership cancellation',
                    'revoked_at' => now()->subDays(3),
                    'revoked_by' => $adminUser->id,
                ]);

            // Some access before revocation
            PurchaseLinkAccess::factory()
                ->forLink($link)
                ->count(rand(2, 5))
                ->create([
                    'accessed_at' => now()->subDays(rand(4, 10)),
                ]);
        }

        $this->command->info('  - Created released hold with partially used allocations and revoked links');
    }

    /**
     * Scenario 4: Hold with Analytics Data
     *
     * - 1 Active hold with extensive access tracking
     * - Multiple access records (some resulted in purchase, some didn't)
     * - Good data for testing analytics
     */
    private function createHoldWithAnalyticsData(
        EventOccurrence $occurrence,
        Organizer $organizer,
        User $adminUser,
        $users,
        $ticketDefinitions
    ): void {
        $this->command->info('Creating Scenario 4: Hold with Analytics Data...');

        // Create an active hold for analytics
        $hold = TicketHold::factory()
            ->active()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($adminUser)
            ->create([
                'name' => 'Marketing Campaign Hold',
                'description' => 'Tickets reserved for marketing campaign tracking',
                'internal_notes' => 'Tracking conversion rates across different channels',
                'expires_at' => now()->addWeeks(2),
            ]);

        // Create allocations
        foreach ($ticketDefinitions->take(2) as $ticketDef) {
            HoldTicketAllocation::factory()
                ->forHold($hold)
                ->forTicketDefinition($ticketDef)
                ->originalPrice()
                ->withQuantity(50)
                ->withPurchased(rand(5, 15))
                ->create();
        }

        // Create links with different referrer sources
        $sources = [
            ['name' => 'Email Newsletter', 'referer' => 'https://newsletter.example.com/campaign1'],
            ['name' => 'Social Media - Facebook', 'referer' => 'https://facebook.com/events/123'],
            ['name' => 'Social Media - Instagram', 'referer' => 'https://instagram.com/p/abc123'],
            ['name' => 'Partner Website', 'referer' => 'https://partner-site.com/tickets'],
        ];

        foreach ($sources as $source) {
            $link = PurchaseLink::factory()
                ->forHold($hold)
                ->active()
                ->anonymous()
                ->unlimited()
                ->create([
                    'name' => $source['name'],
                    'metadata' => ['source' => $source['name'], 'campaign' => 'spring_2025'],
                ]);

            // Create extensive access records with varied conversion rates
            $totalAccesses = rand(20, 50);
            $conversionRate = rand(10, 40) / 100; // 10-40% conversion rate
            $purchaseCount = (int) ($totalAccesses * $conversionRate);
            $noPurchaseCount = $totalAccesses - $purchaseCount;

            // Accesses that resulted in purchase
            for ($i = 0; $i < $purchaseCount; $i++) {
                $accessUser = $users->random();
                $accessTime = now()->subDays(rand(1, 14))->subHours(rand(0, 23));
                $isAnonymousAccess = rand(0, 1) === 0;

                $accessFactory = PurchaseLinkAccess::factory()
                    ->forLink($link)
                    ->withPurchase();

                if ($isAnonymousAccess) {
                    $accessFactory = $accessFactory->anonymous();
                } else {
                    $accessFactory = $accessFactory->forUser($accessUser);
                }

                $access = $accessFactory->create([
                    'accessed_at' => $accessTime,
                    'referer' => $source['referer'],
                ]);

                // Create corresponding purchase
                $this->createSamplePurchaseWithAccess(
                    $link,
                    $access,
                    $isAnonymousAccess ? null : $accessUser,
                    rand(1, 3)
                );
            }

            // Accesses that did not result in purchase
            PurchaseLinkAccess::factory()
                ->forLink($link)
                ->withoutPurchase()
                ->count($noPurchaseCount)
                ->create([
                    'referer' => $source['referer'],
                ]);
        }

        $this->command->info('  - Created analytics hold with 4 tracked campaign links and extensive access data');
    }

    /**
     * Create a sample purchase record for a link.
     */
    private function createSamplePurchase(
        PurchaseLink $link,
        ?User $user,
        int $quantity
    ): void {
        // Get existing booking and transaction to avoid creating extra models
        $booking = $this->getOrCreateBooking();
        $transaction = Transaction::first();

        PurchaseLinkPurchase::factory()
            ->forLink($link)
            ->forBooking($booking)
            ->withQuantity($quantity)
            ->when($user, fn ($f) => $f->byUser($user), fn ($f) => $f->anonymous())
            ->when($transaction, fn ($f) => $f->forTransaction($transaction))
            ->create([
                'access_id' => null, // Explicitly set to null to prevent factory from creating nested access
            ]);
    }

    /**
     * Create a sample purchase record linked to an access record.
     */
    private function createSamplePurchaseWithAccess(
        PurchaseLink $link,
        PurchaseLinkAccess $access,
        ?User $user,
        int $quantity
    ): void {
        // Get existing booking and transaction to avoid creating extra models
        $booking = $this->getOrCreateBooking();
        $transaction = Transaction::first();

        PurchaseLinkPurchase::factory()
            ->forLink($link)
            ->forBooking($booking)
            ->fromAccess($access)
            ->withQuantity($quantity)
            ->when($user, fn ($f) => $f->byUser($user), fn ($f) => $f->anonymous())
            ->when($transaction, fn ($f) => $f->forTransaction($transaction))
            ->create();
    }

    /**
     * Get or create a booking for purchase records.
     * Caches the booking to avoid creating multiple bookings.
     */
    private function getOrCreateBooking(): Booking
    {
        static $cachedBooking = null;

        if ($cachedBooking === null) {
            $cachedBooking = Booking::first();

            if ($cachedBooking === null) {
                // Create a minimal booking only if none exist
                $transaction = Transaction::first() ?? Transaction::factory()->create();
                $event = \App\Models\Event::first();
                $ticketDef = TicketDefinition::first();

                if ($event && $ticketDef) {
                    $cachedBooking = Booking::factory()
                        ->for($transaction)
                        ->for($event, 'event')
                        ->for($ticketDef, 'ticketDefinition')
                        ->create();
                } else {
                    // Fallback: let factory create everything
                    $cachedBooking = Booking::factory()->create();
                }
            }
        }

        return $cachedBooking;
    }
}
