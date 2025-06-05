<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\Transaction;
use App\Enums\BookingStatusEnum;
use App\Enums\TransactionStatusEnum;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing data
        $transactions = Transaction::where('status', TransactionStatusEnum::CONFIRMED)->get();
        $events = Event::with('eventOccurrences.ticketDefinitions')->get();

        if ($transactions->isEmpty()) {
            $this->command->warn('No confirmed transactions found. Please run TransactionSeeder first.');
            return;
        }

        if ($events->isEmpty()) {
            $this->command->warn('No events found. Please run EventSeeder first.');
            return;
        }

        // Create bookings for confirmed transactions
        foreach ($transactions as $transaction) {
            // Each transaction gets 1-4 bookings
            $bookingCount = rand(1, 4);
            $totalBookingAmount = 0;

            for ($i = 0; $i < $bookingCount; $i++) {
                // Pick a random event with ticket definitions
                $eventWithTickets = $events->filter(function ($event) {
                    return $event->eventOccurrences->isNotEmpty() &&
                        $event->eventOccurrences->flatMap->ticketDefinitions->isNotEmpty();
                })->random();

                if (!$eventWithTickets) {
                    continue;
                }

                // Get a random ticket definition from the event's occurrences
                $ticketDefinition = $eventWithTickets->eventOccurrences
                    ->flatMap->ticketDefinitions
                    ->random();

                $priceAtBooking = $ticketDefinition->price;
                $totalBookingAmount += $priceAtBooking;

                Booking::factory()
                    ->for($transaction)
                    ->for($eventWithTickets, 'event')
                    ->for($ticketDefinition, 'ticketDefinition')
                    ->create([
                        'quantity' => 1, // Always 1 as per project requirements
                        'price_at_booking' => $priceAtBooking,
                        'currency_at_booking' => $ticketDefinition->currency,
                        'status' => $this->getBookingStatusFromTransaction($transaction),
                        'max_allowed_check_ins' => $ticketDefinition->max_check_ins ?? 1,
                        'created_at' => $transaction->created_at->addMinutes(rand(1, 30)),
                    ]);
            }

            // Update transaction total to match booking amounts
            $transaction->update(['total_amount' => $totalBookingAmount]);
        }

        // Create some specific test scenarios
        $this->createTestScenarios();

        $this->command->info('Bookings seeded successfully!');
    }

    /**
     * Create specific test scenarios for booking statuses.
     */
    private function createTestScenarios(): void
    {
        $events = Event::with('eventOccurrences.ticketDefinitions')->get();

        if ($events->isEmpty()) {
            return;
        }

        $firstEvent = $events->first();
        $ticketDefinition = $firstEvent->eventOccurrences
            ->flatMap->ticketDefinitions
            ->first();

        if (!$ticketDefinition) {
            return;
        }

        // Get the first user's transaction
        $firstTransaction = Transaction::with('user')->first();

        if (!$firstTransaction) {
            return;
        }

        // Create specific status scenarios
        $scenarios = [
            ['status' => BookingStatusEnum::CONFIRMED, 'notes' => 'Standard confirmed booking'],
            ['status' => BookingStatusEnum::PENDING_CONFIRMATION, 'notes' => 'Pending payment confirmation'],
            ['status' => BookingStatusEnum::USED, 'notes' => 'Customer attended event'],
            ['status' => BookingStatusEnum::CANCELLED, 'notes' => 'Cancelled due to personal reasons'],
        ];

        foreach ($scenarios as $scenario) {
            Booking::factory()
                ->for($firstTransaction)
                ->for($firstEvent, 'event')
                ->for($ticketDefinition, 'ticketDefinition')
                ->create([
                    'status' => $scenario['status'],
                    'quantity' => 1,
                    'price_at_booking' => $ticketDefinition->price,
                    'currency_at_booking' => $ticketDefinition->currency,
                    'metadata' => ['notes' => $scenario['notes']],
                    'max_allowed_check_ins' => 1,
                ]);
        }
    }

    /**
     * Get appropriate booking status based on transaction status.
     */
    private function getBookingStatusFromTransaction(Transaction $transaction): BookingStatusEnum
    {
        return match ($transaction->status) {
            TransactionStatusEnum::CONFIRMED => $this->getRandomConfirmedBookingStatus(),
            TransactionStatusEnum::PENDING_PAYMENT => BookingStatusEnum::PENDING_CONFIRMATION,
            TransactionStatusEnum::PENDING_CONFIRMATION => BookingStatusEnum::PENDING_CONFIRMATION,
            TransactionStatusEnum::CANCELLED => BookingStatusEnum::CANCELLED,
            TransactionStatusEnum::REFUNDED => BookingStatusEnum::CANCELLED,
            default => BookingStatusEnum::CONFIRMED,
        };
    }

    /**
     * Get a random booking status for confirmed transactions.
     */
    private function getRandomConfirmedBookingStatus(): BookingStatusEnum
    {
        $random = rand(1, 100);

        if ($random <= 60) {
            return BookingStatusEnum::CONFIRMED;
        } elseif ($random <= 85) { // 25% chance
            return BookingStatusEnum::USED;
        } else { // 15% chance
            return BookingStatusEnum::CANCELLED;
        }
    }
}
