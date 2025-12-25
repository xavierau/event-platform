<?php

namespace Tests\Unit\TicketHold\Actions;

use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\Actions\Links\UpdatePurchaseLinkAction;
use App\Modules\TicketHold\DTOs\PurchaseLinkData;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
use App\Modules\TicketHold\Exceptions\LinkNotUsableException;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatePurchaseLinkActionTest extends TestCase
{
    use RefreshDatabase;

    private UpdatePurchaseLinkAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdatePurchaseLinkAction;
    }

    public function test_it_updates_link_name(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
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
            ->withQuantity(20)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create(['name' => 'Original Name']);

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Updated Name',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $updatedLink = $this->action->execute($link, $data);

        $this->assertEquals('Updated Name', $updatedLink->name);
    }

    public function test_it_updates_link_notes(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
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
            ->withQuantity(20)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create(['notes' => 'Original notes']);

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Test Link',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: null,
            notes: 'Updated notes',
            metadata: null,
        );

        $updatedLink = $this->action->execute($link, $data);

        $this->assertEquals('Updated notes', $updatedLink->notes);
    }

    public function test_it_updates_link_metadata(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
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
            ->withQuantity(20)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create(['metadata' => ['original' => 'data']]);

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Test Link',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: null,
            notes: null,
            metadata: ['updated' => 'metadata', 'key' => 'value'],
        );

        $updatedLink = $this->action->execute($link, $data);

        $this->assertEquals(['updated' => 'metadata', 'key' => 'value'], $updatedLink->metadata);
    }

    public function test_it_updates_expiration_date(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
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
            ->withQuantity(20)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create(['expires_at' => now()->addDays(7)]);

        $newExpiresAt = now()->addDays(30);

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Test Link',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: $newExpiresAt,
            notes: null,
            metadata: null,
        );

        $updatedLink = $this->action->execute($link, $data);

        $this->assertEquals(
            $newExpiresAt->format('Y-m-d H:i:s'),
            $updatedLink->expires_at->format('Y-m-d H:i:s')
        );
    }

    public function test_it_can_remove_expiration_date(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
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
            ->withQuantity(20)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create(['expires_at' => now()->addDays(7)]);

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Test Link',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $updatedLink = $this->action->execute($link, $data);

        $this->assertNull($updatedLink->expires_at);
    }

    public function test_it_does_not_update_quantity_mode(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
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
            ->withQuantity(20)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->maxQuantity(10)
            ->create();

        $originalQuantityMode = $link->quantity_mode;
        $originalQuantityLimit = $link->quantity_limit;

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Updated Name',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::FIXED, // Different mode
            quantity_limit: 100, // Different limit
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $updatedLink = $this->action->execute($link, $data);

        // Quantity mode and limit should NOT change
        $this->assertEquals($originalQuantityMode, $updatedLink->quantity_mode);
        $this->assertEquals($originalQuantityLimit, $updatedLink->quantity_limit);
    }

    public function test_it_throws_exception_for_non_usable_link_with_purchases(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
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
            ->withQuantity(20)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->create([
                'status' => LinkStatusEnum::REVOKED, // Not usable
                'quantity_purchased' => 3, // Has purchases
            ]);

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Updated Name',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $this->expectException(LinkNotUsableException::class);

        $this->action->execute($link, $data);
    }

    public function test_it_allows_update_of_active_link_with_no_purchases(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
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
            ->withQuantity(20)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create(['quantity_purchased' => 0]);

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Updated Name',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $updatedLink = $this->action->execute($link, $data);

        $this->assertEquals('Updated Name', $updatedLink->name);
    }

    public function test_it_allows_update_of_active_link_with_purchases(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
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
            ->withQuantity(20)
            ->create();

        // Active link with purchases should still be updatable
        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create(['quantity_purchased' => 3]);

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Updated Name',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: null,
            notes: 'Updated notes',
            metadata: null,
        );

        $updatedLink = $this->action->execute($link, $data);

        $this->assertEquals('Updated Name', $updatedLink->name);
        $this->assertEquals('Updated notes', $updatedLink->notes);
    }

    public function test_it_loads_relationships_after_update(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
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
            ->withQuantity(20)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Updated Name',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $updatedLink = $this->action->execute($link, $data);

        $this->assertTrue($updatedLink->relationLoaded('ticketHold'));
        $this->assertTrue($updatedLink->ticketHold->relationLoaded('allocations'));
        $this->assertTrue($updatedLink->ticketHold->allocations->first()->relationLoaded('ticketDefinition'));
    }

    public function test_it_preserves_code_and_uuid_on_update(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
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
            ->withQuantity(20)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();

        $originalCode = $link->code;
        $originalUuid = $link->uuid;

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Updated Name',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $updatedLink = $this->action->execute($link, $data);

        $this->assertEquals($originalCode, $updatedLink->code);
        $this->assertEquals($originalUuid, $updatedLink->uuid);
    }

    public function test_it_preserves_quantity_purchased_on_update(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
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
            ->withQuantity(20)
            ->create();

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create(['quantity_purchased' => 7]);

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Updated Name',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 10,
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $updatedLink = $this->action->execute($link, $data);

        $this->assertEquals(7, $updatedLink->quantity_purchased);
    }
}
