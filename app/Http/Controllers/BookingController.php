<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\Booking\InitiateBookingData;
use App\Services\BookingService; // We'll need to create or use an existing BookingService
use App\Services\PaymentService; // We might refactor payment logic into its own service
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    protected BookingService $bookingService;
    // protected PaymentService $paymentService; // If payment logic is moved to a dedicated service

    // Inject services through the constructor
    public function __construct(BookingService $bookingService /*, PaymentService $paymentService */)
    {
        $this->bookingService = $bookingService;
        // $this->paymentService = $paymentService;
    }

    /**
     * Initiate a new booking.
     *
     * Creates booking records with a pending status.
     * If payment is required, it then initiates the payment flow (e.g., Stripe Checkout).
     * If booking is free, it confirms the booking directly.
     *
     * @param \App\DataTransferObjects\Booking\InitiateBookingData $data
     * @return \Illuminate\Http\JsonResponse
     */
    public function initiateBooking(InitiateBookingData $data): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        try {
            // Delegate to BookingService to handle the core logic
            // The service will create Order/Booking records, calculate totals,
            // and determine if payment is needed.
            $result = $this->bookingService->processBookingInitiation($user, $data);

            if ($result['requires_payment']) {
                // If payment is required, the BookingService should have initiated it
                // and $result should contain the checkout_url and booking_id/order_id.
                return response()->json([
                    'requires_payment' => true,
                    'checkout_url' => $result['checkout_url'],
                    'booking_id' => $result['booking_id'], // Or order_id
                    'message' => 'Booking initiated. Please proceed to payment.'
                ]);
            } else {
                // If no payment is required (e.g., free booking)
                return response()->json([
                    'requires_payment' => false,
                    'booking_confirmed' => true,
                    'booking_id' => $result['booking_id'], // Or order_id
                    'message' => $result['message'] ?? 'Booking confirmed successfully.'
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // This might occur if custom validation within the service fails after DTO validation
            Log::warning('Booking initiation validation failed after DTO: ' . $e->getMessage(), ['errors' => $e->errors()]);
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\App\Exceptions\InventoryUnavailableException $e) { // Example custom exception
            Log::notice('Booking initiation failed due to inventory: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 409); // 409 Conflict
        } catch (\Exception $e) {
            Log::error('Error during booking initiation: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $data->toArray(), // Be careful logging sensitive data
            ]);
            return response()->json(['message' => 'An unexpected error occurred while processing your booking. Please try again.'], 500);
        }
    }
}
