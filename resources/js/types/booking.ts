export interface BookingTicketInfo {
    name: string;
    price: number;
    currency: string;
    quantity: number;
    total_price: number;
}

export interface TicketEventInfo {
    name: string;
}

export interface TicketEventOccurrenceInfo {
    id: number;
    name?: string;
    start_at?: string;
    end_at?: string;
    venue_name?: string;
    venue_address?: string;
}

export interface BookingItem {
    id: number;
    booking_number: string;
    quantity: number;
    total_price: number;
    currency: string;
    status: string;
    created_at: string;
    ticket_definition?: BookingTicketInfo;
    event_occurrences?: TicketEventOccurrenceInfo[];
    event?: TicketEventInfo;
    qr_code_identifier?: string;
}
