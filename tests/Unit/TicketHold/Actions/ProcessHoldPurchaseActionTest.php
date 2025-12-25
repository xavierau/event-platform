<?php

namespace Tests\Unit\TicketHold\Actions;

use App\Enums\BookingStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Booking;
use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\TicketHold\Actions\Purchases\CalculateHoldPriceAction;
use App\Modules\TicketHold\Actions\Purchases\ProcessHoldPurchaseAction;
use App\Modules\TicketHold\DTOs\HoldPurchaseItemData;
use App\Modules\TicketHold\DTOs\HoldPurchaseRequestData;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use App\Modules\TicketHold\Exceptions\HoldNotActiveException;
use App\Modules\TicketHold\Exceptions\InsufficientHoldInventoryException;
use App\Modules\TicketHold\Exceptions\LinkNotUsableException;
use App\Modules\TicketHold\Exceptions\UserNotAuthorizedForLinkException;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
use App\Modules\TicketHold\Models\PurchaseLinkPurchase;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessHoldPurchaseActionTest extends TestCase
{
    use RefreshDatabase;

    private ProcessHoldPurchaseAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $calculatePriceAction = app(CalculateHoldPriceAction::class);
        $this->action = new ProcessHoldPurchaseAction($calculatePriceAction);
    }

    private function createSetup(
        PricingModeEnum $pricingMode = PricingModeEnum::ORIGINAL,
        ?int $customPrice = null,
        ?int $discountPercentage = null,
        int $allocatedQuantity = 20,
        int $ticketPrice = 10000
    ): array {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
            'price' => $ticketPrice,
        ]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        $allocation = HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity($allocatedQuantity)
            ->create([
                'pricing_mode' => $pricingMode,
                'custom_price' => $customPrice,
                'discount_percentage' => $discountPercentage,
            ]);

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->unlimited()
            ->create();

        return [
            'occurrence' => $occurrence,
            'organizer' => $organizer,
            'creator' => $creator,
            'ticketDefinition' => $ticketDefinition,
            'hold' => $hold,
            'allocation' => $allocation,
            'link' => $link,
        ];
    }

    public function test_it_creates_booking_and_tickets(): void
    {
        $setup = $this->createSetup();

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 2
                ),
            ]
        );

        $result = $this->action->execute($requestData);

        $this->assertArrayHasKey('transaction', $result);
        $this->assertArrayHasKey('bookings', $result);
        $this->assertArrayHasKey('purchases', $result);
        $this->assertArrayHasKey('totals', $result);

        $this->assertInstanceOf(Transaction::class, $result['transaction']);
        $this->assertCount(2, $result['bookings']); // 2 bookings for quantity of 2
        $this->assertCount(2, $result['purchases']); // 2 purchase records

        foreach ($result['bookings'] as $booking) {
            $this->assertInstanceOf(Booking::class, $booking);
            $this->assertEquals(BookingStatusEnum::CONFIRMED, $booking->status);
            $this->assertNotNull($booking->qr_code_identifier);
        }
    }

    public function test_it_updates_purchased_quantities(): void
    {
        $setup = $this->createSetup(allocatedQuantity: 20);

        $this->assertEquals(0, $setup['allocation']->purchased_quantity);

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 5
                ),
            ]
        );

        $this->action->execute($requestData);

        $setup['allocation']->refresh();
        $this->assertEquals(5, $setup['allocation']->purchased_quantity);
    }

    public function test_it_records_purchase_in_purchase_link_purchases(): void
    {
        $setup = $this->createSetup();
        $user = User::factory()->create();

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 1
                ),
            ]
        );

        $result = $this->action->execute($requestData, $user);

        $purchase = $result['purchases'][0];
        $this->assertInstanceOf(PurchaseLinkPurchase::class, $purchase);
        $this->assertEquals($setup['link']->id, $purchase->purchase_link_id);
        $this->assertEquals($user->id, $purchase->user_id);
        $this->assertEquals(1, $purchase->quantity);
    }

    public function test_it_updates_access_record_if_provided(): void
    {
        $setup = $this->createSetup();
        $user = User::factory()->create();

        $access = PurchaseLinkAccess::factory()
            ->forLink($setup['link'])
            ->withoutPurchase()
            ->create();

        $this->assertFalse($access->resulted_in_purchase);

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 1
                ),
            ]
        );

        $this->action->execute($requestData, $user, $access);

        $access->refresh();
        $this->assertTrue($access->resulted_in_purchase);
    }

    public function test_it_throws_exception_on_insufficient_inventory(): void
    {
        $setup = $this->createSetup(allocatedQuantity: 5);

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 10 // More than allocated
                ),
            ]
        );

        $this->expectException(InsufficientHoldInventoryException::class);

        $this->action->execute($requestData);
    }

    public function test_it_throws_exception_for_revoked_link(): void
    {
        $setup = $this->createSetup();

        // Revoke the link
        $setup['link']->update([
            'status' => LinkStatusEnum::REVOKED,
            'revoked_at' => now(),
        ]);

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 1
                ),
            ]
        );

        $this->expectException(LinkNotUsableException::class);

        $this->action->execute($requestData);
    }

    public function test_it_throws_exception_for_expired_link(): void
    {
        $setup = $this->createSetup();

        // Expire the link
        $setup['link']->update([
            'status' => LinkStatusEnum::ACTIVE,
            'expires_at' => now()->subDay(),
        ]);

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 1
                ),
            ]
        );

        $this->expectException(LinkNotUsableException::class);

        $this->action->execute($requestData);
    }

    public function test_it_throws_exception_for_inactive_hold(): void
    {
        $setup = $this->createSetup();

        // Release the hold
        $setup['hold']->release(User::factory()->create());

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 1
                ),
            ]
        );

        $this->expectException(HoldNotActiveException::class);

        $this->action->execute($requestData);
    }

    public function test_it_throws_exception_for_unauthorized_user(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $assignedUser = User::factory()->create();
        $unauthorizedUser = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100, 'price' => 10000]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(20)
            ->originalPrice()
            ->create();

        // Create link tied to specific user
        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->withUser($assignedUser)
            ->create();

        $requestData = new HoldPurchaseRequestData(
            link_code: $link->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $ticketDefinition->id,
                    quantity: 1
                ),
            ]
        );

        $this->expectException(UserNotAuthorizedForLinkException::class);

        $this->action->execute($requestData, $unauthorizedUser);
    }

    public function test_it_allows_assigned_user_to_purchase(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $assignedUser = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100, 'price' => 10000]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
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
            ->withUser($assignedUser)
            ->unlimited()
            ->create();

        $requestData = new HoldPurchaseRequestData(
            link_code: $link->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $ticketDefinition->id,
                    quantity: 1
                ),
            ]
        );

        $result = $this->action->execute($requestData, $assignedUser);

        $this->assertNotNull($result['transaction']);
    }

    public function test_it_calculates_correct_prices_for_discounted_tickets(): void
    {
        $setup = $this->createSetup(
            pricingMode: PricingModeEnum::PERCENTAGE_DISCOUNT,
            discountPercentage: 50,
            ticketPrice: 10000
        );

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 2
                ),
            ]
        );

        $result = $this->action->execute($requestData);

        // 2 tickets at 5000 (50% of 10000) = 10000 total
        $this->assertEquals(10000, $result['transaction']->total_amount);
        $this->assertEquals(10000, $result['totals']['subtotal']);
        $this->assertEquals(10000, $result['totals']['total_savings']); // (10000-5000)*2

        // Check purchase records
        foreach ($result['purchases'] as $purchase) {
            $this->assertEquals(5000, $purchase->unit_price);
            $this->assertEquals(10000, $purchase->original_price);
        }
    }

    public function test_it_calculates_correct_prices_for_free_tickets(): void
    {
        $setup = $this->createSetup(
            pricingMode: PricingModeEnum::FREE,
            ticketPrice: 10000
        );

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 3
                ),
            ]
        );

        $result = $this->action->execute($requestData);

        $this->assertEquals(0, $result['transaction']->total_amount);
        $this->assertEquals(0, $result['totals']['subtotal']);
        $this->assertEquals(30000, $result['totals']['total_savings']); // 10000 * 3

        foreach ($result['purchases'] as $purchase) {
            $this->assertEquals(0, $purchase->unit_price);
            $this->assertEquals(10000, $purchase->original_price);
        }
    }

    public function test_it_calculates_correct_prices_for_fixed_price_tickets(): void
    {
        $setup = $this->createSetup(
            pricingMode: PricingModeEnum::FIXED,
            customPrice: 7500,
            ticketPrice: 10000
        );

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 2
                ),
            ]
        );

        $result = $this->action->execute($requestData);

        $this->assertEquals(15000, $result['transaction']->total_amount); // 7500 * 2
        $this->assertEquals(15000, $result['totals']['subtotal']);
        $this->assertEquals(5000, $result['totals']['total_savings']); // (10000-7500)*2
    }

    public function test_it_updates_link_purchased_quantity(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100, 'price' => 10000]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
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
            ->maxQuantity(10)
            ->create(['quantity_purchased' => 0]);

        $requestData = new HoldPurchaseRequestData(
            link_code: $link->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $ticketDefinition->id,
                    quantity: 3
                ),
            ]
        );

        $this->action->execute($requestData);

        $link->refresh();
        $this->assertEquals(3, $link->quantity_purchased);
    }

    public function test_it_throws_exception_when_exceeding_link_quantity_limit(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100, 'price' => 10000]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(50)
            ->originalPrice()
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->maxQuantity(5) // Only 5 allowed
            ->create(['quantity_purchased' => 3]); // Already 3 used

        $requestData = new HoldPurchaseRequestData(
            link_code: $link->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $ticketDefinition->id,
                    quantity: 5 // Trying to purchase 5 more
                ),
            ]
        );

        $this->expectException(LinkNotUsableException::class);

        $this->action->execute($requestData);
    }

    public function test_it_creates_transaction_with_correct_metadata(): void
    {
        $setup = $this->createSetup();

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 1
                ),
            ]
        );

        $result = $this->action->execute($requestData);

        $transaction = $result['transaction'];
        $this->assertEquals(TransactionStatusEnum::CONFIRMED, $transaction->status);
        $this->assertEquals('ticket_hold', $transaction->metadata['source']);
        $this->assertArrayHasKey('total_savings', $transaction->metadata);
    }

    public function test_it_handles_multiple_ticket_types(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $ticketDef1 = TicketDefinition::factory()->create(['total_quantity' => 100, 'price' => 10000]);
        $ticketDef2 = TicketDefinition::factory()->create(['total_quantity' => 100, 'price' => 5000]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDef1)
            ->withQuantity(20)
            ->originalPrice()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDef2)
            ->withQuantity(30)
            ->discounted(20)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->unlimited()
            ->create();

        $requestData = new HoldPurchaseRequestData(
            link_code: $link->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $ticketDef1->id,
                    quantity: 2
                ),
                new HoldPurchaseItemData(
                    ticket_definition_id: $ticketDef2->id,
                    quantity: 3
                ),
            ]
        );

        $result = $this->action->execute($requestData);

        // 2 bookings for ticket1, 3 bookings for ticket2
        $this->assertCount(5, $result['bookings']);
        $this->assertCount(5, $result['purchases']);

        // Total: 2*10000 + 3*4000 = 20000 + 12000 = 32000
        $this->assertEquals(32000, $result['transaction']->total_amount);
    }

    public function test_it_works_without_user(): void
    {
        $setup = $this->createSetup();

        $requestData = new HoldPurchaseRequestData(
            link_code: $setup['link']->code,
            items: [
                new HoldPurchaseItemData(
                    ticket_definition_id: $setup['ticketDefinition']->id,
                    quantity: 1
                ),
            ]
        );

        $result = $this->action->execute($requestData, null, null);

        $this->assertNull($result['transaction']->user_id);
        $this->assertNull($result['purchases'][0]->user_id);
    }
}
