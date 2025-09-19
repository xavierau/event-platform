<?php

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\Booking\ManualBookingData;
use App\Enums\RoleNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManualBookingRequest;
use App\Services\BookingService;
use App\Services\ManualBookingService;
use Illuminate\Http\Request;
use App\Enums\BookingStatusEnum;

class BookingController extends Controller
{
    protected BookingService $bookingService;
    protected ManualBookingService $manualBookingService;

    public function __construct(BookingService $bookingService, ManualBookingService $manualBookingService)
    {
        $this->bookingService = $bookingService;
        $this->manualBookingService = $manualBookingService;
    }

    /**
     * Display a listing of the resource with enhanced filtering.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'event_id', 'user_id', 'date_from', 'date_to', 'sort_by', 'sort_order', 'per_page']);

        if ($user->hasRole(RoleNameEnum::ADMIN->value)) {
            $bookings = $this->bookingService->getPaginatedBookings($filters);
            $events = $this->bookingService->getEventsForFilter();
            $statistics = $this->bookingService->getBookingStatistics();
        } elseif ($user->activeOrganizers()->exists()) {
            // User is a member of at least one organizer
            $bookings = $this->bookingService->getPaginatedBookingsForOrganizer($user, $filters);
            $events = $this->bookingService->getOrganizerEventsForFilter($user);
            $statistics = $this->bookingService->getBookingStatistics($user);
        } else {
            return redirect()->route('my-bookings');
        }

        $statuses = collect(BookingStatusEnum::cases())->map(fn($status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ]);

        return inertia('Admin/Bookings/Index', [
            'bookings' => $bookings,
            'events' => $events,
            'statuses' => $statuses,
            'statistics' => $statistics,
            'filters' => $filters,
            'canCreateManualBooking' => $user->hasRole(RoleNameEnum::ADMIN->value),
            'pageTitle' => __('bookings.index_title'),
            'breadcrumbs' => [
                ['title' => __('common.admin'), 'href' => route('admin.dashboard')],
                ['title' => __('bookings.index_title')]
            ]
        ]);
    }

    /**
     * Show the form for creating a new manual booking.
     */
    public function create()
    {
        // Use policy to check authorization
        $this->authorize('createManual', \App\Models\Booking::class);

        $formData = $this->manualBookingService->getFormData();

        return inertia('Admin/Bookings/Create', [
            'users' => $formData['users'],
            'events' => $formData['events'],
            'currencies' => $formData['currencies'],
            'pageTitle' => __('Create Manual Booking'),
            'breadcrumbs' => [
                ['title' => __('common.admin'), 'href' => route('admin.dashboard')],
                ['title' => __('bookings.index_title'), 'href' => route('admin.bookings.index')],
                ['title' => __('Create Manual Booking')]
            ]
        ]);
    }

    /**
     * Store a newly created manual booking.
     */
    public function store(ManualBookingRequest $request)
    {
        // Use policy to check authorization (the request already handles this too)
        $this->authorize('createManual', \App\Models\Booking::class);

        try {
            $data = ManualBookingData::from([
                ...$request->validated(),
                'created_by_admin_id' => auth()->id(),
            ]);

            $result = $this->manualBookingService->createManualBooking($data);

            $message = $result['message'] . ' ' . implode(', ', $result['booking_numbers']);

            return redirect()
                ->route('admin.bookings.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            \Log::error('Manual booking creation failed', [
                'admin_id' => auth()->id(),
                'request_data' => $request->validated(),
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['general' => __('Failed to create manual booking. Please try again.')]);
        }
    }

    /**
     * Display the specified resource with detailed information.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $booking = $this->bookingService->getDetailedBooking((int)$id);

        if (!$booking) {
            return redirect()->route('admin.bookings.index')->with('error', 'Booking not found.');
        }

        // Check if user has permission to view this booking
        if (!$user->hasRole(RoleNameEnum::ADMIN->value)) {
            // Check if user is a member of the event's organizer
            $eventOrganizer = $booking->event->organizer;
            if ($user->isMemberOfOrganizer($eventOrganizer)) {
                // User is authorized as an organizer member
            } else {
                // Regular users should only see their own bookings
                if ($booking->transaction->user_id !== $user->id) {
                    return redirect()->route('my-bookings')->with('error', 'Unauthorized to view this booking.');
                }
            }
        }

        return inertia('Admin/Bookings/Show', [
            'booking' => $booking,
            'pageTitle' => 'Booking Details - ' . $booking->booking_number,
            'breadcrumbs' => [
                ['title' => 'Admin', 'href' => route('admin.dashboard')],
                ['title' => 'Bookings', 'href' => route('admin.bookings.index')],
                ['title' => 'Booking #' . $booking->booking_number]
            ]
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $booking = $this->bookingService->getDetailedBooking((int)$id);

        if (!$booking) {
            return redirect()->route('admin.bookings.index')->with('error', 'Booking not found.');
        }

        return inertia('Admin/Bookings/Edit', [
            'booking' => $booking,
            'pageTitle' => 'Edit Booking - ' . $booking->booking_number,
            'breadcrumbs' => [
                ['title' => 'Admin', 'href' => route('admin.dashboard')],
                ['title' => 'Bookings', 'href' => route('admin.bookings.index')],
                ['title' => 'Edit #' . $booking->booking_number]
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // TODO: Implement booking update logic
        // $bookingData = BookingData::from($request->all());
        // $this->bookingService->updateBooking((int)$id, $bookingData);
        // return redirect()->route('admin.bookings.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // TODO: Implement booking deletion logic (if allowed)
        // $this->bookingService->deleteBooking((int)$id);
        // return redirect()->route('admin.bookings.index');
    }

    /**
     * Export bookings as CSV.
     */
    public function export(Request $request)
    {
        // TODO: Implement CSV export functionality
        // This would be useful for admin reporting
    }

    /**
     * Search users for manual booking creation (AJAX endpoint).
     */
    public function searchUsers(Request $request)
    {
        // Use policy to check authorization
        $this->authorize('createManual', \App\Models\Booking::class);

        $request->validate([
            'search' => 'required|string|min:2|max:255',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        $searchTerm = $request->input('search');
        $limit = $request->input('limit', 20);

        // Search users by name or email
        $users = \App\Models\User::where(function ($query) use ($searchTerm) {
            $query->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%");
        })
        ->select('id', 'name', 'email')
        ->limit($limit)
        ->orderBy('name')
        ->get();

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }

    /**
     * Get ticket definitions for a specific event (AJAX endpoint).
     */
    public function getTicketDefinitions(Request $request, int $eventId)
    {
        // Use policy to check authorization
        $this->authorize('createManual', \App\Models\Booking::class);

        $request->validate([
            'event_id' => 'sometimes|integer|exists:events,id'
        ]);

        $ticketDefinitions = $this->manualBookingService->getTicketDefinitionsForEvent($eventId);

        return response()->json([
            'success' => true,
            'ticket_definitions' => $ticketDefinitions->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'name' => is_array($ticket->name) ? $ticket->name : ['en' => $ticket->name],
                    'description' => is_array($ticket->description) ? $ticket->description : ['en' => $ticket->description ?? ''],
                    'price' => $ticket->price,
                    'currency' => $ticket->currency,
                    'total_quantity' => $ticket->total_quantity,
                    'min_per_order' => $ticket->min_per_order,
                    'max_per_order' => $ticket->max_per_order,
                    'formatted_price' => number_format($ticket->price / 100, 2) . ' ' . strtoupper($ticket->currency),
                ];
            }),
        ]);
    }

    /**
     * Bulk actions on bookings (e.g., bulk status update).
     */
    public function bulkAction(Request $request)
    {
        // TODO: Implement bulk actions
        // Useful for batch processing multiple bookings
    }
}
