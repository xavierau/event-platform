<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleNameEnum;
use App\Http\Controllers\Controller;
use App\Services\BookingService; // Assuming BookingService will be created
use Illuminate\Http\Request;

class BookingController extends Controller
{
    protected BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $bookingsCollection = collect(); // Default to an empty collection

        // Assuming role names 'user' and 'organizer'
        if ($user->hasRole('user')) {
            $bookingsCollection = $this->bookingService->getBookingsForUser($user);
        } elseif ($user->hasRole('organizer')) {
            $bookingsCollection = $this->bookingService->getBookingsForOrganizerEvents($user);
        } elseif ($user->hasRole(RoleNameEnum::ADMIN->value)) { // Example: Allow super-admin to see all bookings
            $bookingsCollection = $this->bookingService->getAllBookings();
        }
        // If you were using pagination, it would typically already be in the correct structure:
        // $bookings = $this->bookingService->getAllBookingsPaginated();
        // For collections, we wrap it to match the { data: [...] } structure:
        $bookings = ['data' => $bookingsCollection];

        return inertia('Admin/Bookings/Index', [
            'bookings' => $bookings,
            'pageTitle' => 'Manage Bookings',
            'breadcrumbs' => [
                ['title' => 'Admin', 'href' => route('admin.dashboard')], // Assuming you have an admin.dashboard route
                ['title' => 'Bookings']
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // return inertia('Admin/Bookings/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Logic to store a new booking
        // $bookingData = BookingData::from($request->all());
        // $this->bookingService->createBooking($bookingData);
        // return redirect()->route('admin.bookings.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // $booking = $this->bookingService->findBooking((int)$id);
        // return inertia('Admin/Bookings/Show', ['booking' => $booking]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // $booking = $this->bookingService->findBooking((int)$id);
        // return inertia('Admin/Bookings/Edit', ['booking' => $booking]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // $bookingData = BookingData::from($request->all());
        // $this->bookingService->updateBooking((int)$id, $bookingData);
        // return redirect()->route('admin.bookings.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // $this->bookingService->deleteBooking((int)$id);
        // return redirect()->route('admin.bookings.index');
    }
}
