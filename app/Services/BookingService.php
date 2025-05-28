<?php

namespace App\Services;

use App\DataTransferObjects\Booking\InitiateBookingData;
use App\Enums\TransactionStatusEnum;
use App\Enums\BookingStatusEnum;
use App\Models\User;
// use App\Models\Order; // Assuming you have an Order model (ORD-001)
use App\Models\Transaction; // Using Transaction model instead of Order
use App\Models\Booking; // Assuming you have a Booking model (ORD-002)
use App\Models\TicketDefinition; // Assuming TicketDefinition model (TCKD-001)
use App\Models\EventOccurrence; // Assuming EventOccurrence model (EVT-002)
use App\Exceptions\InventoryUnavailableException; // Import the custom exception
use Illuminate\Validation\ValidationException; // Import for quantity validation issues
// use App\Actions\Bookings\CreateBookingAction; // As per TRX-004
// use App\Actions\Orders\CreateOrderAction; // If you have a separate action for orders
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use App\DataTransferObjects\BookingData; // Assuming BookingData DTO will be created
use App\Actions\Booking\UpsertBookingAction; // Assuming UpsertBookingAction will be created
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth; // To get the authenticated user if needed directly
use App\Helpers\QrCodeHelper; // Import the QrCodeHelper

class BookingService
{

    /**
     * Processes the booking initiation request.
     *
     * - Validates ticket availability and calculates final prices.
     * - Creates Order and Booking records with an initial status.
     * - If payment is required, initiates Stripe Checkout and returns the URL.
     * - If free, confirms the booking and returns a success message.
     *
     * @param \App\Models\User $user
     * @param \App\DataTransferObjects\Booking\InitiateBookingData $data
     * @return array an array containing keys like 'requires_payment', 'checkout_url', 'booking_id', 'message'
     * @throws \Illuminate\Validation\ValidationException | \App\Exceptions\InventoryUnavailableException | \Exception
     */
    public function processBookingInitiation(User $user, InitiateBookingData $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $occurrence = EventOccurrence::with('event')->findOrFail($data->occurrence_id);
            $totalAmount = 0;
            $currency = null; // Should be determined from tickets or event settings
            $lineItemsForStripe = [];
            $createdBookings = [];

            // 1. Validate items, check availability, calculate total price
            // This is a crucial step. You need to fetch each ticket_definition from the DB,
            // verify its price (don't trust price_at_purchase from DTO for final calculation),
            // check stock (if applicable), and apply any business rules.

            // --- DEBUGGING ---
            if ($data->items === null) {
                dd(
                    'DEBUG: $data->items is NULL'
                );
            } else {
                // Keeping the detailed dd() for now, but commenting it out if the workaround works.
                /* dd(
                'DEBUG: $data->items class is: ' . get_class($data->items),
                'DEBUG: method_exists($data->items, \'isEmpty\'): ' . (method_exists($data->items, 'isEmpty') ? 'true' : 'false'),
                'DEBUG: method_exists($data->items, \'first\'): ' . (method_exists($data->items, 'first') ? 'true' : 'false'),
                'DEBUG: method_exists($data->items, \'getDataClass\'): ' . (method_exists($data->items, 'getDataClass') ? 'true' : 'false'),
                $data->items
                ); */
            }
            // --- END DEBUGGING ---

            if (count($data->items) === 0) {
                // This logic needs to be robust based on your event/ticket structure.
                // If the occurrence itself can be marked as free and allows booking without specific tickets:
                // For this example, we assume if items are empty, it's a free scenario if the occurrence supports it.
                // if (!$occurrence->allow_free_general_admission) { // You'd need such a field or logic
                //     throw ValidationException::withMessages(['items' => 'No items selected for this event occurrence.']);
                // }
                $totalAmount = 0;
                $currency = strtolower(config('cashier.currency'));
            } else {
                foreach ($data->items as $itemData) {
                    $ticketDefinition = TicketDefinition::findOrFail($itemData->ticket_id);
                    $requestedQuantity = $itemData->quantity;

                    // 1. Min/Max per order validation
                    if ($ticketDefinition->min_per_order && $requestedQuantity < $ticketDefinition->min_per_order) {
                        throw ValidationException::withMessages([
                            'items.' . $itemData->ticket_id => "Minimum quantity for {$ticketDefinition->name} is {$ticketDefinition->min_per_order}."
                        ]);
                    }
                    if ($ticketDefinition->max_per_order && $requestedQuantity > $ticketDefinition->max_per_order) {
                        throw ValidationException::withMessages([
                            'items.' . $itemData->ticket_id => "Maximum quantity for {$ticketDefinition->name} is {$ticketDefinition->max_per_order}."
                        ]);
                    }

                    // 2. Inventory Check (using the accessor from TicketDefinition model)
                    // Assumes $ticketDefinition->quantity_available accessor exists and provides current, accurate available stock.
                    // This also assumes that if quantity_available is null, it means infinite stock.
                    if (isset($ticketDefinition->quantity_available) && $requestedQuantity > $ticketDefinition->quantity_available) {
                        throw new InventoryUnavailableException(
                            "Not enough stock for ticket: {$ticketDefinition->name}. Requested: {$requestedQuantity}, Available: {$ticketDefinition->quantity_available}."
                        );
                    }
                    // If $ticketDefinition->quantity_available is null, we assume infinite stock and skip the check.

                    // Use DB price, not $itemData->price_at_purchase for calculation integrity
                    $itemTotal = $ticketDefinition->price * $requestedQuantity;
                    $totalAmount += $itemTotal;
                    $currency = strtolower($ticketDefinition->currency); // Assuming all items in a booking share currency

                    $productDescription = $ticketDefinition->description;
                    if (empty(trim((string) $productDescription))) { // Check if null, empty, or just whitespace
                        $fallbackDescription = trim($occurrence->event->name . ' - ' . $occurrence->name);
                        if (empty($fallbackDescription)) {
                            $productDescription = 'Event Ticket'; // Generic fallback
                        } else {
                            $productDescription = $fallbackDescription;
                        }
                    }

                    $lineItemsForStripe[] = [
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => $ticketDefinition->price, // Price from DB, in cents
                            'product_data' => [
                                'name' => $ticketDefinition->name,
                                'description' => $productDescription, // Use the validated/fallback description
                            ],
                        ],
                        'quantity' => $requestedQuantity,
                    ];
                }
            }

            // Ensure currency is set if not already (e.g. for completely free booking with no items)
            if (is_null($currency)) {
                $currency = strtolower(config('cashier.currency'));
            }

            // 2. Create Order record (if your structure uses one)
            // $order = $this->createOrderAction->execute($user, $totalAmount, $currency, ...);
            // For simplicity, let's assume an Order model exists and we create it directly or via a simpler service method.
            // The Order would also get a status, e.g., TransactionStatusEnum::PENDING_PAYMENT or TransactionStatusEnum::CONFIRMED

            // Mock Order ID for now if not implementing full Order model yet
            $orderId = null; // Replace with actual Order ID if using an Order model
            $transactionStatus = ($totalAmount > 0) ? TransactionStatusEnum::PENDING_PAYMENT : TransactionStatusEnum::CONFIRMED;

            // Determine initial booking status based on transaction status
            $initialBookingStatus = ($transactionStatus === TransactionStatusEnum::CONFIRMED)
                ? BookingStatusEnum::CONFIRMED
                : BookingStatusEnum::PENDING_CONFIRMATION;

            // Example: Creating a simplified Order concept (you should adapt this to your actual Order model and logic)
            // This is highly simplified. Your actual Order creation would be more robust.
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'total_amount' => $totalAmount, // Total amount in cents
                'currency' => $currency,
                'status' => $transactionStatus, // Use the determined transaction status
                // Add other relevant fields: billing_address_id, shipping_address_id, payment_gateway_details etc.
            ]);
            $orderId = $transaction->id; // Use transaction ID as orderId for consistency in this context

            // 3. Create Booking records for each item
            // Link them to the Order if applicable
            if (count($data->items) > 0) {
                foreach ($data->items as $itemData) {
                    $ticketDefinition = TicketDefinition::findOrFail($itemData->ticket_id); // Fetch ticket definition once per item type
                    for ($i = 0; $i < $itemData->quantity; $i++) { // Loop for each unit of this ticket type
                        // This would ideally use a CreateBookingAction as per TRX-004
                        $qrCodeIdentifier = QrCodeHelper::generate();
                        $booking = Booking::create([
                            'user_id' => $user->id,
                            'transaction_id' => $orderId,
                            'event_id' => $occurrence->event->id,
                            'ticket_definition_id' => $itemData->ticket_id,
                            'qr_code_identifier' => $qrCodeIdentifier, // Generate BK- format QR code
                            'booking_number' => $qrCodeIdentifier, // Generate BK- format QR code
                            'quantity' => 1, // Each booking record represents one physical ticket
                            'price_per_unit' => $ticketDefinition->price, // Price from DB
                            'price_at_booking' => $ticketDefinition->price, // Adding the missing price_at_booking field
                            'currency_at_booking' => $currency, // Adding the missing currency_at_booking field
                            'total_price' => $ticketDefinition->price, // Price for one unit
                            'currency' => $currency,
                            'status' => $initialBookingStatus, // Use the determined initial booking status
                            'max_allowed_check_ins' => $ticketDefinition->max_check_ins ?? 1, // Set max check-ins from ticket definition
                            // Consider adding a unique reference/seat number here if applicable per ticket
                        ]);
                        $createdBookings[] = $booking;
                    }
                }
            } else if ($totalAmount == 0) { // Handle general free admission booking if items list is empty
                // Potentially update Order and Booking statuses to CONFIRMED if they were PENDING_CONFIRMATION
                // For now, we set them to CONFIRMED directly above.
                // TODO: Decrement inventory for free tickets here if not handled by an event listener on Booking creation.
                // For example:
                // if (count($data->items) > 0) { // WORKAROUND using count()
                //    foreach ($data->items as $itemData) {
                //        $ticketDef = TicketDefinition::find($itemData->ticket_id);
                //        if ($ticketDef && $ticketDef->total_quantity !== null) {
                //            $ticketDef->decrement('total_quantity', $itemData->quantity);
                //        }
                //    }
                //}
                $qrCodeIdentifier = QrCodeHelper::generate();

                Booking::create([
                    'user_id' => $user->id,
                    'transaction_id' => $orderId,
                    'event_id' => $occurrence->event->id,
                    'qr_code_identifier' => $qrCodeIdentifier,
                    'booking_number' => $qrCodeIdentifier, // Generate BK- format QR code
                    'ticket_definition_id' => null, // No specific ticket
                    'quantity' => 1, // Or based on some logic for general admission
                    'price_per_unit' => 0,
                    'total_price' => 0,
                    'price_at_booking' => 0, // Free booking
                    'currency_at_booking' => $currency, // Adding the missing currency_at_booking field
                    'currency' => $currency,
                    'status' => BookingStatusEnum::CONFIRMED, // Directly confirmed for free general admission
                    'max_allowed_check_ins' => 1, // Default to 1 for free bookings
                ]);
            }

            // 4. If total amount is 0 (free booking)
            if ($totalAmount <= 0) {
                // Potentially update Order and Booking statuses to CONFIRMED if they were PENDING_CONFIRMATION
                // For now, we set them to CONFIRMED directly above.
                // TODO: Decrement inventory for booked tickets if applicable.
                return [
                    'requires_payment' => false,
                    'booking_confirmed' => true,
                    'booking_id' => $orderId, // Or a specific primary booking ID if no Order model
                    // 'message' => 'Your free booking is confirmed.'
                ];
            }

            // 5. If payment is required, initiate Stripe Checkout session
            $metadata = [
                'transaction_id' => $orderId, // Crucial for webhook reconciliation. This IS the transaction_id.
                // Add any other relevant metadata: occurrence_id, user_id (though user is on Stripe session too)
            ];

            try {

                $params = [
                    'success_url' => route('payment.success') . '?transaction_id=' . $orderId . '&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('payment.cancel') . '?transaction_id=' . $orderId . '&session_id={CHECKOUT_SESSION_ID}',
                    'metadata' => $metadata,
                    // 'payment_method_types' => ['card'], // From your PaymentController - usually not needed as Stripe handles this
                ];

                $checkoutSession = $user->checkout($lineItemsForStripe, $params);

                if (!$checkoutSession || !$checkoutSession->id) {
                    throw new \RuntimeException('Failed to create Stripe checkout session: Invalid session response');
                }

                $transaction->payment_gateway = 'stripe';
                $transaction->payment_gateway_transaction_id = $checkoutSession->id;
                $transaction->save();

                return [
                    'requires_payment' => true,
                    'checkout_url' => $checkoutSession->url,
                    'booking_id' => $orderId, // Or primary booking ID
                ];
            } catch (ApiErrorException $e) {
                Log::error('Stripe API Error during checkout session creation in BookingService: ' . $e->getMessage(), ['order_id' => $orderId]);
                // Rollback or mark order/bookings as failed if appropriate here, though transaction should handle DB rollback.
                // For the client, rethrow or throw a custom exception that BookingController can catch.
                throw $e; // Rethrow for BookingController to handle the response
            } catch (\Exception $e) {
                Log::error('General error during payment initiation in BookingService: ' . $e->getMessage(), ['order_id' => $orderId]);
                throw $e; // Rethrow
            }
        });
    }

    /**
     * Get all bookings. Potentially for admin users.
     */
    public function getAllBookings(): Collection
    {
        // return Booking::all(); // Or with pagination: Booking::paginate()
        return Booking::with(['user', 'event'])->get(); // Example: eager load user and event
    }

    /**
     * Get bookings for a specific user.
     */
    public function getBookingsForUser(User $user): Collection
    {
        // Get bookings through the transaction relationship
        return Booking::whereHas('transaction', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with([
            'ticketDefinition.eventOccurrences.event',
            'ticketDefinition.eventOccurrences.venue',
            'transaction'
        ])->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get bookings for events managed by a specific organizer.
     */
    public function getBookingsForOrganizerEvents(User $organizer): Collection
    {
        // Assuming Event model has an 'organizer_id' or similar relationship to User
        // And Booking model has an 'event_id' column
        // This is a simplified example. You might need a more complex query
        // depending on your database schema and relationships.
        // Using `whereRelation` for a more concise and efficient single query.
        // This assumes:
        // 1. The Booking model has an 'event' relationship.
        // 2. The related Event model (or 'events' table) has a column named 'organizer_id'
        //    that stores the ID of the organizer (User).
        // If the column name is different (e.g., 'user_id'), adjust it accordingly.
        // This method is available from Laravel 8+.
        return Booking::whereRelation('event', 'organizer_id', $organizer->id)
            ->with(['user', 'event']) // Eager load related user and event for each booking
            ->get();
    }

    /**
     * Find a specific booking by ID.
     */
    public function findBooking(int $bookingId): ?Booking
    {
        return Booking::with(['user', 'event'])->find($bookingId);
    }


    /**
     * Delete a booking.
     */
    public function deleteBooking(int $bookingId): bool
    {
        $booking = $this->findBooking($bookingId);
        if (!$booking) {
            return false; // Or throw an exception
        }
        return $booking->delete();
    }

    // TODO: Add other methods like cancelBooking, updateBookingStatus, etc.
}
