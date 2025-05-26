export function getBookingStatusColor(status: string): string {
    switch (status.toLowerCase()) {
        case 'confirmed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        case 'used':
            return 'bg-gray-100 text-gray-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

export function getBookingStatusText(status: string): string {
    switch (status.toLowerCase()) {
        case 'confirmed':
            return 'Confirmed';
        case 'cancelled':
            return 'Cancelled';
        case 'used':
            return 'Used';
        case 'pending':
            return 'Pending';
        default:
            return status;
    }
}
