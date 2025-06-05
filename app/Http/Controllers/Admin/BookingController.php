<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleNameEnum;
use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Http\Request;
use App\Enums\BookingStatusEnum;

class BookingController extends Controller
{
    protected BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Display a listing of the resource with enhanced filtering.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Build filters array from request
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'event_id' => $request->get('event_id'),
            'user_id' => $request->get('user_id'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
            'per_page' => $request->get('per_page', 15),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        // Get bookings based on user role
        if ($user->hasRole(RoleNameEnum::ADMIN->value)) {
            $bookings = $this->bookingService->getAllBookingsWithFilters($filters);
            $events = $this->bookingService->getEventsForFilter();
            $statistics = $this->bookingService->getBookingStatistics();
        } elseif ($user->hasRole('organizer')) {
            $bookings = $this->bookingService->getBookingsForOrganizerEventsWithFilters($user, $filters);
            $events = $this->bookingService->getOrganizerEventsForFilter($user);
            $statistics = $this->bookingService->getBookingStatistics($user);
        } else {
            // For regular users, redirect to their personal bookings page
            return redirect()->route('my-bookings');
        }

        // Get available booking statuses for filter dropdown
        $statuses = collect(BookingStatusEnum::cases())->map(function ($status) {
            return [
                'value' => $status->value,
                'label' => ucfirst(str_replace('_', ' ', $status->value))
            ];
        });

        return inertia('Admin/Bookings/Index', [
            'bookings' => $bookings,
            'events' => $events,
            'statuses' => $statuses,
            'statistics' => $statistics,
            'filters' => $filters,
            'pageTitle' => 'Manage Bookings',
            'breadcrumbs' => [
                ['title' => 'Admin', 'href' => route('admin.dashboard')],
                ['title' => 'Bookings']
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // TODO: Implement booking creation form if needed
        return inertia('Admin/Bookings/Create', [
            'pageTitle' => 'Create Booking',
            'breadcrumbs' => [
                ['title' => 'Admin', 'href' => route('admin.dashboard')],
                ['title' => 'Bookings', 'href' => route('admin.bookings.index')],
                ['title' => 'Create']
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // TODO: Implement booking creation logic
        // $bookingData = BookingData::from($request->all());
        // $this->bookingService->createBooking($bookingData);
        // return redirect()->route('admin.bookings.index');
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
            if ($user->hasRole('organizer')) {
                // Check if the booking belongs to organizer's event
                if ($booking->event->organizer_id !== $user->id) {
                    return redirect()->route('admin.bookings.index')->with('error', 'Unauthorized to view this booking.');
                }
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
     * Bulk actions on bookings (e.g., bulk status update).
     */
    public function bulkAction(Request $request)
    {
        // TODO: Implement bulk actions
        // Useful for batch processing multiple bookings
    }
}
