<?php

namespace Tests\Unit\TicketHold\Services;

use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\DTOs\PurchaseLinkData;
use App\Modules\TicketHold\DTOs\TicketAllocationData;
use App\Modules\TicketHold\DTOs\TicketHoldData;
use App\Modules\TicketHold\Enums\HoldStatusEnum;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;
use App\Modules\TicketHold\Services\TicketHoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketHoldServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketHoldService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TicketHoldService::class);
    }

    // ========================================
    // Hold Operations Tests
    // ========================================

    public function test_create_hold_delegates_to_action(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);

        $data = new TicketHoldData(
            event_occurrence_id: $occurrence->id,
            organizer_id: $organizer->id,
            name: 'Test Hold',
            description: 'Test description',
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

        $hold = $this->service->createHold($data, $user);

        $this->assertInstanceOf(TicketHold::class, $hold);
        $this->assertEquals('Test Hold', $hold->name);
        $this->assertEquals(HoldStatusEnum::ACTIVE, $hold->status);
    }

    public function test_update_hold_delegates_to_action(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create(['name' => 'Original Name']);

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(10)
            ->create();

        $data = new TicketHoldData(
            event_occurrence_id: $occurrence->id,
            organizer_id: $organizer->id,
            name: 'Updated Name',
            description: 'Updated description',
            internal_notes: null,
            allocations: [
                new TicketAllocationData(
                    ticket_definition_id: $ticketDefinition->id,
                    allocated_quantity: 15,
                    pricing_mode: PricingModeEnum::ORIGINAL,
                    custom_price: null,
                    discount_percentage: null,
                ),
            ],
            expires_at: null,
        );

        $updatedHold = $this->service->updateHold($hold, $data);

        $this->assertEquals('Updated Name', $updatedHold->name);
    }

    public function test_release_hold_delegates_to_action(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        $releasedHold = $this->service->releaseHold($hold, $user);

        $this->assertEquals(HoldStatusEnum::RELEASED, $releasedHold->status);
        $this->assertNotNull($releasedHold->released_at);
    }

    public function test_get_hold_by_id_returns_hold_with_relationships(): void
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
            ->withQuantity(10)
            ->create();

        $result = $this->service->getHoldById($hold->id);

        $this->assertNotNull($result);
        $this->assertEquals($hold->id, $result->id);
        $this->assertTrue($result->relationLoaded('allocations'));
        $this->assertTrue($result->relationLoaded('eventOccurrence'));
        $this->assertTrue($result->relationLoaded('purchaseLinks'));
        $this->assertTrue($result->relationLoaded('creator'));
    }

    public function test_get_hold_by_id_returns_null_for_nonexistent(): void
    {
        $result = $this->service->getHoldById(99999);

        $this->assertNull($result);
    }

    public function test_get_hold_by_uuid_returns_hold(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        $result = $this->service->getHoldByUuid($hold->uuid);

        $this->assertNotNull($result);
        $this->assertEquals($hold->id, $result->id);
    }

    public function test_get_holds_for_organizer_returns_collection(): void
    {
        $organizer = Organizer::factory()->create();
        $otherOrganizer = Organizer::factory()->create();
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
            ->forOrganizer($otherOrganizer)
            ->createdBy($user)
            ->active()
            ->count(2)
            ->create();

        $result = $this->service->getHoldsForOrganizer($organizer->id);

        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn ($h) => $h->organizer_id === $organizer->id));
    }

    public function test_get_holds_for_organizer_filters_by_status(): void
    {
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $user = User::factory()->create();

        TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->count(2)
            ->create();

        TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->released()
            ->count(1)
            ->create();

        $result = $this->service->getHoldsForOrganizer($organizer->id, HoldStatusEnum::ACTIVE);

        $this->assertCount(2, $result);
    }

    public function test_get_holds_for_occurrence_returns_collection(): void
    {
        $organizer = Organizer::factory()->create();
        $occurrence = EventOccurrence::factory()->create();
        $otherOccurrence = EventOccurrence::factory()->create();
        $user = User::factory()->create();

        TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->count(3)
            ->create();

        TicketHold::factory()
            ->forOccurrence($otherOccurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->count(2)
            ->create();

        $result = $this->service->getHoldsForOccurrence($occurrence->id);

        $this->assertCount(3, $result);
    }

    // ========================================
    // Link Operations Tests
    // ========================================

    public function test_create_purchase_link_delegates_to_action(): void
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

        $link = $this->service->createPurchaseLink($data);

        $this->assertInstanceOf(PurchaseLink::class, $link);
        $this->assertEquals('Test Link', $link->name);
    }

    public function test_update_purchase_link_delegates_to_action(): void
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

        $updatedLink = $this->service->updatePurchaseLink($link, $data);

        $this->assertEquals('Updated Name', $updatedLink->name);
    }

    public function test_revoke_purchase_link_delegates_to_action(): void
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

        $revokedLink = $this->service->revokePurchaseLink($link, $user);

        $this->assertEquals(LinkStatusEnum::REVOKED, $revokedLink->status);
    }

    public function test_get_link_by_code_returns_link(): void
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

        $result = $this->service->getLinkByCode($link->code);

        $this->assertNotNull($result);
        $this->assertEquals($link->id, $result->id);
    }

    public function test_get_link_by_code_returns_null_for_nonexistent(): void
    {
        $result = $this->service->getLinkByCode('nonexistent-code');

        $this->assertNull($result);
    }

    public function test_get_links_for_hold_returns_collection(): void
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

        PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->count(3)
            ->create();

        $result = $this->service->getLinksForHold($hold);

        $this->assertCount(3, $result);
    }

    // ========================================
    // Validation Tests
    // ========================================

    public function test_validate_link_for_user_returns_valid_for_usable_link(): void
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
            ->neverExpires()
            ->create();

        $result = $this->service->validateLinkForUser($link->code);

        $this->assertTrue($result['valid']);
        $this->assertNotNull($result['link']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_link_for_user_returns_invalid_for_nonexistent_link(): void
    {
        $result = $this->service->validateLinkForUser('nonexistent-code');

        $this->assertFalse($result['valid']);
        $this->assertNull($result['link']);
        $this->assertContains('Link not found', $result['errors']);
    }

    public function test_validate_link_for_user_returns_invalid_for_revoked_link(): void
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
            ->revoked()
            ->create();

        $result = $this->service->validateLinkForUser($link->code);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    // ========================================
    // Utility Operations Tests
    // ========================================

    public function test_update_expired_holds_updates_active_expired_holds(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();

        // Create hold that should be expired
        TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->create([
                'status' => HoldStatusEnum::ACTIVE,
                'expires_at' => now()->subDay(),
            ]);

        // Create hold that is not yet expired
        TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->create([
                'status' => HoldStatusEnum::ACTIVE,
                'expires_at' => now()->addDay(),
            ]);

        $count = $this->service->updateExpiredHolds();

        $this->assertEquals(1, $count);
    }

    public function test_update_expired_links_updates_active_expired_links(): void
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

        // Create expired link
        PurchaseLink::factory()
            ->forHold($hold)
            ->create([
                'status' => LinkStatusEnum::ACTIVE,
                'expires_at' => now()->subDay(),
            ]);

        // Create not-yet-expired link
        PurchaseLink::factory()
            ->forHold($hold)
            ->create([
                'status' => LinkStatusEnum::ACTIVE,
                'expires_at' => now()->addDay(),
            ]);

        $count = $this->service->updateExpiredLinks();

        $this->assertEquals(1, $count);
    }

    public function test_delete_hold_soft_deletes(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        $result = $this->service->deleteHold($hold);

        $this->assertTrue($result);
        $this->assertSoftDeleted('ticket_holds', ['id' => $hold->id]);
    }

    public function test_delete_link_soft_deletes(): void
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

        $result = $this->service->deleteLink($link);

        $this->assertTrue($result);
        $this->assertSoftDeleted('purchase_links', ['id' => $link->id]);
    }
}
