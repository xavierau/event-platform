<?php

namespace Tests\Unit\Services;

use App\Enums\BookingStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Event;
use App\Models\TicketDefinition;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = new BookingService();

        // Ensure necessary locales are available for translatable fields
        Config::set('app.available_locales', ['en' => 'English', 'zh-TW' => 'Traditional Chinese']);
        Config::set('app.locale', 'en');
    }

    private function createTestBooking(User $customer, User $organizer, array $bookingData = []): Booking
    {
        $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Test Event'],
            'event_status' => 'published',
            'visibility' => 'public',
        ]);

        $ticketDefinition = TicketDefinition::factory()->create([
            'name' => 'General Admission',
            'price' => 5000, // $50.00 in cents
            'currency' => 'USD',
        ]);

        $transactionData = $bookingData['transaction'] ?? [];
        unset($bookingData['transaction']);

        $transaction = Transaction::factory()->create(array_merge([
            'user_id' => $customer->id,
            'total_amount' => 5000,
            'currency' => 'USD',
            'status' => TransactionStatusEnum::CONFIRMED,
        ], $transactionData));

        $defaultBookingData = [
            'transaction_id' => $transaction->id,
            'event_id' => $event->id,
            'ticket_definition_id' => $ticketDefinition->id,
            'booking_number' => 'BK-' . uniqid(),
            'qr_code_identifier' => 'BK-' . uniqid(),
            'quantity' => 1,
            'price_at_booking' => 5000,
            'currency_at_booking' => 'USD',
            'status' => BookingStatusEnum::CONFIRMED,
            'max_allowed_check_ins' => 1,
        ];

        return Booking::factory()->create(array_merge($defaultBookingData, $bookingData));
    }

    public function test_getAllBookingsWithFilters_returns_all_bookings_for_admin(): void
    {
        $admin = User::factory()->create();
        $organizer1 = User::factory()->create();
        $organizer2 = User::factory()->create();
        $customer = User::factory()->create();

        // Create bookings from different organizers
        $booking1 = $this->createTestBooking($customer, $organizer1);
        $booking2 = $this->createTestBooking($customer, $organizer2);

        $result = $this->bookingService->getAllBookingsWithFilters();

        $this->assertEquals(2, $result->total());
        $bookingIds = $result->pluck('id')->toArray();
        $this->assertContains($booking1->id, $bookingIds);
        $this->assertContains($booking2->id, $bookingIds);
    }

    public function test_getAllBookingsWithFilters_filters_by_search(): void
    {
        $organizer = User::factory()->create();
        $customer1 = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $customer2 = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $booking1 = $this->createTestBooking($customer1, $organizer, ['booking_number' => 'BK-SEARCH123']);
        $booking2 = $this->createTestBooking($customer2, $organizer, ['booking_number' => 'BK-OTHER456']);

        // Search by booking number
        $result = $this->bookingService->getAllBookingsWithFilters(['search' => 'SEARCH123']);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($booking1->id, $result->first()->id);

        // Search by customer name
        $result = $this->bookingService->getAllBookingsWithFilters(['search' => 'John']);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($booking1->id, $result->first()->id);

        // Search by customer email
        $result = $this->bookingService->getAllBookingsWithFilters(['search' => 'jane@example.com']);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($booking2->id, $result->first()->id);
    }

    public function test_getAllBookingsWithFilters_filters_by_status(): void
    {
        $organizer = User::factory()->create();
        $customer = User::factory()->create();

        $confirmedBooking = $this->createTestBooking($customer, $organizer, ['status' => BookingStatusEnum::CONFIRMED]);
        $pendingBooking = $this->createTestBooking($customer, $organizer, ['status' => BookingStatusEnum::PENDING_CONFIRMATION]);

        $result = $this->bookingService->getAllBookingsWithFilters(['status' => BookingStatusEnum::CONFIRMED->value]);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($confirmedBooking->id, $result->first()->id);
    }

    public function test_getAllBookingsWithFilters_filters_by_date_range(): void
    {
        $organizer = User::factory()->create();
        $customer = User::factory()->create();

        // Create booking from 3 days ago
        $oldBooking = $this->createTestBooking($customer, $organizer);
        $oldBooking->created_at = now()->subDays(3);
        $oldBooking->save();

        // Create booking from today
        $newBooking = $this->createTestBooking($customer, $organizer);
        $newBooking->created_at = now();
        $newBooking->save();

        $result = $this->bookingService->getAllBookingsWithFilters([
            'date_from' => now()->subDay()->toDateString(), // From yesterday onwards
        ]);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($newBooking->id, $result->first()->id);
    }

    public function test_getAllBookingsWithFilters_filters_by_event(): void
    {
        $organizer = User::factory()->create();
        $customer = User::factory()->create();

        $booking1 = $this->createTestBooking($customer, $organizer);
        $booking2 = $this->createTestBooking($customer, $organizer);

        $result = $this->bookingService->getAllBookingsWithFilters(['event_id' => $booking1->event_id]);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($booking1->id, $result->first()->id);
    }

    /**
     * CRITICAL TEST: Ensure organizers can only access bookings for their own events
     */
    public function test_getBookingsForOrganizerEventsWithFilters_restricts_to_organizer_events(): void
    {
        $organizer1 = User::factory()->create();
        $organizer2 = User::factory()->create();
        $customer = User::factory()->create();

        // Create bookings for each organizer's events
        $booking1 = $this->createTestBooking($customer, $organizer1);
        $booking2 = $this->createTestBooking($customer, $organizer2);

        // Organizer1 should only see their own event bookings
        $result = $this->bookingService->getBookingsForOrganizerEventsWithFilters($organizer1);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($booking1->id, $result->first()->id);
        $this->assertNotEquals($booking2->id, $result->first()->id);

        // Organizer2 should only see their own event bookings
        $result = $this->bookingService->getBookingsForOrganizerEventsWithFilters($organizer2);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($booking2->id, $result->first()->id);
        $this->assertNotEquals($booking1->id, $result->first()->id);
    }

    /**
     * CRITICAL TEST: Organizer cannot access bookings from events they don't own
     */
    public function test_getBookingsForOrganizerEventsWithFilters_returns_empty_for_unauthorized_organizer(): void
    {
        $organizer1 = User::factory()->create();
        $organizer2 = User::factory()->create();
        $customer = User::factory()->create();

        // Create booking for organizer1's event
        $booking = $this->createTestBooking($customer, $organizer1);

        // Organizer2 should see no bookings as they don't own any events with bookings
        $result = $this->bookingService->getBookingsForOrganizerEventsWithFilters($organizer2);
        $this->assertEquals(0, $result->total());
        $this->assertTrue($result->isEmpty());
    }

    /**
     * CRITICAL TEST: Organizer event filter only works for their own events
     */
    public function test_getBookingsForOrganizerEventsWithFilters_ignores_unauthorized_event_filter(): void
    {
        $organizer1 = User::factory()->create();
        $organizer2 = User::factory()->create();
        $customer = User::factory()->create();

        $booking1 = $this->createTestBooking($customer, $organizer1);
        $booking2 = $this->createTestBooking($customer, $organizer2);

        // Organizer1 tries to filter by organizer2's event - should return no results
        $result = $this->bookingService->getBookingsForOrganizerEventsWithFilters($organizer1, [
            'event_id' => $booking2->event_id
        ]);
        $this->assertEquals(0, $result->total());

        // Organizer1 filters by their own event - should return their booking
        $result = $this->bookingService->getBookingsForOrganizerEventsWithFilters($organizer1, [
            'event_id' => $booking1->event_id
        ]);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($booking1->id, $result->first()->id);
    }

    public function test_getBookingsForOrganizerEventsWithFilters_applies_search_filters(): void
    {
        $organizer = User::factory()->create();
        $customer1 = User::factory()->create(['name' => 'John Doe']);
        $customer2 = User::factory()->create(['name' => 'Jane Smith']);

        $booking1 = $this->createTestBooking($customer1, $organizer, ['booking_number' => 'BK-JOHN123']);
        $booking2 = $this->createTestBooking($customer2, $organizer, ['booking_number' => 'BK-JANE456']);

        // Search should work within organizer's scope
        $result = $this->bookingService->getBookingsForOrganizerEventsWithFilters($organizer, ['search' => 'John']);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($booking1->id, $result->first()->id);
    }

    public function test_getBookingsForOrganizerEventsWithFilters_applies_status_filters(): void
    {
        $organizer = User::factory()->create();
        $customer = User::factory()->create();

        $confirmedBooking = $this->createTestBooking($customer, $organizer, ['status' => BookingStatusEnum::CONFIRMED]);
        $pendingBooking = $this->createTestBooking($customer, $organizer, ['status' => BookingStatusEnum::PENDING_CONFIRMATION]);

        $result = $this->bookingService->getBookingsForOrganizerEventsWithFilters($organizer, [
            'status' => BookingStatusEnum::CONFIRMED->value
        ]);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($confirmedBooking->id, $result->first()->id);
    }

    public function test_getDetailedBooking_loads_all_relationships(): void
    {
        $organizer = User::factory()->create();
        $customer = User::factory()->create();

        $booking = $this->createTestBooking($customer, $organizer);

        $result = $this->bookingService->getDetailedBooking($booking->id);

        $this->assertNotNull($result);
        $this->assertEquals($booking->id, $result->id);
        $this->assertTrue($result->relationLoaded('user'));
        $this->assertTrue($result->relationLoaded('event'));
        $this->assertTrue($result->relationLoaded('ticketDefinition'));
        $this->assertTrue($result->relationLoaded('transaction'));
        $this->assertTrue($result->relationLoaded('checkInLogs'));
    }

    public function test_getDetailedBooking_returns_null_for_non_existent_booking(): void
    {
        $result = $this->bookingService->getDetailedBooking(99999);
        $this->assertNull($result);
    }

    public function test_getEventsForFilter_returns_events_with_bookings(): void
    {
        $organizer1 = User::factory()->create();
        $organizer2 = User::factory()->create();
        $customer = User::factory()->create();

        // Create event with booking
        $bookingWithEvent = $this->createTestBooking($customer, $organizer1);

        // Create event without booking
        $eventWithoutBooking = Event::factory()->create([
            'organizer_id' => $organizer2->id,
            'category_id' => Category::factory()->create(['name' => ['en' => 'Test Category']])->id,
            'name' => ['en' => 'Event Without Bookings'],
        ]);

        $result = $this->bookingService->getEventsForFilter();

        $this->assertEquals(1, $result->count());
        $this->assertEquals($bookingWithEvent->event_id, $result->first()->id);
    }

    /**
     * CRITICAL TEST: Organizer events for filter only returns their own events
     */
    public function test_getOrganizerEventsForFilter_returns_only_organizer_events_with_bookings(): void
    {
        $organizer1 = User::factory()->create();
        $organizer2 = User::factory()->create();
        $customer = User::factory()->create();

        // Create bookings for both organizers
        $booking1 = $this->createTestBooking($customer, $organizer1);
        $booking2 = $this->createTestBooking($customer, $organizer2);

        // Organizer1 should only see their own events
        $result = $this->bookingService->getOrganizerEventsForFilter($organizer1);
        $this->assertEquals(1, $result->count());
        $this->assertEquals($booking1->event_id, $result->first()->id);

        // Organizer2 should only see their own events
        $result = $this->bookingService->getOrganizerEventsForFilter($organizer2);
        $this->assertEquals(1, $result->count());
        $this->assertEquals($booking2->event_id, $result->first()->id);
    }

    public function test_getBookingStatistics_returns_correct_statistics_for_admin(): void
    {
        $organizer1 = User::factory()->create();
        $organizer2 = User::factory()->create();
        $customer = User::factory()->create();

        // Setup bookings with various statuses
        // 4 Confirmed bookings should contribute to revenue
        for ($i = 0; $i < 4; $i++) {
            $this->createTestBooking($customer, $organizer1, [
                'status' => BookingStatusEnum::CONFIRMED,
                'transaction' => ['status' => TransactionStatusEnum::CONFIRMED]
            ]);
        }
        // 1 Pending booking with a pending transaction
        $this->createTestBooking($customer, $organizer1, [
            'status' => BookingStatusEnum::PENDING_CONFIRMATION,
            'transaction' => ['status' => TransactionStatusEnum::PENDING_PAYMENT]
        ]);
        // 1 Cancelled booking
        $this->createTestBooking($customer, $organizer2, [
            'status' => BookingStatusEnum::CANCELLED,
            'transaction' => ['status' => TransactionStatusEnum::CANCELLED]
        ]);

        // Act
        $statistics = $this->bookingService->getBookingStatistics();

        // Assert
        $this->assertEquals(6, $statistics['total_bookings']);
        $this->assertEquals(4, $statistics['confirmed_bookings']);
        $this->assertEquals(1, $statistics['pending_bookings']);
        $this->assertEquals(1, $statistics['cancelled_bookings']);
        // Revenue is calculated from transaction total_amount, and each transaction is $50.00 (5000 cents)
        // Since there are 4 transactions with confirmed status, total should be 4 * $50.00 = $200.00
        $this->assertEquals(20000, $statistics['total_revenue']); // 4 confirmed transactions * $50.00
        $this->assertCount(5, $statistics['recent_bookings']);
    }

    /** @test */
    public function test_getBookingStatistics_scopes_to_organizer_events(): void
    {
        $organizer1 = User::factory()->create();
        $organizer2 = User::factory()->create();
        $customer = User::factory()->create();

        // Create bookings for organizer1
        $this->createTestBooking($customer, $organizer1, [
            'status' => BookingStatusEnum::CONFIRMED,
            'transaction' => ['status' => TransactionStatusEnum::CONFIRMED]
        ]);
        $this->createTestBooking($customer, $organizer1, [
            'status' => BookingStatusEnum::PENDING_CONFIRMATION,
            'transaction' => ['status' => TransactionStatusEnum::PENDING_PAYMENT]
        ]);

        // Create a booking for organizer2 (should be ignored for organizer1's stats)
        $this->createTestBooking($customer, $organizer2, ['status' => BookingStatusEnum::CONFIRMED]);

        // Statistics for organizer1
        $statistics = $this->bookingService->getBookingStatistics($organizer1);

        $this->assertEquals(2, $statistics['total_bookings']);
        $this->assertEquals(1, $statistics['confirmed_bookings']);
        $this->assertEquals(1, $statistics['pending_bookings']);
        // Revenue includes only confirmed transactions for organizer1
        $this->assertEquals(5000, $statistics['total_revenue']); // 1 confirmed transaction * $50.00

        // Statistics for organizer2 should only include their event bookings
        $statistics = $this->bookingService->getBookingStatistics($organizer2);
        $this->assertEquals(1, $statistics['total_bookings']);
        $this->assertEquals(5000, $statistics['total_revenue']);
    }

    public function test_getBookingStatistics_handles_organizer_with_no_events(): void
    {
        $organizerWithoutEvents = User::factory()->create();

        $statistics = $this->bookingService->getBookingStatistics($organizerWithoutEvents);

        $this->assertEquals(0, $statistics['total_bookings']);
        $this->assertEquals(0, $statistics['confirmed_bookings']);
        $this->assertEquals(0, $statistics['pending_bookings']);
        $this->assertEquals(0, $statistics['total_revenue']);
        $this->assertCount(0, $statistics['recent_bookings']);
    }

    public function test_booking_queries_use_correct_relationships(): void
    {
        $organizer = User::factory()->create();
        $customer = User::factory()->create();

        $booking = $this->createTestBooking($customer, $organizer);

        // Test that queries properly load relationships
        $result = $this->bookingService->getAllBookingsWithFilters();
        $firstBooking = $result->first();

        $this->assertTrue($firstBooking->relationLoaded('user'));
        $this->assertTrue($firstBooking->relationLoaded('event'));
        $this->assertTrue($firstBooking->relationLoaded('ticketDefinition'));
        $this->assertTrue($firstBooking->relationLoaded('transaction'));
    }

    public function test_pagination_works_correctly(): void
    {
        $organizer = User::factory()->create();
        $customer = User::factory()->create();

        // Create multiple bookings
        for ($i = 0; $i < 25; $i++) {
            $this->createTestBooking($customer, $organizer);
        }

        $result = $this->bookingService->getAllBookingsWithFilters(['per_page' => 10]);
        $this->assertEquals(25, $result->total());
        $this->assertEquals(10, $result->count());
        $this->assertEquals(3, $result->lastPage());
    }

    public function test_sorting_works_correctly(): void
    {
        $organizer = User::factory()->create();
        $customer = User::factory()->create();

        $booking1 = $this->createTestBooking($customer, $organizer, ['booking_number' => 'BK-AAA']);
        $booking2 = $this->createTestBooking($customer, $organizer, ['booking_number' => 'BK-ZZZ']);

        // Sort by booking number ascending
        $result = $this->bookingService->getAllBookingsWithFilters([
            'sort_by' => 'booking_number',
            'sort_order' => 'asc'
        ]);

        $this->assertEquals($booking1->id, $result->first()->id);

        // Sort by booking number descending
        $result = $this->bookingService->getAllBookingsWithFilters([
            'sort_by' => 'booking_number',
            'sort_order' => 'desc'
        ]);

        $this->assertEquals($booking2->id, $result->first()->id);
    }
}
