<?php

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\CheckInData;
use App\Enums\RoleNameEnum;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Services\CheckInService;
use App\Services\QrCodeValidationService;
use App\Traits\CheckInLoggable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class QrScannerController extends Controller
{
    use CheckInLoggable;
    public function __construct(
        private QrCodeValidationService $qrCodeValidationService,
        private CheckInService $checkInService
    ) {
        // Middleware will be applied at the route level
    }

    /**
     * Display the QR scanner page
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        Log::channel('qr_scanner')->info('[QR_SCANNER] Page access attempted', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_roles' => $user->roles->pluck('name')->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Check authorization: only admins or users with organizer entity membership can access
        if (! $user->hasRole(RoleNameEnum::ADMIN)) {
            if (! $user->activeOrganizers()->exists()) {
                Log::channel('qr_scanner')->warning('[QR_SCANNER] Access denied - no organizer membership', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
                abort(403, 'You do not have permission to access the QR scanner.');
            }
        }

        // Get events based on user role
        $events = $this->getAccessibleEvents($user);

        Log::channel('qr_scanner')->info('[QR_SCANNER] Page loaded successfully', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->roles->first()?->name,
            'events_count' => count($events),
            'is_platform_admin' => $user->hasRole(RoleNameEnum::ADMIN),
            'active_organizers_count' => $user->activeOrganizers()->count(),
        ]);

        return Inertia::render('Admin/QrScanner/Index', [
            'events' => $events,
            'roles' => [
                'ADMIN' => RoleNameEnum::ADMIN->value,
                'USER' => RoleNameEnum::USER->value,
            ],
            'user_role' => $user->roles->first()?->name,
            'pageTitle' => 'QR Code Scanner',
            'breadcrumbs' => [
                ['text' => 'Dashboard', 'href' => route('admin.dashboard')],
                ['text' => 'QR Code Scanner'],
            ],
        ]);
    }

    /**
     * Validate QR code and return booking information with usage history
     */
    public function validateQrCode(Request $request)
    {
        Log::channel('qr_scanner')->info('[VALIDATION] Request received', [
            'qr_code' => $request->input('qr_code'),
            'event_id' => $request->input('event_id'),
            'user_id' => Auth::id(),
        ]);

        $request->validate([
            'qr_code' => 'required|string',
            'event_id' => 'nullable|integer|exists:events,id',
        ]);

        $qrCode = $request->input('qr_code');
        $eventId = $request->input('event_id');

        // Validate QR code format and find booking
        $validation = $this->qrCodeValidationService->validateQrCode($qrCode);

        if (! $validation['is_valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR code',
                'errors' => $validation['errors'],
            ], 400);
        }

        $booking = $validation['booking'];

        // Check if user has permission to access this booking's event
        if (! $this->canAccessBooking($booking, Auth::user(), $eventId)) {
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

        // Get check-in history for this booking (filtered by user's organizer access)
        $checkInHistory = $this->checkInService->getCheckInHistory($booking, Auth::user());

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
                'seat_number' => $booking->metadata['seat_number'] ?? null,
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
            'check_in_history' => $checkInHistory,
        ]);
    }

    /**
     * Process check-in for a booking
     */
    public function checkIn(Request $request)
    {
        $this->logMethodEntry('BOOKING_CHECKIN', __METHOD__, [
            'request_data' => $request->except(['password', 'token']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            $checkInData = CheckInData::from($request->all());

            $this->logValidation('BOOKING_CHECKIN', 'CheckInData DTO created successfully', [
                'qr_code_identifier' => $checkInData->qr_code_identifier,
                'event_occurrence_id' => $checkInData->event_occurrence_id,
                'method' => $checkInData->method->value,
                'device_identifier' => $checkInData->device_identifier,
            ]);

            // Additional validation: ensure user has permission
            $this->logDatabaseOperation('BOOKING_CHECKIN', 'Looking up booking by QR code', [
                'qr_code_identifier' => $checkInData->qr_code_identifier,
            ]);

            $booking = \App\Models\Booking::byQrCode($checkInData->qr_code_identifier)->first();

            // If not found by qr_code_identifier, try booking_number (for legacy QR codes)
            if (! $booking) {
                $this->logBusinessLogic('BOOKING_CHECKIN', 'Booking not found by QR code, trying booking number', [
                    'identifier' => $checkInData->qr_code_identifier,
                ]);

                $booking = \App\Models\Booking::where('booking_number', $checkInData->qr_code_identifier)->first();
            }

            if (! $booking) {
                $this->logValidation('BOOKING_CHECKIN', 'Booking not found', [
                    'qr_code_identifier' => $checkInData->qr_code_identifier,
                ], false);

                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found',
                ], 404);
            }

            $this->logDatabaseOperation('BOOKING_CHECKIN', 'Booking found', [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'booking_status' => $booking->status->value,
                'event_id' => $booking->event_id,
                'organizer_id' => $booking->event->organizer_id,
            ]);

            $user = Auth::user();
            $canAccess = $this->canAccessBooking($booking, $user);

            $this->logAuthorization('BOOKING_CHECKIN', 'Booking access check completed', [
                'user_id' => $user->id,
                'booking_id' => $booking->id,
                'can_access' => $canAccess,
            ], $canAccess);

            if (! $canAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to check in this booking',
                ], 403);
            }

            // Process the check-in using the CheckInService
            $this->logBusinessLogic('BOOKING_CHECKIN', 'Calling CheckInService to process check-in', [
                'service' => CheckInService::class,
                'booking_id' => $booking->id,
            ]);

            $result = $this->checkInService->processCheckIn($checkInData);

            if ($result['success']) {
                $this->logMethodExit('BOOKING_CHECKIN', __METHOD__, [
                    'status' => 'success',
                    'booking_id' => $booking->id,
                    'check_in_status' => $result['status']->value ?? null,
                ]);

                // Return 204 No Content for successful check-in as requested
                return response()->noContent();
            } else {
                $this->logBusinessLogic('BOOKING_CHECKIN', 'Check-in failed', [
                    'booking_id' => $booking->id,
                    'failure_message' => $result['message'],
                    'status' => $result['status']->value ?? null,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'status' => $result['status']->value ?? null,
                ], 400);
            }
        } catch (\Exception $e) {
            $this->logCheckInError('BOOKING_CHECKIN', 'Exception during check-in', $e, [
                'request_data' => $request->except(['password', 'token']),
            ]);

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

        // Users with organizer entity memberships can see events from organizer entities they belong to
        $userOrganizerIds = $user->activeOrganizers()->pluck('organizers.id');

        if ($userOrganizerIds->isNotEmpty()) {
            return $query->whereIn('organizer_id', $userOrganizerIds)->get();
        }

        return collect();
    }

    /**
     * Check if user can access a specific booking
     */
    private function canAccessBooking($booking, $user, $eventId = null): bool
    {
        Log::channel('qr_scanner')->info('[AUTH] Checking access for booking', [
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'booking_event_organizer_id' => $booking->event->organizer_id,
        ]);

        // Platform admins can access any booking
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            Log::channel('qr_scanner')->info('[AUTH] Access granted: User is Platform Admin', [
                'user_id' => $user->id,
            ]);
            return true;
        }

        // Users with organizer entity memberships can access bookings for events from organizer entities they belong to
        $userOrganizerIds = $user->activeOrganizers()->pluck('organizers.id');

        $canAccess = $userOrganizerIds->contains($booking->event->organizer_id);

        Log::channel('qr_scanner')->info('[AUTH] Organizer access check result', [
            'user_id' => $user->id,
            'user_organizer_ids' => $userOrganizerIds->toArray(),
            'booking_event_organizer_id' => $booking->event->organizer_id,
            'can_access' => $canAccess,
        ]);

        return $canAccess;
    }
}
