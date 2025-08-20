<?php

namespace App\Actions\Booking;

use App\Data\AssignSeatData;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;

class AssignSeatAction
{
    public function execute(AssignSeatData $data): Booking
    {
        $booking = Booking::findOrFail($data->booking_id);
        
        // Get existing metadata or initialize empty array
        $metadata = $booking->metadata ?? [];
        
        // Add seat assignment info
        $metadata['seat_number'] = $data->seat_number;
        $metadata['seat_assigned_by'] = Auth::id();
        $metadata['seat_assigned_at'] = now()->toISOString();
        
        // Update booking metadata
        $booking->update(['metadata' => $metadata]);
        
        return $booking->fresh();
    }
    
    public function removeSeat(int $bookingId): Booking
    {
        $booking = Booking::findOrFail($bookingId);
        
        // Get existing metadata
        $metadata = $booking->metadata ?? [];
        
        // Remove seat assignment info
        unset($metadata['seat_number'], $metadata['seat_assigned_by'], $metadata['seat_assigned_at']);
        
        // Update booking metadata
        $booking->update(['metadata' => $metadata]);
        
        return $booking->fresh();
    }
}