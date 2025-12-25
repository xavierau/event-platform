<?php

namespace Tests\Unit\TicketHold\Actions;

use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\Actions\Holds\ReleaseTicketHoldAction;
use App\Modules\TicketHold\Enums\HoldStatusEnum;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseTicketHoldActionTest extends TestCase
{
    use RefreshDatabase;

    private ReleaseTicketHoldAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new ReleaseTicketHoldAction;
    }

    public function test_it_sets_released_at_timestamp(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $releasedBy = User::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        $this->assertNull($hold->released_at);

        $releasedHold = $this->action->execute($hold, $releasedBy);

        $this->assertNotNull($releasedHold->released_at);
        $this->assertTrue($releasedHold->released_at->isToday());
    }

    public function test_it_sets_released_by_user(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $releasedBy = User::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        $this->assertNull($hold->released_by);

        $releasedHold = $this->action->execute($hold, $releasedBy);

        $this->assertEquals($releasedBy->id, $releasedHold->released_by);
    }

    public function test_it_changes_status_to_released(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $releasedBy = User::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        $this->assertEquals(HoldStatusEnum::ACTIVE, $hold->status);

        $releasedHold = $this->action->execute($hold, $releasedBy);

        $this->assertEquals(HoldStatusEnum::RELEASED, $releasedHold->status);
    }

    public function test_it_revokes_all_active_purchase_links(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $releasedBy = User::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        // Create active links
        $activeLink1 = PurchaseLink::factory()->forHold($hold)->active()->create();
        $activeLink2 = PurchaseLink::factory()->forHold($hold)->active()->create();

        $this->assertEquals(LinkStatusEnum::ACTIVE, $activeLink1->status);
        $this->assertEquals(LinkStatusEnum::ACTIVE, $activeLink2->status);

        $releasedHold = $this->action->execute($hold, $releasedBy);

        $activeLink1->refresh();
        $activeLink2->refresh();

        $this->assertEquals(LinkStatusEnum::REVOKED, $activeLink1->status);
        $this->assertEquals(LinkStatusEnum::REVOKED, $activeLink2->status);
        $this->assertNotNull($activeLink1->revoked_at);
        $this->assertNotNull($activeLink2->revoked_at);
        $this->assertEquals($releasedBy->id, $activeLink1->revoked_by);
        $this->assertEquals($releasedBy->id, $activeLink2->revoked_by);
    }

    public function test_it_does_not_change_already_revoked_links(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $revokedBy = User::factory()->create();
        $releasedBy = User::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        $revokedAt = now()->subDays(2);

        // Create already revoked link
        $revokedLink = PurchaseLink::factory()->forHold($hold)->create([
            'status' => LinkStatusEnum::REVOKED,
            'revoked_at' => $revokedAt,
            'revoked_by' => $revokedBy->id,
        ]);

        $releasedHold = $this->action->execute($hold, $releasedBy);

        $revokedLink->refresh();

        // Should not have changed
        $this->assertEquals(LinkStatusEnum::REVOKED, $revokedLink->status);
        $this->assertEquals($revokedBy->id, $revokedLink->revoked_by);
        $this->assertEquals(
            $revokedAt->format('Y-m-d H:i:s'),
            $revokedLink->revoked_at->format('Y-m-d H:i:s')
        );
    }

    public function test_it_does_not_change_expired_links(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $releasedBy = User::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        // Create expired link
        $expiredLink = PurchaseLink::factory()->forHold($hold)->expired()->create();

        $originalStatus = $expiredLink->status;

        $releasedHold = $this->action->execute($hold, $releasedBy);

        $expiredLink->refresh();

        $this->assertEquals($originalStatus, $expiredLink->status);
    }

    public function test_it_does_not_change_exhausted_links(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $releasedBy = User::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        // Create exhausted link
        $exhaustedLink = PurchaseLink::factory()->forHold($hold)->exhausted()->create();

        $originalStatus = $exhaustedLink->status;

        $releasedHold = $this->action->execute($hold, $releasedBy);

        $exhaustedLink->refresh();

        $this->assertEquals($originalStatus, $exhaustedLink->status);
    }

    public function test_it_can_release_hold_without_any_links(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $releasedBy = User::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        // No links attached

        $releasedHold = $this->action->execute($hold, $releasedBy);

        $this->assertEquals(HoldStatusEnum::RELEASED, $releasedHold->status);
        $this->assertCount(0, $releasedHold->purchaseLinks);
    }

    public function test_it_can_release_hold_with_allocations(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $releasedBy = User::factory()->create();
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
            ->withPurchased(5)
            ->create();

        $releasedHold = $this->action->execute($hold, $releasedBy);

        $this->assertEquals(HoldStatusEnum::RELEASED, $releasedHold->status);
        $this->assertCount(1, $releasedHold->allocations);
        $this->assertEquals(20, $releasedHold->allocations->first()->allocated_quantity);
        $this->assertEquals(5, $releasedHold->allocations->first()->purchased_quantity);
    }

    public function test_it_loads_relationships_after_release(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $releasedBy = User::factory()->create();
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
            ->withQuantity(10)
            ->create();

        PurchaseLink::factory()->forHold($hold)->active()->create();

        $releasedHold = $this->action->execute($hold, $releasedBy);

        $this->assertTrue($releasedHold->relationLoaded('allocations'));
        $this->assertTrue($releasedHold->allocations->first()->relationLoaded('ticketDefinition'));
        $this->assertTrue($releasedHold->relationLoaded('purchaseLinks'));
    }

    public function test_it_revokes_multiple_active_links_in_single_transaction(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $releasedBy = User::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->active()
            ->create();

        // Create multiple active and non-active links
        $activeLinks = PurchaseLink::factory()->forHold($hold)->active()->count(3)->create();
        $expiredLink = PurchaseLink::factory()->forHold($hold)->expired()->create();
        $revokedLink = PurchaseLink::factory()->forHold($hold)->revoked()->create();

        $releasedHold = $this->action->execute($hold, $releasedBy);

        // Check all active links were revoked
        foreach ($activeLinks as $link) {
            $link->refresh();
            $this->assertEquals(LinkStatusEnum::REVOKED, $link->status);
            $this->assertNotNull($link->revoked_at);
            $this->assertEquals($releasedBy->id, $link->revoked_by);
        }

        // Check non-active links were not touched
        $expiredLink->refresh();
        $this->assertEquals(LinkStatusEnum::EXPIRED, $expiredLink->status);

        $revokedLink->refresh();
        $this->assertEquals(LinkStatusEnum::REVOKED, $revokedLink->status);
    }

    public function test_releasing_already_released_hold_updates_again(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $creator = User::factory()->create();
        $firstReleasedBy = User::factory()->create();
        $secondReleasedBy = User::factory()->create();

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($creator)
            ->released()
            ->create([
                'released_at' => now()->subDay(),
                'released_by' => $firstReleasedBy->id,
            ]);

        // Release again - the action will update the released_at and released_by
        $releasedHold = $this->action->execute($hold, $secondReleasedBy);

        // Should update with new values
        $this->assertEquals(HoldStatusEnum::RELEASED, $releasedHold->status);
        $this->assertEquals($secondReleasedBy->id, $releasedHold->released_by);
        $this->assertTrue($releasedHold->released_at->isToday());
    }
}
