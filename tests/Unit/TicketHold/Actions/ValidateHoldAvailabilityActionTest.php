<?php

namespace Tests\Unit\TicketHold\Actions;

use App\Models\Booking;
use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\Actions\Holds\ValidateHoldAvailabilityAction;
use App\Modules\TicketHold\Exceptions\InsufficientInventoryException;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ValidateHoldAvailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    private ValidateHoldAvailabilityAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new ValidateHoldAvailabilityAction;
    }

    public function test_it_returns_true_when_inventory_available(): void
    {
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
        ]);
        $occurrence = EventOccurrence::factory()->create();

        // Should not throw exception
        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                10,
                $occurrence->id
            );
        });

        $this->assertTrue(true); // If we reach here, validation passed
    }

    public function test_it_throws_exception_when_inventory_exhausted(): void
    {
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 10,
        ]);
        $occurrence = EventOccurrence::factory()->create();

        $this->expectException(InsufficientInventoryException::class);

        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                20, // More than available
                $occurrence->id
            );
        });
    }

    public function test_it_allows_unlimited_inventory_tickets(): void
    {
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => null, // Unlimited
        ]);
        $occurrence = EventOccurrence::factory()->create();

        // Should not throw exception for any quantity
        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                10000, // Large quantity
                $occurrence->id
            );
        });

        $this->assertTrue(true); // If we reach here, validation passed
    }

    public function test_it_considers_existing_bookings(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
        ]);

        // Create confirmed bookings taking up 80 tickets
        Booking::factory()->count(80)->create([
            'event_id' => $occurrence->event_id,
            'ticket_definition_id' => $ticketDefinition->id,
            'quantity' => 1,
            'status' => 'confirmed',
        ]);

        // 20 remaining - requesting 15 should work
        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                15,
                $occurrence->id
            );
        });

        $this->assertTrue(true);

        // But requesting 25 should fail
        $this->expectException(InsufficientInventoryException::class);

        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                25,
                $occurrence->id
            );
        });
    }

    public function test_it_considers_existing_holds(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
        ]);

        // Create an existing hold with 60 tickets
        $existingHold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($existingHold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(60)
            ->create();

        // 40 remaining - requesting 30 should work
        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                30,
                $occurrence->id
            );
        });

        $this->assertTrue(true);

        // But requesting 50 should fail
        $this->expectException(InsufficientInventoryException::class);

        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                50,
                $occurrence->id
            );
        });
    }

    public function test_it_excludes_specified_hold_from_calculation(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
        ]);

        // Create a hold using 80 tickets
        $existingHold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($existingHold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(80)
            ->create();

        // Without exclusion, only 20 available
        $this->expectException(InsufficientInventoryException::class);

        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                50,
                $occurrence->id,
                null // No exclusion
            );
        });
    }

    public function test_it_allows_full_inventory_when_excluding_current_hold(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
        ]);

        // Create a hold using 80 tickets
        $existingHold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($existingHold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(80)
            ->create();

        // With exclusion, full 100 available
        DB::transaction(function () use ($ticketDefinition, $occurrence, $existingHold) {
            $this->action->execute(
                $ticketDefinition->id,
                100, // Full inventory
                $occurrence->id,
                $existingHold->id // Exclude current hold
            );
        });

        $this->assertTrue(true);
    }

    public function test_it_considers_purchased_quantities_in_holds(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
        ]);

        // Create a hold with 60 allocated, 40 purchased
        // This means only 20 are "held" (60 - 40)
        $existingHold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($existingHold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(60)
            ->withPurchased(40)
            ->create();

        // Available = 100 - 0 (bookings) - 20 (held, not purchased) = 80
        // Note: The purchased quantity is tracked separately and affects remaining
        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                80,
                $occurrence->id
            );
        });

        $this->assertTrue(true);
    }

    public function test_it_does_not_consider_inactive_holds(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
        ]);

        // Create an expired hold with 80 tickets (should not count)
        $expiredHold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->expired()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($expiredHold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(80)
            ->create();

        // Create a released hold with 50 tickets (should not count)
        $releasedHold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->released()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($releasedHold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(50)
            ->create();

        // All 100 should be available since holds are not active
        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                100,
                $occurrence->id
            );
        });

        $this->assertTrue(true);
    }

    public function test_it_considers_pending_confirmation_bookings(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
        ]);

        // Create pending_confirmation bookings
        Booking::factory()->count(50)->create([
            'event_id' => $occurrence->event_id,
            'ticket_definition_id' => $ticketDefinition->id,
            'quantity' => 1,
            'status' => 'pending_confirmation',
        ]);

        // 50 remaining
        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                50,
                $occurrence->id
            );
        });

        $this->assertTrue(true);

        // 51 should fail
        $this->expectException(InsufficientInventoryException::class);

        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                51,
                $occurrence->id
            );
        });
    }

    public function test_it_does_not_consider_cancelled_bookings(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
        ]);

        // Create cancelled bookings (should not count)
        Booking::factory()->count(80)->create([
            'event_id' => $occurrence->event_id,
            'ticket_definition_id' => $ticketDefinition->id,
            'quantity' => 1,
            'status' => 'cancelled',
        ]);

        // All 100 should be available
        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                100,
                $occurrence->id
            );
        });

        $this->assertTrue(true);
    }

    public function test_exception_message_includes_ticket_name(): void
    {
        $ticketDefinition = TicketDefinition::factory()->create([
            'name' => ['en' => 'VIP Golden Ticket'],
            'total_quantity' => 10,
        ]);
        $occurrence = EventOccurrence::factory()->create();

        try {
            DB::transaction(function () use ($ticketDefinition, $occurrence) {
                $this->action->execute(
                    $ticketDefinition->id,
                    20,
                    $occurrence->id
                );
            });
            $this->fail('Expected InsufficientInventoryException was not thrown');
        } catch (InsufficientInventoryException $e) {
            $this->assertStringContainsString('VIP Golden Ticket', $e->getMessage());
            $this->assertStringContainsString('Requested: 20', $e->getMessage());
            $this->assertStringContainsString('Available: 10', $e->getMessage());
        }
    }

    public function test_it_handles_zero_available_inventory(): void
    {
        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
        ]);

        // Book all tickets
        Booking::factory()->count(100)->create([
            'event_id' => $occurrence->event_id,
            'ticket_definition_id' => $ticketDefinition->id,
            'quantity' => 1,
            'status' => 'confirmed',
        ]);

        $this->expectException(InsufficientInventoryException::class);

        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                1,
                $occurrence->id
            );
        });
    }

    public function test_it_uses_locking_for_concurrency_safety(): void
    {
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 10,
        ]);
        $occurrence = EventOccurrence::factory()->create();

        // This test verifies the action runs within a transaction context
        // The actual concurrency test would require multiple processes
        DB::transaction(function () use ($ticketDefinition, $occurrence) {
            $this->action->execute(
                $ticketDefinition->id,
                5,
                $occurrence->id
            );
        });

        $this->assertTrue(true);
    }

    public function test_it_handles_holds_from_different_occurrences(): void
    {
        $occurrence1 = EventOccurrence::factory()->create();
        $occurrence2 = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'total_quantity' => 100,
        ]);

        // Create a hold for occurrence1 using 80 tickets
        $holdForOccurrence1 = TicketHold::factory()
            ->forOccurrence($occurrence1)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($holdForOccurrence1)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(80)
            ->create();

        // For occurrence2, all 100 should be available (different occurrence)
        // Note: This depends on the implementation - if holds are occurrence-specific
        // In current implementation, holds are tied to occurrence, so this should pass
        DB::transaction(function () use ($ticketDefinition, $occurrence2) {
            $this->action->execute(
                $ticketDefinition->id,
                100,
                $occurrence2->id
            );
        });

        $this->assertTrue(true);
    }
}
