<?php

namespace Tests\Unit\TicketHold\Actions;

use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\Actions\Links\CreatePurchaseLinkAction;
use App\Modules\TicketHold\DTOs\PurchaseLinkData;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
use App\Modules\TicketHold\Exceptions\HoldNotActiveException;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePurchaseLinkActionTest extends TestCase
{
    use RefreshDatabase;

    private CreatePurchaseLinkAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreatePurchaseLinkAction;
    }

    public function test_it_creates_purchase_link_with_valid_data(): void
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
            name: 'VIP Invite Link',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: now()->addDays(7),
            notes: 'For VIP customers only',
            metadata: ['source' => 'manual_invite'],
        );

        $link = $this->action->execute($data);

        $this->assertInstanceOf(PurchaseLink::class, $link);
        $this->assertEquals('VIP Invite Link', $link->name);
        $this->assertEquals($hold->id, $link->ticket_hold_id);
        $this->assertEquals(QuantityModeEnum::MAXIMUM, $link->quantity_mode);
        $this->assertEquals(5, $link->quantity_limit);
        $this->assertEquals(0, $link->quantity_purchased);
        $this->assertEquals(LinkStatusEnum::ACTIVE, $link->status);
        $this->assertEquals('For VIP customers only', $link->notes);
        $this->assertEquals(['source' => 'manual_invite'], $link->metadata);
    }

    public function test_it_generates_unique_code(): void
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

        $link = $this->action->execute($data);

        $this->assertNotNull($link->code);
        $this->assertEquals(16, strlen($link->code));
    }

    public function test_it_generates_uuid(): void
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

        $link = $this->action->execute($data);

        $this->assertNotNull($link->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $link->uuid
        );
    }

    public function test_it_creates_anonymous_link(): void
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
            name: 'Anonymous Link',
            assigned_user_id: null, // No assigned user
            quantity_mode: QuantityModeEnum::UNLIMITED,
            quantity_limit: null,
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $link = $this->action->execute($data);

        $this->assertNull($link->assigned_user_id);
        $this->assertTrue($link->is_anonymous);
    }

    public function test_it_creates_user_assigned_link(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $assignedUser = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);

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
            ->create();

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Personal Link',
            assigned_user_id: $assignedUser->id,
            quantity_mode: QuantityModeEnum::FIXED,
            quantity_limit: 2,
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $link = $this->action->execute($data);

        $this->assertEquals($assignedUser->id, $link->assigned_user_id);
        $this->assertFalse($link->is_anonymous);
    }

    public function test_it_creates_link_with_fixed_quantity_mode(): void
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
            name: 'Fixed Quantity Link',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::FIXED,
            quantity_limit: 3,
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $link = $this->action->execute($data);

        $this->assertEquals(QuantityModeEnum::FIXED, $link->quantity_mode);
        $this->assertEquals(3, $link->quantity_limit);
    }

    public function test_it_creates_link_with_unlimited_quantity_mode(): void
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
            name: 'Unlimited Link',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::UNLIMITED,
            quantity_limit: null,
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $link = $this->action->execute($data);

        $this->assertEquals(QuantityModeEnum::UNLIMITED, $link->quantity_mode);
        $this->assertNull($link->quantity_limit);
    }

    public function test_it_throws_exception_for_inactive_hold(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->expired()
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

        $this->expectException(HoldNotActiveException::class);

        $this->action->execute($data);
    }

    public function test_it_throws_exception_for_released_hold(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->released()
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

        $this->expectException(HoldNotActiveException::class);

        $this->action->execute($data);
    }

    public function test_it_creates_link_with_expiration(): void
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

        $expiresAt = now()->addDays(14);

        $data = new PurchaseLinkData(
            ticket_hold_id: $hold->id,
            name: 'Expiring Link',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: $expiresAt,
            notes: null,
            metadata: null,
        );

        $link = $this->action->execute($data);

        $this->assertEquals(
            $expiresAt->format('Y-m-d H:i:s'),
            $link->expires_at->format('Y-m-d H:i:s')
        );
    }

    public function test_it_creates_link_without_expiration(): void
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
            name: 'No Expiry Link',
            assigned_user_id: null,
            quantity_mode: QuantityModeEnum::MAXIMUM,
            quantity_limit: 5,
            expires_at: null,
            notes: null,
            metadata: null,
        );

        $link = $this->action->execute($data);

        $this->assertNull($link->expires_at);
    }

    public function test_it_loads_relationships_after_creation(): void
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

        $link = $this->action->execute($data);

        $this->assertTrue($link->relationLoaded('ticketHold'));
        $this->assertTrue($link->ticketHold->relationLoaded('allocations'));
        $this->assertTrue($link->ticketHold->allocations->first()->relationLoaded('ticketDefinition'));
    }

    public function test_it_creates_multiple_unique_links(): void
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

        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $data = new PurchaseLinkData(
                ticket_hold_id: $hold->id,
                name: "Link {$i}",
                assigned_user_id: null,
                quantity_mode: QuantityModeEnum::MAXIMUM,
                quantity_limit: 5,
                expires_at: null,
                notes: null,
                metadata: null,
            );

            $link = $this->action->execute($data);
            $codes[] = $link->code;
        }

        // All codes should be unique
        $this->assertEquals(5, count(array_unique($codes)));
    }
}
