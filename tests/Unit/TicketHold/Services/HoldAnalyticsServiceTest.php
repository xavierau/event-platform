<?php

namespace Tests\Unit\TicketHold\Services;

use App\Models\Booking;
use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
use App\Modules\TicketHold\Models\PurchaseLinkPurchase;
use App\Modules\TicketHold\Models\TicketHold;
use App\Modules\TicketHold\Services\HoldAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private HoldAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new HoldAnalyticsService;
    }

    private function createHoldWithData(): array
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'name' => ['en' => 'Test Ticket'],
            'total_quantity' => 100,
            'price' => 10000,
        ]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        $allocation = HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(20)
            ->withPurchased(5)
            ->originalPrice()
            ->create();

        return [
            'occurrence' => $occurrence,
            'organizer' => $organizer,
            'user' => $user,
            'ticketDefinition' => $ticketDefinition,
            'hold' => $hold,
            'allocation' => $allocation,
        ];
    }

    // ========================================
    // Get Hold Analytics Tests
    // ========================================

    public function test_get_hold_analytics_returns_hold_info(): void
    {
        $setup = $this->createHoldWithData();

        $analytics = $this->service->getHoldAnalytics($setup['hold']);

        $this->assertArrayHasKey('hold', $analytics);
        $this->assertEquals($setup['hold']->id, $analytics['hold']['id']);
        $this->assertEquals($setup['hold']->uuid, $analytics['hold']['uuid']);
        $this->assertEquals($setup['hold']->name, $analytics['hold']['name']);
        $this->assertEquals('active', $analytics['hold']['status']);
    }

    public function test_get_hold_analytics_returns_inventory_info(): void
    {
        $setup = $this->createHoldWithData();

        $analytics = $this->service->getHoldAnalytics($setup['hold']);

        $this->assertArrayHasKey('inventory', $analytics);
        $this->assertEquals(20, $analytics['inventory']['total_allocated']);
        $this->assertEquals(5, $analytics['inventory']['total_purchased']);
        $this->assertEquals(15, $analytics['inventory']['total_remaining']);
        $this->assertEquals(25.0, $analytics['inventory']['utilization_rate']); // 5/20 * 100
    }

    public function test_get_hold_analytics_returns_allocations_info(): void
    {
        $setup = $this->createHoldWithData();

        $analytics = $this->service->getHoldAnalytics($setup['hold']);

        $this->assertArrayHasKey('allocations', $analytics);
        $this->assertCount(1, $analytics['allocations']);

        $allocation = $analytics['allocations'][0];
        $this->assertEquals($setup['ticketDefinition']->id, $allocation['ticket_definition_id']);
        $this->assertEquals(20, $allocation['allocated']);
        $this->assertEquals(5, $allocation['purchased']);
        $this->assertEquals(15, $allocation['remaining']);
        $this->assertEquals('original', $allocation['pricing_mode']);
    }

    public function test_get_hold_analytics_returns_link_stats(): void
    {
        $setup = $this->createHoldWithData();

        PurchaseLink::factory()->forHold($setup['hold'])->active()->count(2)->create();
        PurchaseLink::factory()->forHold($setup['hold'])->revoked()->count(1)->create();
        PurchaseLink::factory()->forHold($setup['hold'])->exhausted()->count(1)->create();

        $analytics = $this->service->getHoldAnalytics($setup['hold']);

        $this->assertArrayHasKey('links', $analytics);
        $this->assertEquals(4, $analytics['links']['total']);
        $this->assertEquals(2, $analytics['links']['active']);
        $this->assertEquals(1, $analytics['links']['revoked']);
        $this->assertEquals(1, $analytics['links']['exhausted']);
    }

    public function test_get_hold_analytics_returns_engagement_stats(): void
    {
        $setup = $this->createHoldWithData();

        $link = PurchaseLink::factory()->forHold($setup['hold'])->active()->create();

        // Create accesses
        PurchaseLinkAccess::factory()->forLink($link)->withoutPurchase()->count(10)->create();
        PurchaseLinkAccess::factory()->forLink($link)->withPurchase()->count(3)->create();

        $analytics = $this->service->getHoldAnalytics($setup['hold']);

        $this->assertArrayHasKey('engagement', $analytics);
        $this->assertEquals(13, $analytics['engagement']['total_accesses']);
    }

    // ========================================
    // Get Link Analytics Tests
    // ========================================

    public function test_get_link_analytics_returns_link_info(): void
    {
        $setup = $this->createHoldWithData();
        $link = PurchaseLink::factory()
            ->forHold($setup['hold'])
            ->active()
            ->maxQuantity(10)
            ->create(['quantity_purchased' => 3]);

        $analytics = $this->service->getLinkAnalytics($link);

        $this->assertArrayHasKey('link', $analytics);
        $this->assertEquals($link->id, $analytics['link']['id']);
        $this->assertEquals($link->code, $analytics['link']['code']);
        $this->assertEquals('active', $analytics['link']['status']);
        $this->assertEquals(3, $analytics['link']['quantity_purchased']);
        $this->assertEquals(7, $analytics['link']['remaining_quantity']);
    }

    public function test_get_link_analytics_returns_engagement_metrics(): void
    {
        $setup = $this->createHoldWithData();
        $link = PurchaseLink::factory()->forHold($setup['hold'])->active()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        PurchaseLinkAccess::factory()->forLink($link)->forUser($user1)->count(3)->create();
        PurchaseLinkAccess::factory()->forLink($link)->forUser($user2)->count(2)->create();
        PurchaseLinkAccess::factory()->forLink($link)->anonymous()->count(5)->create();

        $analytics = $this->service->getLinkAnalytics($link);

        $this->assertArrayHasKey('engagement', $analytics);
        $this->assertEquals(10, $analytics['engagement']['total_accesses']);
        $this->assertEquals(2, $analytics['engagement']['unique_visitors']); // Only logged-in users
    }

    public function test_get_link_analytics_calculates_conversion_rate(): void
    {
        $setup = $this->createHoldWithData();
        $link = PurchaseLink::factory()->forHold($setup['hold'])->active()->create();

        PurchaseLinkAccess::factory()->forLink($link)->withoutPurchase()->count(80)->create();
        PurchaseLinkAccess::factory()->forLink($link)->withPurchase()->count(20)->create();

        $analytics = $this->service->getLinkAnalytics($link);

        $this->assertEquals(100, $analytics['engagement']['total_accesses']);
        $this->assertEquals(20, $analytics['engagement']['purchases_from_access']);
        $this->assertEquals(20.0, $analytics['engagement']['conversion_rate']); // 20/100 * 100
    }

    public function test_get_link_analytics_returns_revenue_info(): void
    {
        $setup = $this->createHoldWithData();
        $link = PurchaseLink::factory()->forHold($setup['hold'])->active()->create();
        $transaction = Transaction::factory()->create();
        $booking = Booking::factory()->create([
            'ticket_definition_id' => $setup['ticketDefinition']->id,
            'event_id' => $setup['occurrence']->event_id,
        ]);

        // Create purchase records
        PurchaseLinkPurchase::factory()
            ->forLink($link)
            ->forBooking($booking)
            ->forTransaction($transaction)
            ->withQuantity(2)
            ->create([
                'unit_price' => 8000,
                'original_price' => 10000,
            ]);

        $analytics = $this->service->getLinkAnalytics($link);

        $this->assertArrayHasKey('revenue', $analytics);
        $this->assertEquals(16000, $analytics['revenue']['total_revenue']); // 8000 * 2
        $this->assertEquals(20000, $analytics['revenue']['total_original_value']); // 10000 * 2
        $this->assertEquals(4000, $analytics['revenue']['total_savings_given']); // (10000-8000) * 2
    }

    public function test_get_link_analytics_returns_accesses_by_day(): void
    {
        $setup = $this->createHoldWithData();
        $link = PurchaseLink::factory()->forHold($setup['hold'])->active()->create();

        $today = now();
        $yesterday = now()->subDay();

        PurchaseLinkAccess::factory()
            ->forLink($link)
            ->accessedAt($today)
            ->count(5)
            ->create();

        PurchaseLinkAccess::factory()
            ->forLink($link)
            ->accessedAt($yesterday)
            ->count(3)
            ->create();

        $analytics = $this->service->getLinkAnalytics($link);

        $this->assertArrayHasKey('accesses_by_day', $analytics);
        $this->assertArrayHasKey($today->format('Y-m-d'), $analytics['accesses_by_day']);
        $this->assertArrayHasKey($yesterday->format('Y-m-d'), $analytics['accesses_by_day']);
    }

    public function test_get_link_analytics_returns_recent_accesses(): void
    {
        $setup = $this->createHoldWithData();
        $link = PurchaseLink::factory()->forHold($setup['hold'])->active()->create();

        PurchaseLinkAccess::factory()->forLink($link)->count(15)->create();

        $analytics = $this->service->getLinkAnalytics($link);

        $this->assertArrayHasKey('recent_accesses', $analytics);
        $this->assertCount(10, $analytics['recent_accesses']); // Limited to 10
    }

    // ========================================
    // Get Organizer Analytics Tests
    // ========================================

    public function test_get_organizer_analytics_returns_summary(): void
    {
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100, 'price' => 10000]);

        $hold1 = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        $hold2 = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold1)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(20)
            ->withPurchased(10)
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold2)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(30)
            ->withPurchased(15)
            ->create();

        $analytics = $this->service->getOrganizerAnalytics($organizer->id);

        $this->assertArrayHasKey('summary', $analytics);
        $this->assertEquals(2, $analytics['summary']['total_holds']);
        $this->assertEquals(50, $analytics['summary']['total_allocated_tickets']); // 20 + 30
        $this->assertEquals(25, $analytics['summary']['total_purchased_tickets']); // 10 + 15
        $this->assertEquals(50.0, $analytics['summary']['overall_utilization_rate']); // 25/50 * 100
    }

    public function test_get_organizer_analytics_returns_holds_by_status(): void
    {
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $user = User::factory()->create();

        TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->count(3)
            ->create();

        TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->released()
            ->count(2)
            ->create();

        TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->expired()
            ->count(1)
            ->create();

        $analytics = $this->service->getOrganizerAnalytics($organizer->id);

        $this->assertArrayHasKey('holds_by_status', $analytics);
        $this->assertEquals(3, $analytics['holds_by_status']['active']);
        $this->assertEquals(2, $analytics['holds_by_status']['released']);
        $this->assertEquals(1, $analytics['holds_by_status']['expired']);
    }

    public function test_get_organizer_analytics_filters_by_date_range(): void
    {
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $user = User::factory()->create();

        // Create hold within range
        TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create(['created_at' => now()->subDays(5)]);

        // Create hold outside range
        TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create(['created_at' => now()->subDays(60)]);

        $analytics = $this->service->getOrganizerAnalytics(
            $organizer->id,
            now()->subDays(30),
            now()
        );

        $this->assertEquals(1, $analytics['summary']['total_holds']);
    }

    // ========================================
    // Top Performing Links Tests
    // ========================================

    public function test_get_top_performing_links_returns_sorted_by_conversion(): void
    {
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(100)
            ->create();

        // High conversion link
        $highConversionLink = PurchaseLink::factory()->forHold($hold)->active()->create();
        PurchaseLinkAccess::factory()->forLink($highConversionLink)->withPurchase()->count(80)->create();
        PurchaseLinkAccess::factory()->forLink($highConversionLink)->withoutPurchase()->count(20)->create();

        // Low conversion link
        $lowConversionLink = PurchaseLink::factory()->forHold($hold)->active()->create();
        PurchaseLinkAccess::factory()->forLink($lowConversionLink)->withPurchase()->count(10)->create();
        PurchaseLinkAccess::factory()->forLink($lowConversionLink)->withoutPurchase()->count(90)->create();

        $result = $this->service->getTopPerformingLinks($organizer->id, 10);

        $this->assertCount(2, $result);
        $this->assertEquals($highConversionLink->id, $result[0]['link']->id);
        $this->assertEquals(80.0, $result[0]['conversion_rate']); // 80/100
        $this->assertEquals($lowConversionLink->id, $result[1]['link']->id);
        $this->assertEquals(10.0, $result[1]['conversion_rate']); // 10/100
    }

    public function test_get_top_performing_links_respects_limit(): void
    {
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(100)
            ->create();

        // Create 10 links, each with at least one access (required for top performing calculation)
        $links = PurchaseLink::factory()->forHold($hold)->active()->count(10)->create();
        foreach ($links as $link) {
            PurchaseLinkAccess::factory()->forLink($link)->count(1)->create();
        }

        $result = $this->service->getTopPerformingLinks($organizer->id, 5);

        $this->assertCount(5, $result);
    }

    public function test_get_top_performing_links_excludes_links_with_no_accesses(): void
    {
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(100)
            ->create();

        // Create 5 links without accesses (should be excluded)
        PurchaseLink::factory()->forHold($hold)->active()->count(5)->create();

        // Create 2 links with accesses (should be included)
        $linksWithAccesses = PurchaseLink::factory()->forHold($hold)->active()->count(2)->create();
        foreach ($linksWithAccesses as $link) {
            PurchaseLinkAccess::factory()->forLink($link)->count(3)->create();
        }

        $result = $this->service->getTopPerformingLinks($organizer->id, 10);

        // Only links with accesses should be returned
        $this->assertCount(2, $result);
    }

    // ========================================
    // Revenue By Ticket Type Tests
    // ========================================

    public function test_get_revenue_by_ticket_type_returns_breakdown(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDef1 = TicketDefinition::factory()->create([
            'name' => ['en' => 'VIP Ticket'],
            'total_quantity' => 100,
            'price' => 10000,
        ]);
        $ticketDef2 = TicketDefinition::factory()->create([
            'name' => ['en' => 'Regular Ticket'],
            'total_quantity' => 100,
            'price' => 5000,
        ]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDef1)
            ->withQuantity(20)
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDef2)
            ->withQuantity(30)
            ->create();

        $link = PurchaseLink::factory()->forHold($hold)->active()->create();
        $transaction = Transaction::factory()->create();

        // VIP purchases
        $vipBooking = Booking::factory()->create([
            'ticket_definition_id' => $ticketDef1->id,
            'event_id' => $occurrence->event_id,
        ]);
        PurchaseLinkPurchase::factory()
            ->forLink($link)
            ->forBooking($vipBooking)
            ->forTransaction($transaction)
            ->withQuantity(2)
            ->create([
                'unit_price' => 8000,
                'original_price' => 10000,
            ]);

        // Regular purchases
        $regularBooking = Booking::factory()->create([
            'ticket_definition_id' => $ticketDef2->id,
            'event_id' => $occurrence->event_id,
        ]);
        PurchaseLinkPurchase::factory()
            ->forLink($link)
            ->forBooking($regularBooking)
            ->forTransaction($transaction)
            ->withQuantity(3)
            ->create([
                'unit_price' => 4000,
                'original_price' => 5000,
            ]);

        $result = $this->service->getRevenueByTicketType($hold);

        $this->assertCount(2, $result);
    }

    // ========================================
    // Access Patterns Tests
    // ========================================

    public function test_get_access_patterns_returns_hourly_distribution(): void
    {
        $setup = $this->createHoldWithData();
        $link = PurchaseLink::factory()->forHold($setup['hold'])->active()->create();

        // Create accesses at different hours
        PurchaseLinkAccess::factory()
            ->forLink($link)
            ->accessedAt(now()->setHour(10))
            ->count(5)
            ->create();

        PurchaseLinkAccess::factory()
            ->forLink($link)
            ->accessedAt(now()->setHour(14))
            ->count(10)
            ->create();

        $patterns = $this->service->getAccessPatterns($link);

        $this->assertArrayHasKey('hourly_distribution', $patterns);
        $this->assertCount(24, $patterns['hourly_distribution']); // All 24 hours
        $this->assertEquals('14', $patterns['peak_hour']); // Hour 14 has most accesses
    }

    public function test_get_access_patterns_returns_day_of_week_distribution(): void
    {
        $setup = $this->createHoldWithData();
        $link = PurchaseLink::factory()->forHold($setup['hold'])->active()->create();

        // Create accesses on different days
        PurchaseLinkAccess::factory()
            ->forLink($link)
            ->accessedAt(Carbon::parse('Monday'))
            ->count(10)
            ->create();

        PurchaseLinkAccess::factory()
            ->forLink($link)
            ->accessedAt(Carbon::parse('Friday'))
            ->count(20)
            ->create();

        $patterns = $this->service->getAccessPatterns($link);

        $this->assertArrayHasKey('day_of_week_distribution', $patterns);
        $this->assertEquals('Friday', $patterns['peak_day']);
    }

    // ========================================
    // Referrer Analysis Tests
    // ========================================

    public function test_get_referrer_analysis_groups_by_domain(): void
    {
        $setup = $this->createHoldWithData();
        $link = PurchaseLink::factory()->forHold($setup['hold'])->active()->create();

        PurchaseLinkAccess::factory()
            ->forLink($link)
            ->fromReferer('https://google.com/search?q=test')
            ->count(10)
            ->create();

        PurchaseLinkAccess::factory()
            ->forLink($link)
            ->fromReferer('https://facebook.com/share/123')
            ->withPurchase()
            ->count(5)
            ->create();

        $referrers = $this->service->getReferrerAnalysis($link);

        $this->assertCount(2, $referrers);

        $googleReferer = $referrers->firstWhere('domain', 'google.com');
        $this->assertEquals(10, $googleReferer['access_count']);

        $facebookReferer = $referrers->firstWhere('domain', 'facebook.com');
        $this->assertEquals(5, $facebookReferer['access_count']);
        $this->assertEquals(100.0, $facebookReferer['conversion_rate']); // All 5 resulted in purchase
    }

    public function test_get_referrer_analysis_excludes_null_referers(): void
    {
        $setup = $this->createHoldWithData();
        $link = PurchaseLink::factory()->forHold($setup['hold'])->active()->create();

        PurchaseLinkAccess::factory()
            ->forLink($link)
            ->create(['referer' => null]);

        PurchaseLinkAccess::factory()
            ->forLink($link)
            ->fromReferer('https://example.com')
            ->count(5)
            ->create();

        $referrers = $this->service->getReferrerAnalysis($link);

        $this->assertCount(1, $referrers);
        $this->assertEquals('example.com', $referrers->first()['domain']);
    }
}
