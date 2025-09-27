<?php

use App\Models\Booking;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Venue;
use App\Models\Organizer;
use App\Services\GoogleAnalyticsService;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->gaService = app(GoogleAnalyticsService::class);
});

describe('GoogleAnalyticsService', function () {
    describe('formatTransactionForGA4', function () {
        it('formats transaction data correctly for GA4', function () {
            // Arrange
            $user = User::factory()->create();
            $transaction = Transaction::factory()->create([
                'user_id' => $user->id,
                'total_amount' => 5000, // $50.00 in cents
                'currency' => 'USD',
            ]);

            $organizer = Organizer::factory()->create(['name' => 'Test Organizer']);
            $venue = Venue::factory()->create(['name' => 'Test Venue']);
            $category = Category::factory()->create(['name' => 'Music']);
            $event = Event::factory()->create([
                'name' => 'Test Event',
                'organizer_id' => $organizer->id,
                'category_id' => $category->id,
            ]);
            $eventOccurrence = EventOccurrence::factory()->create([
                'event_id' => $event->id,
                'venue_id' => $venue->id,
            ]);
            $ticketDefinition = TicketDefinition::factory()->create([
                'name' => 'VIP Ticket',
            ]);

            $booking = Booking::factory()->create([
                'transaction_id' => $transaction->id,
                'event_id' => $event->id,
                'ticket_definition_id' => $ticketDefinition->id,
                'price_at_booking' => 5000,
                'quantity' => 1,
            ]);

            $bookings = Booking::where('transaction_id', $transaction->id)->get();

            // Load relationships
            $bookings->load([
                'ticketDefinition',
                'event.organizer',
                'event.category'
            ]);

            // Act
            $result = $this->gaService->formatTransactionForGA4($transaction, $bookings, $user);

            // Assert
            expect($result)->toHaveKeys([
                'transaction_id',
                'value',
                'currency',
                'affiliation',
                'tax',
                'shipping',
                'items',
                'user_id'
            ]);

            expect($result['transaction_id'])->toBe((string) $transaction->id);
            expect($result['value'])->toBe(50.0); // Converted from cents
            expect($result['currency'])->toBe('USD');
            expect($result['user_id'])->toBe((string) $user->id);
            expect($result['items'])->toHaveCount(1);

            $item = $result['items'][0];
            expect($item['item_id'])->toBe("ticket_{$ticketDefinition->id}");
            expect($item['item_name'])->toBe('VIP Ticket');
            expect($item['item_category'])->toBe('Event Ticket');
            expect($item['item_category2'])->toBe($category->name);
            expect($item['item_brand'])->toBe('Test Organizer');
            expect($item['price'])->toBe(50.0);
            expect($item['quantity'])->toBe(1);
            expect($item['item_variant'])->toBe('standard');
        });

        it('handles transaction without user', function () {
            $transaction = Transaction::factory()->create([
                'total_amount' => 2500,
                'currency' => 'EUR',
            ]);

            $booking = Booking::factory()->create([
                'transaction_id' => $transaction->id,
                'price_at_booking' => 2500,
                'quantity' => 2,
            ]);

            $bookings = Booking::where('transaction_id', $transaction->id)->get();

            $result = $this->gaService->formatTransactionForGA4($transaction, $bookings);

            expect($result)->not->toHaveKey('user_id');
            expect($result['value'])->toBe(25.0);
            expect($result['currency'])->toBe('EUR');
        });
    });

    describe('formatUserPropertiesForGA4', function () {
        it('formats basic user properties', function () {
            $user = User::factory()->create([
                'created_at' => now()->subYears(2),
            ]);

            $result = $this->gaService->formatUserPropertiesForGA4($user);

            expect($result)->toHaveKeys([
                'user_type',
                'membership_tier',
                'membership_status',
                'customer_since'
            ]);

            expect($result['user_type'])->toBe('customer');
            expect($result['membership_tier'])->toBe('none');
            expect($result['membership_status'])->toBe('none');
            expect($result['customer_since'])->toBe($user->created_at->format('Y-m-d'));
        });
    });

    describe('generatePurchaseTrackingScript', function () {
        it('generates valid JavaScript for purchase tracking', function () {
            $transactionData = [
                'transaction_id' => '123',
                'value' => 50.0,
                'currency' => 'USD',
                'items' => []
            ];

            $script = $this->gaService->generatePurchaseTrackingScript($transactionData);

            expect($script)->toContain("gtag('event', 'purchase'");
            expect($script)->toContain('"transaction_id":"123"');
            expect($script)->toContain('"value":50');
            expect($script)->toContain('"currency":"USD"');
            expect($script)->toContain('console.log');
        });
    });

    describe('generateUserPropertiesScript', function () {
        it('generates valid JavaScript for user properties', function () {
            $userProperties = [
                'user_type' => 'customer',
                'membership_tier' => 'premium'
            ];
            $userId = '456';

            $script = $this->gaService->generateUserPropertiesScript($userProperties, $userId);

            expect($script)->toContain("gtag('set', { 'user_id': '456' })");
            expect($script)->toContain("gtag('set', 'user_properties'");
            expect($script)->toContain('"user_type":"customer"');
            expect($script)->toContain('"membership_tier":"premium"');
        });
    });

    describe('validateTransactionData', function () {
        it('validates valid transaction data', function () {
            $validData = [
                'transaction_id' => '123',
                'value' => 50.0,
                'currency' => 'USD',
                'items' => [
                    ['item_id' => 'ticket_1', 'item_name' => 'VIP Ticket']
                ]
            ];

            $errors = $this->gaService->validateTransactionData($validData);

            expect($errors)->toBeEmpty();
        });

        it('identifies missing required fields', function () {
            $invalidData = [
                'value' => -10,
                'currency' => '',
                'items' => []
            ];

            $errors = $this->gaService->validateTransactionData($invalidData);

            expect($errors)->toContain('transaction_id is required');
            expect($errors)->toContain('value must be a non-negative number');
            expect($errors)->toContain('currency is required and must be a string');
            expect($errors)->toContain('items array is required and cannot be empty');
        });
    });

    describe('isTrackingEnabled', function () {
        it('returns true when analytics ID is configured', function () {
            config(['services.google.analytics_id' => 'GA-TEST-123']);

            expect($this->gaService->isTrackingEnabled())->toBeTrue();
        });

        it('returns false when analytics ID is not configured', function () {
            config(['services.google.analytics_id' => null]);

            expect($this->gaService->isTrackingEnabled())->toBeFalse();
        });
    });

    describe('formatBookingsAsGA4Items', function () {
        it('formats multiple bookings correctly', function () {
            $organizer = Organizer::factory()->create(['name' => 'Event Organizer']);
            $venue = Venue::factory()->create(['name' => 'Concert Hall']);
            $category = Category::factory()->create(['name' => 'Music']);
            $event = Event::factory()->create([
                'name' => 'Rock Concert',
                'organizer_id' => $organizer->id,
                'category_id' => $category->id,
            ]);
            $eventOccurrence = EventOccurrence::factory()->create([
                'event_id' => $event->id,
                'venue_id' => $venue->id,
            ]);

            $vipTicket = TicketDefinition::factory()->create([
                'name' => 'VIP Access',
            ]);
            $generalTicket = TicketDefinition::factory()->create([
                'name' => 'General Admission',
            ]);

            $vipBooking = Booking::factory()->create([
                'event_id' => $event->id,
                'ticket_definition_id' => $vipTicket->id,
                'price_at_booking' => 10000, // $100
                'quantity' => 1,
            ]);

            $generalBooking = Booking::factory()->create([
                'event_id' => $event->id,
                'ticket_definition_id' => $generalTicket->id,
                'price_at_booking' => 5000, // $50
                'quantity' => 2,
            ]);

            $bookings = Booking::whereIn('id', [$vipBooking->id, $generalBooking->id])->get();

            // Load relationships
            $bookings->load([
                'ticketDefinition',
                'event.organizer',
                'event.category'
            ]);

            $result = $this->gaService->formatBookingsAsGA4Items($bookings);

            expect($result)->toHaveCount(2);

            // Check VIP ticket
            $vipItem = $result[0];
            expect($vipItem['item_id'])->toBe("ticket_{$vipTicket->id}");
            expect($vipItem['item_name'])->toBe('VIP Access');
            expect($vipItem['item_category'])->toBe('Event Ticket');
            expect($vipItem['item_category2'])->toBe($category->name);
            expect($vipItem['item_brand'])->toBe('Event Organizer');
            expect($vipItem['price'])->toBe(100.0);
            expect($vipItem['quantity'])->toBe(1);
            expect($vipItem['item_variant'])->toBe('standard');

            // Check custom parameters
            expect($vipItem['custom_parameters'])->toHaveKeys([
                'event_id',
                'event_name',
                'booking_id'
            ]);
            expect($vipItem['custom_parameters']['event_name'])->toBe('Rock Concert');

            // Check General ticket
            $generalItem = $result[1];
            expect($generalItem['item_name'])->toBe('General Admission');
            expect($generalItem['price'])->toBe(50.0);
            expect($generalItem['quantity'])->toBe(2);
            expect($generalItem['item_variant'])->toBe('standard');
        });

        it('handles bookings with missing relationships gracefully', function () {
            // Create a temporary ticket definition and event, then delete them to test graceful fallbacks
            $tempTicket = TicketDefinition::factory()->create(['name' => 'Temp Ticket']);
            $tempEvent = Event::factory()->create(['name' => 'Temp Event']);

            $booking = Booking::factory()->create([
                'ticket_definition_id' => $tempTicket->id,
                'event_id' => $tempEvent->id,
                'price_at_booking' => 3000,
                'quantity' => 1,
            ]);

            // Delete the ticket and event to simulate missing relationships
            $tempTicket->delete();
            $tempEvent->delete();

            $bookings = Booking::where('id', $booking->id)->get();

            $result = $this->gaService->formatBookingsAsGA4Items($bookings);

            expect($result)->toHaveCount(1);

            $item = $result[0];
            expect($item['item_name'])->toBe('General Admission'); // Default fallback
            expect($item['item_category2'])->toBe('General'); // Default fallback because no event/category relationship
            expect($item['item_brand'])->toBe('Event Platform'); // Default fallback
            expect($item['price'])->toBe(30.0);
            expect($item['quantity'])->toBe(1);
            expect($item['item_variant'])->toBe('standard'); // Default fallback
        });
    });
});