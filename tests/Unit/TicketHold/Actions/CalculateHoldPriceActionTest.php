<?php

namespace Tests\Unit\TicketHold\Actions;

use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\Actions\Purchases\CalculateHoldPriceAction;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateHoldPriceActionTest extends TestCase
{
    use RefreshDatabase;

    private CalculateHoldPriceAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CalculateHoldPriceAction;
    }

    private function createHoldWithAllocation(
        PricingModeEnum $pricingMode,
        ?int $customPrice = null,
        ?int $discountPercentage = null,
        int $ticketPrice = 10000
    ): HoldTicketAllocation {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
            'price' => $ticketPrice,
        ]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        return HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(20)
            ->create([
                'pricing_mode' => $pricingMode,
                'custom_price' => $customPrice,
                'discount_percentage' => $discountPercentage,
            ]);
    }

    public function test_it_returns_original_price_for_original_mode(): void
    {
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::ORIGINAL,
            ticketPrice: 10000
        );

        $result = $this->action->execute($allocation);

        $this->assertEquals(10000, $result['unit_price']);
        $this->assertEquals(10000, $result['original_price']);
        $this->assertEquals(0, $result['savings']);
        $this->assertEquals(PricingModeEnum::ORIGINAL, $result['pricing_mode']);
    }

    public function test_it_returns_custom_price_for_fixed_mode(): void
    {
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::FIXED,
            customPrice: 5000,
            ticketPrice: 10000
        );

        $result = $this->action->execute($allocation);

        $this->assertEquals(5000, $result['unit_price']);
        $this->assertEquals(10000, $result['original_price']);
        $this->assertEquals(5000, $result['savings']);
        $this->assertEquals(PricingModeEnum::FIXED, $result['pricing_mode']);
    }

    public function test_it_calculates_discount_for_percentage_discount_mode(): void
    {
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::PERCENTAGE_DISCOUNT,
            discountPercentage: 25,
            ticketPrice: 10000
        );

        $result = $this->action->execute($allocation);

        // 10000 * (1 - 0.25) = 7500
        $this->assertEquals(7500, $result['unit_price']);
        $this->assertEquals(10000, $result['original_price']);
        $this->assertEquals(2500, $result['savings']);
        $this->assertEquals(PricingModeEnum::PERCENTAGE_DISCOUNT, $result['pricing_mode']);
    }

    public function test_it_returns_zero_for_free_mode(): void
    {
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::FREE,
            ticketPrice: 10000
        );

        $result = $this->action->execute($allocation);

        $this->assertEquals(0, $result['unit_price']);
        $this->assertEquals(10000, $result['original_price']);
        $this->assertEquals(10000, $result['savings']);
        $this->assertEquals(PricingModeEnum::FREE, $result['pricing_mode']);
    }

    public function test_it_uses_original_price_override_when_provided(): void
    {
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::PERCENTAGE_DISCOUNT,
            discountPercentage: 20,
            ticketPrice: 10000
        );

        // Override original price with 15000
        $result = $this->action->execute($allocation, 15000);

        // 15000 * (1 - 0.20) = 12000
        $this->assertEquals(12000, $result['unit_price']);
        $this->assertEquals(15000, $result['original_price']);
        $this->assertEquals(3000, $result['savings']);
    }

    public function test_it_handles_50_percent_discount(): void
    {
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::PERCENTAGE_DISCOUNT,
            discountPercentage: 50,
            ticketPrice: 10000
        );

        $result = $this->action->execute($allocation);

        $this->assertEquals(5000, $result['unit_price']);
        $this->assertEquals(5000, $result['savings']);
    }

    public function test_it_handles_100_percent_discount(): void
    {
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::PERCENTAGE_DISCOUNT,
            discountPercentage: 100,
            ticketPrice: 10000
        );

        $result = $this->action->execute($allocation);

        $this->assertEquals(0, $result['unit_price']);
        $this->assertEquals(10000, $result['savings']);
    }

    public function test_it_handles_zero_percent_discount(): void
    {
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::PERCENTAGE_DISCOUNT,
            discountPercentage: 0,
            ticketPrice: 10000
        );

        $result = $this->action->execute($allocation);

        $this->assertEquals(10000, $result['unit_price']);
        $this->assertEquals(0, $result['savings']);
    }

    public function test_it_rounds_percentage_discount_correctly(): void
    {
        // Test with odd percentage that causes rounding
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::PERCENTAGE_DISCOUNT,
            discountPercentage: 33,
            ticketPrice: 10000
        );

        $result = $this->action->execute($allocation);

        // 10000 * (1 - 0.33) = 6700
        $this->assertEquals(6700, $result['unit_price']);
    }

    public function test_fixed_mode_falls_back_to_original_if_custom_price_null(): void
    {
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::FIXED,
            customPrice: null, // Not set
            ticketPrice: 10000
        );

        $result = $this->action->execute($allocation);

        // Falls back to original price
        $this->assertEquals(10000, $result['unit_price']);
    }

    public function test_percentage_discount_uses_zero_if_null(): void
    {
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::PERCENTAGE_DISCOUNT,
            discountPercentage: null, // Not set
            ticketPrice: 10000
        );

        $result = $this->action->execute($allocation);

        // No discount applied
        $this->assertEquals(10000, $result['unit_price']);
    }

    public function test_savings_is_never_negative(): void
    {
        // Create a fixed price higher than original
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::FIXED,
            customPrice: 15000, // Higher than ticket price
            ticketPrice: 10000
        );

        $result = $this->action->execute($allocation);

        $this->assertEquals(15000, $result['unit_price']);
        $this->assertEquals(10000, $result['original_price']);
        $this->assertEquals(0, $result['savings']); // max(0, 10000-15000) = 0
    }

    public function test_execute_for_link_returns_null_for_non_existing_allocation(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition1 = TicketDefinition::factory()->create(['total_quantity' => 100, 'price' => 5000]);
        $ticketDefinition2 = TicketDefinition::factory()->create(['total_quantity' => 100, 'price' => 8000]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        // Only create allocation for ticketDefinition1
        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition1)
            ->withQuantity(20)
            ->originalPrice()
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();

        // Load relationships
        $link->load('ticketHold.allocations');

        // Try to get price for ticketDefinition2 which has no allocation
        $result = $this->action->executeForLink($link, $ticketDefinition2);

        $this->assertNull($result);
    }

    public function test_execute_for_link_returns_price_for_existing_allocation(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100, 'price' => 10000]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(20)
            ->create([
                'pricing_mode' => PricingModeEnum::PERCENTAGE_DISCOUNT,
                'discount_percentage' => 30,
            ]);

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();

        // Load relationships
        $link->load('ticketHold.allocations');

        $result = $this->action->executeForLink($link, $ticketDefinition);

        $this->assertNotNull($result);
        $this->assertEquals(7000, $result['unit_price']); // 10000 * 0.7
        $this->assertEquals(10000, $result['original_price']);
        $this->assertEquals(3000, $result['savings']);
    }

    public function test_calculate_order_total_with_multiple_items(): void
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
            ->discounted(20)
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDef2)
            ->withQuantity(50)
            ->free()
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();

        // Load relationships
        $link->load('ticketHold.allocations.ticketDefinition');

        $items = [
            ['ticket_definition_id' => $ticketDef1->id, 'quantity' => 2],
            ['ticket_definition_id' => $ticketDef2->id, 'quantity' => 3],
        ];

        $result = $this->action->calculateOrderTotal($link, $items);

        // VIP: 10000 * 0.8 = 8000 * 2 = 16000
        // Regular: 0 * 3 = 0
        // Subtotal: 16000
        $this->assertEquals(16000, $result['subtotal']);

        // VIP savings: (10000 - 8000) * 2 = 4000
        // Regular savings: (5000 - 0) * 3 = 15000
        // Total savings: 19000
        $this->assertEquals(19000, $result['total_savings']);

        $this->assertCount(2, $result['items']);
    }

    public function test_calculate_order_total_skips_non_existing_ticket_definitions(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100, 'price' => 10000]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(20)
            ->originalPrice()
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();

        // Load relationships
        $link->load('ticketHold.allocations.ticketDefinition');

        $items = [
            ['ticket_definition_id' => $ticketDefinition->id, 'quantity' => 2],
            ['ticket_definition_id' => 99999, 'quantity' => 1], // Non-existent
        ];

        $result = $this->action->calculateOrderTotal($link, $items);

        // Only 1 item should be calculated
        $this->assertCount(1, $result['items']);
        $this->assertEquals(20000, $result['subtotal']); // 10000 * 2
    }

    public function test_calculate_order_total_returns_line_totals(): void
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

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(20)
            ->discounted(50)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();

        // Load relationships
        $link->load('ticketHold.allocations.ticketDefinition');

        $items = [
            ['ticket_definition_id' => $ticketDefinition->id, 'quantity' => 4],
        ];

        $result = $this->action->calculateOrderTotal($link, $items);

        $item = $result['items'][0];
        $this->assertEquals($ticketDefinition->id, $item['ticket_definition_id']);
        $this->assertEquals(4, $item['quantity']);
        $this->assertEquals(5000, $item['unit_price']); // 50% of 10000
        $this->assertEquals(10000, $item['original_price']);
        $this->assertEquals(20000, $item['line_total']); // 5000 * 4
        $this->assertEquals(20000, $item['line_savings']); // (10000 - 5000) * 4
        $this->assertEquals('percentage_discount', $item['pricing_mode']);
    }

    public function test_handles_zero_price_ticket(): void
    {
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::ORIGINAL,
            ticketPrice: 0
        );

        $result = $this->action->execute($allocation);

        $this->assertEquals(0, $result['unit_price']);
        $this->assertEquals(0, $result['original_price']);
        $this->assertEquals(0, $result['savings']);
    }

    public function test_handles_very_large_ticket_price(): void
    {
        $allocation = $this->createHoldWithAllocation(
            pricingMode: PricingModeEnum::PERCENTAGE_DISCOUNT,
            discountPercentage: 10,
            ticketPrice: 1000000 // 10,000.00
        );

        $result = $this->action->execute($allocation);

        $this->assertEquals(900000, $result['unit_price']);
        $this->assertEquals(100000, $result['savings']);
    }
}
