<?php

namespace Tests\Unit\TicketHold\Actions;

use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\Actions\Holds\CreateTicketHoldAction;
use App\Modules\TicketHold\Actions\Holds\ValidateHoldAvailabilityAction;
use App\Modules\TicketHold\DTOs\TicketAllocationData;
use App\Modules\TicketHold\DTOs\TicketHoldData;
use App\Modules\TicketHold\Enums\HoldStatusEnum;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use App\Modules\TicketHold\Exceptions\InsufficientInventoryException;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTicketHoldActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateTicketHoldAction $action;

    private ValidateHoldAvailabilityAction $validateAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validateAction = app(ValidateHoldAvailabilityAction::class);
        $this->action = new CreateTicketHoldAction($this->validateAction);
    }

    public function test_it_creates_ticket_hold_with_valid_data(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
        ]);

        $data = new TicketHoldData(
            event_occurrence_id: $occurrence->id,
            organizer_id: $organizer->id,
            name: 'VIP Hold',
            description: 'Hold for VIP customers',
            internal_notes: 'Internal notes here',
            allocations: [
                new TicketAllocationData(
                    ticket_definition_id: $ticketDefinition->id,
                    allocated_quantity: 10,
                    pricing_mode: PricingModeEnum::ORIGINAL,
                    custom_price: null,
                    discount_percentage: null,
                ),
            ],
            expires_at: now()->addDays(7),
        );

        $hold = $this->action->execute($data, $user);

        $this->assertInstanceOf(TicketHold::class, $hold);
        $this->assertEquals('VIP Hold', $hold->name);
        $this->assertEquals('Hold for VIP customers', $hold->description);
        $this->assertEquals('Internal notes here', $hold->internal_notes);
        $this->assertEquals($occurrence->id, $hold->event_occurrence_id);
        $this->assertEquals($organizer->id, $hold->organizer_id);
        $this->assertEquals($user->id, $hold->created_by);
        $this->assertEquals(HoldStatusEnum::ACTIVE, $hold->status);
        $this->assertNotNull($hold->uuid);
    }

    public function test_it_creates_allocations_for_each_ticket_definition(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition1 = TicketDefinition::factory()->create(['total_quantity' => 100]);
        $ticketDefinition2 = TicketDefinition::factory()->create(['total_quantity' => 100]);

        $data = new TicketHoldData(
            event_occurrence_id: $occurrence->id,
            organizer_id: $organizer->id,
            name: 'Multi-Ticket Hold',
            description: null,
            internal_notes: null,
            allocations: [
                new TicketAllocationData(
                    ticket_definition_id: $ticketDefinition1->id,
                    allocated_quantity: 10,
                    pricing_mode: PricingModeEnum::ORIGINAL,
                    custom_price: null,
                    discount_percentage: null,
                ),
                new TicketAllocationData(
                    ticket_definition_id: $ticketDefinition2->id,
                    allocated_quantity: 20,
                    pricing_mode: PricingModeEnum::FIXED,
                    custom_price: 5000,
                    discount_percentage: null,
                ),
            ],
            expires_at: null,
        );

        $hold = $this->action->execute($data, $user);

        $this->assertCount(2, $hold->allocations);

        $allocation1 = $hold->allocations->where('ticket_definition_id', $ticketDefinition1->id)->first();
        $this->assertEquals(10, $allocation1->allocated_quantity);
        $this->assertEquals(0, $allocation1->purchased_quantity);
        $this->assertEquals(PricingModeEnum::ORIGINAL, $allocation1->pricing_mode);

        $allocation2 = $hold->allocations->where('ticket_definition_id', $ticketDefinition2->id)->first();
        $this->assertEquals(20, $allocation2->allocated_quantity);
        $this->assertEquals(0, $allocation2->purchased_quantity);
        $this->assertEquals(PricingModeEnum::FIXED, $allocation2->pricing_mode);
        $this->assertEquals(5000, $allocation2->custom_price);
    }

    public function test_it_sets_correct_status_and_timestamps(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);
        $expiresAt = now()->addDays(14);

        $data = new TicketHoldData(
            event_occurrence_id: $occurrence->id,
            organizer_id: $organizer->id,
            name: 'Timed Hold',
            description: null,
            internal_notes: null,
            allocations: [
                new TicketAllocationData(
                    ticket_definition_id: $ticketDefinition->id,
                    allocated_quantity: 5,
                    pricing_mode: PricingModeEnum::FREE,
                    custom_price: null,
                    discount_percentage: null,
                ),
            ],
            expires_at: $expiresAt,
        );

        $hold = $this->action->execute($data, $user);

        $this->assertEquals(HoldStatusEnum::ACTIVE, $hold->status);
        $this->assertNull($hold->released_at);
        $this->assertNull($hold->released_by);
        $this->assertEquals($expiresAt->format('Y-m-d H:i:s'), $hold->expires_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($hold->created_at);
    }

    public function test_it_throws_exception_when_inventory_insufficient(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 5]);

        $data = new TicketHoldData(
            event_occurrence_id: $occurrence->id,
            organizer_id: $organizer->id,
            name: 'Too Large Hold',
            description: null,
            internal_notes: null,
            allocations: [
                new TicketAllocationData(
                    ticket_definition_id: $ticketDefinition->id,
                    allocated_quantity: 10, // More than available
                    pricing_mode: PricingModeEnum::ORIGINAL,
                    custom_price: null,
                    discount_percentage: null,
                ),
            ],
            expires_at: null,
        );

        $this->expectException(InsufficientInventoryException::class);

        $this->action->execute($data, $user);
    }

    public function test_it_creates_hold_with_percentage_discount_pricing(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);

        $data = new TicketHoldData(
            event_occurrence_id: $occurrence->id,
            organizer_id: $organizer->id,
            name: 'Discount Hold',
            description: null,
            internal_notes: null,
            allocations: [
                new TicketAllocationData(
                    ticket_definition_id: $ticketDefinition->id,
                    allocated_quantity: 10,
                    pricing_mode: PricingModeEnum::PERCENTAGE_DISCOUNT,
                    custom_price: null,
                    discount_percentage: 25,
                ),
            ],
            expires_at: null,
        );

        $hold = $this->action->execute($data, $user);

        $allocation = $hold->allocations->first();
        $this->assertEquals(PricingModeEnum::PERCENTAGE_DISCOUNT, $allocation->pricing_mode);
        $this->assertEquals(25, $allocation->discount_percentage);
    }

    public function test_it_creates_hold_without_expiration(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);

        $data = new TicketHoldData(
            event_occurrence_id: $occurrence->id,
            organizer_id: $organizer->id,
            name: 'Permanent Hold',
            description: null,
            internal_notes: null,
            allocations: [
                new TicketAllocationData(
                    ticket_definition_id: $ticketDefinition->id,
                    allocated_quantity: 10,
                    pricing_mode: PricingModeEnum::ORIGINAL,
                    custom_price: null,
                    discount_percentage: null,
                ),
            ],
            expires_at: null,
        );

        $hold = $this->action->execute($data, $user);

        $this->assertNull($hold->expires_at);
        $this->assertFalse($hold->is_expired);
    }

    public function test_it_creates_hold_for_unlimited_inventory_ticket(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => null, // Unlimited
        ]);

        $data = new TicketHoldData(
            event_occurrence_id: $occurrence->id,
            organizer_id: $organizer->id,
            name: 'Unlimited Ticket Hold',
            description: null,
            internal_notes: null,
            allocations: [
                new TicketAllocationData(
                    ticket_definition_id: $ticketDefinition->id,
                    allocated_quantity: 1000, // Large quantity
                    pricing_mode: PricingModeEnum::ORIGINAL,
                    custom_price: null,
                    discount_percentage: null,
                ),
            ],
            expires_at: null,
        );

        $hold = $this->action->execute($data, $user);

        $this->assertInstanceOf(TicketHold::class, $hold);
        $this->assertEquals(1000, $hold->allocations->first()->allocated_quantity);
    }

    public function test_it_loads_relationships_after_creation(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);

        $data = new TicketHoldData(
            event_occurrence_id: $occurrence->id,
            organizer_id: $organizer->id,
            name: 'Test Hold',
            description: null,
            internal_notes: null,
            allocations: [
                new TicketAllocationData(
                    ticket_definition_id: $ticketDefinition->id,
                    allocated_quantity: 10,
                    pricing_mode: PricingModeEnum::ORIGINAL,
                    custom_price: null,
                    discount_percentage: null,
                ),
            ],
            expires_at: null,
        );

        $hold = $this->action->execute($data, $user);

        $this->assertTrue($hold->relationLoaded('allocations'));
        $this->assertTrue($hold->allocations->first()->relationLoaded('ticketDefinition'));
        $this->assertTrue($hold->relationLoaded('eventOccurrence'));
    }
}
