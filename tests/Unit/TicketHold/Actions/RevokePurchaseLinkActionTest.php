<?php

namespace Tests\Unit\TicketHold\Actions;

use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\Actions\Links\RevokePurchaseLinkAction;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevokePurchaseLinkActionTest extends TestCase
{
    use RefreshDatabase;

    private RevokePurchaseLinkAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new RevokePurchaseLinkAction;
    }

    public function test_it_revokes_active_link(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $revokedBy = User::factory()->create();
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

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();

        $this->assertEquals(LinkStatusEnum::ACTIVE, $link->status);

        $revokedLink = $this->action->execute($link, $revokedBy);

        $this->assertEquals(LinkStatusEnum::REVOKED, $revokedLink->status);
    }

    public function test_it_sets_revoked_at_timestamp(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $revokedBy = User::factory()->create();
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

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();

        $this->assertNull($link->revoked_at);

        $revokedLink = $this->action->execute($link, $revokedBy);

        $this->assertNotNull($revokedLink->revoked_at);
        $this->assertTrue($revokedLink->revoked_at->isToday());
    }

    public function test_it_sets_revoked_by_user(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $revokedBy = User::factory()->create();
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

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();

        $this->assertNull($link->revoked_by);

        $revokedLink = $this->action->execute($link, $revokedBy);

        $this->assertEquals($revokedBy->id, $revokedLink->revoked_by);
    }

    public function test_it_does_not_change_already_revoked_link(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $originalRevoker = User::factory()->create();
        $newRevoker = User::factory()->create();
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

        $originalRevokedAt = now()->subDays(5);

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->create([
                'status' => LinkStatusEnum::REVOKED,
                'revoked_at' => $originalRevokedAt,
                'revoked_by' => $originalRevoker->id,
            ]);

        $result = $this->action->execute($link, $newRevoker);

        // Should not have changed
        $this->assertEquals(LinkStatusEnum::REVOKED, $result->status);
        $this->assertEquals($originalRevoker->id, $result->revoked_by);
        $this->assertEquals(
            $originalRevokedAt->format('Y-m-d H:i:s'),
            $result->revoked_at->format('Y-m-d H:i:s')
        );
    }

    public function test_it_does_not_change_expired_link(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $revokedBy = User::factory()->create();
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

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->expired()
            ->create();

        $result = $this->action->execute($link, $revokedBy);

        // Status should remain expired
        $this->assertEquals(LinkStatusEnum::EXPIRED, $result->status);
        $this->assertNull($result->revoked_by);
    }

    public function test_it_does_not_change_exhausted_link(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $revokedBy = User::factory()->create();
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

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->exhausted()
            ->create();

        $result = $this->action->execute($link, $revokedBy);

        // Status should remain exhausted
        $this->assertEquals(LinkStatusEnum::EXHAUSTED, $result->status);
        $this->assertNull($result->revoked_by);
    }

    public function test_it_loads_relationships_after_revoke(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $revokedBy = User::factory()->create();
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

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();

        $revokedLink = $this->action->execute($link, $revokedBy);

        $this->assertTrue($revokedLink->relationLoaded('ticketHold'));
        $this->assertTrue($revokedLink->relationLoaded('revokedByUser'));
    }

    public function test_it_preserves_other_link_data_on_revoke(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $revokedBy = User::factory()->create();
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

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create([
                'name' => 'Test Link',
                'assigned_user_id' => $assignedUser->id,
                'quantity_purchased' => 3,
                'notes' => 'Some notes',
                'metadata' => ['key' => 'value'],
            ]);

        $originalCode = $link->code;
        $originalUuid = $link->uuid;

        $revokedLink = $this->action->execute($link, $revokedBy);

        $this->assertEquals('Test Link', $revokedLink->name);
        $this->assertEquals($assignedUser->id, $revokedLink->assigned_user_id);
        $this->assertEquals(3, $revokedLink->quantity_purchased);
        $this->assertEquals('Some notes', $revokedLink->notes);
        $this->assertEquals(['key' => 'value'], $revokedLink->metadata);
        $this->assertEquals($originalCode, $revokedLink->code);
        $this->assertEquals($originalUuid, $revokedLink->uuid);
    }

    public function test_revoked_link_is_no_longer_usable(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $revokedBy = User::factory()->create();
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

        $link = PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();

        $this->assertTrue($link->is_usable);

        $revokedLink = $this->action->execute($link, $revokedBy);

        $this->assertFalse($revokedLink->is_usable);
    }
}
