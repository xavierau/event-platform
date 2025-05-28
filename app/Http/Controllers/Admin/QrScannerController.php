<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QrCodeValidationService;
use App\DataTransferObjects\CheckInData;
use App\Models\EventOccurrence;
use App\Models\Event;
use App\Enums\RoleNameEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class QrScannerController extends Controller
{
    public function __construct(
        private QrCodeValidationService $qrCodeValidationService
    ) {
        // Middleware will be applied at the route level
    }

    /**
     * Display the QR scanner page
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // Get events based on user role
        $events = $this->getAccessibleEvents($user);

        return Inertia::render('Admin/QrScanner/Index', [
            'events' => $events,
            'user_role' => $user->roles->first()?->name,
        ]);
    }

    /**
     * Validate QR code and return booking information
     */
    public function validateQrCode(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string',
            'event_id' => 'nullable|integer|exists:events,id',
        ]);

        $qrCode = $request->input('qr_code');
        $eventId = $request->input('event_id');

        // Validate QR code format and find booking
        $validation = $this->qrCodeValidationService->validateQrCode($qrCode);

        if (!$validation['is_valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR code',
                'errors' => $validation['errors'],
            ], 400);
        }

        $booking = $validation['booking'];

        // Check if user has permission to access this booking's event
        if (!$this->canAccessBooking($booking, Auth::user(), $eventId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this booking',
            ], 403);
        }

        // Get event occurrences for this booking's event
        $eventOccurrences = EventOccurrence::where('event_id', $booking->event_id)
            ->orderBy('start_at')
            ->get()
            ->map(function ($occurrence) {
                return [
                    'id' => $occurrence->id,
                    'name' => $occurrence->name,
                    'start_at' => $occurrence->start_at,
                    'end_at' => $occurrence->end_at,
                    'venue_name' => $occurrence->venue_name,
                ];
            });

        return response()->json([
            'success' => true,
            'booking' => [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'status' => $booking->status,
                'quantity' => $booking->quantity,
                'total_price' => $booking->total_price,
                'currency' => $booking->currency,
                'created_at' => $booking->created_at,
                'user' => [
                    'id' => $booking->user->id,
                    'name' => $booking->user->name,
                    'email' => $booking->user->email,
                ],
                'event' => [
                    'id' => $booking->event->id,
                    'name' => $booking->event->name,
                ],
                'ticket_definition' => $booking->ticketDefinition ? [
                    'id' => $booking->ticketDefinition->id,
                    'name' => $booking->ticketDefinition->name,
                ] : null,
            ],
            'event_occurrences' => $eventOccurrences,
        ]);
    }

    /**
     * Process check-in for a booking
     */
    public function checkIn(Request $request)
    {
        try {
            $checkInData = CheckInData::from($request->all());

            // Additional validation: ensure user has permission
            $booking = \App\Models\Booking::byQrCode($checkInData->qr_code_identifier)->first();

            if (!$booking || !$this->canAccessBooking($booking, Auth::user())) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to check in this booking',
                ], 403);
            }

            // TODO: Implement check-in logic using CheckInService
            // This would be implemented in a future task

            return response()->json([
                'success' => true,
                'message' => 'Check-in functionality will be implemented in a future update',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid check-in data',
                'errors' => [$e->getMessage()],
            ], 400);
        }
    }

    /**
     * Get events accessible to the current user
     */
    private function getAccessibleEvents($user)
    {
        $query = Event::with(['media'])
            ->select(['id', 'name', 'organizer_id', 'event_status'])
            ->where('event_status', 'published');

        // Platform admins can see all events
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return $query->get();
        }

        // Organizers can only see their own events
        if ($user->hasRole(RoleNameEnum::ORGANIZER)) {
            return $query->where('organizer_id', $user->id)->get();
        }

        return collect();
    }

    /**
     * Check if user can access a specific booking
     */
    private function canAccessBooking($booking, $user, $eventId = null): bool
    {
        // Platform admins can access any booking
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Organizers can only access bookings for their events
        // TODO if future the organizer has many users then need to check if the user is the organizer of the event
        if ($user->hasRole(RoleNameEnum::ORGANIZER)) {
            return $booking->event->organizer_id === $user->id;
        }

        return false;
    }
}
