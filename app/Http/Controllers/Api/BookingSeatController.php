<?php

namespace App\Http\Controllers\Api;

use App\Actions\Booking\AssignSeatAction;
use App\Data\AssignSeatData;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingSeatController extends Controller
{
    public function __construct(private AssignSeatAction $assignSeatAction)
    {
        $this->middleware('auth:sanctum');
    }

    public function assign(Request $request, Booking $booking): JsonResponse
    {
        // Check if user can manage bookings for this event
        $this->authorize('update', $booking);
        
        $data = AssignSeatData::from([
            'seat_number' => $request->seat_number,
            'booking_id' => $booking->id,
        ]);
        
        $updatedBooking = $this->assignSeatAction->execute($data);
        
        return response()->json([
            'message' => 'Seat assigned successfully',
            'booking' => $updatedBooking,
            'seat_number' => $updatedBooking->metadata['seat_number'] ?? null,
        ]);
    }
    
    public function remove(Booking $booking): JsonResponse
    {
        // Check if user can manage bookings for this event
        $this->authorize('update', $booking);
        
        $updatedBooking = $this->assignSeatAction->removeSeat($booking->id);
        
        return response()->json([
            'message' => 'Seat assignment removed successfully',
            'booking' => $updatedBooking,
        ]);
    }
}