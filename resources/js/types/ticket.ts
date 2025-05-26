// Centralized Ticket Types for the Event Platform

/**
 * Base ticket definition interface - represents the core ticket definition entity
 */
export interface TicketDefinition {
    id: number;
    name: Record<string, string> | string; // Translatable name or simple string
    description?: Record<string, string> | string; // Translatable description or simple string
    price: number; // Price in smallest currency unit (e.g., cents)
    currency: string; // e.g., 'USD', 'EUR', etc.
    total_quantity?: number | null; // null means unlimited
    availability_window_start?: string | null;
    availability_window_end?: string | null;
    availability_window_start_utc?: string | null;
    availability_window_end_utc?: string | null;
    min_per_order?: number | null;
    max_per_order?: number | null;
    status: string; // e.g., 'active', 'draft', 'inactive', 'archived'
    metadata?: Record<string, any> | null;
    created_at?: string;
    updated_at?: string;
}

/**
 * Simplified ticket definition for public-facing components (e.g., event detail page, purchase modal)
 */
export interface PublicTicketType {
    id: string | number;
    name: string; // Already translated for current locale
    description?: string; // Already translated for current locale
    price: number; // Price in smallest currency unit (e.g., cents)
    currency: string;
    quantity_available?: number; // Available quantity for purchase
    max_per_order?: number;
    min_per_order?: number;
}

/**
 * Ticket definition option for selectors and dropdowns
 */
export interface TicketDefinitionOption {
    id: number;
    name: string; // Name (preferably translated for current UI locale)
    price: number; // Price in smallest currency unit (e.g., cents)
    currency_code: string; // e.g., 'USD', 'EUR', etc.
    status?: string;
}

/**
 * Ticket assignment for event occurrences
 */
export interface OccurrenceTicketAssignment {
    ticket_definition_id: number;
    name?: string; // For display convenience, fetched from main TicketDefinition
    original_price?: number; // For display convenience
    original_currency_code?: string; // For display convenience
    quantity_for_occurrence: number | undefined; // null/undefined means unlimited
    price_override: number | undefined; // In cents, null/undefined means use original price
    availability_status?: string; // Optional status for this specific occurrence
}

/**
 * Form data for creating ticket definitions
 */
export interface TicketDefinitionCreateFormData {
    name: Record<string, string>; // Translatable name
    description: Record<string, string>; // Translatable description
    price: number | null;
    currency: string;
    total_quantity: number | null;
    availability_window_start: string | null;
    availability_window_end: string | null;
    min_per_order: number | null;
    max_per_order: number | null;
    status: string;
    metadata: Record<string, any> | null;
}

/**
 * Form data for editing ticket definitions
 */
export interface TicketDefinitionEditFormData extends TicketDefinitionCreateFormData {
    _method: 'PUT';
}

/**
 * Paginated ticket definitions response
 */
export interface TicketDefinitionsPaginated {
    data: TicketDefinition[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number;
    to: number;
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
}

/**
 * Ticket definition with additional props for specific components
 * Currently identical to TicketDefinition, but can be extended as needed
 */
export type TicketDefinitionProp = TicketDefinition;

/**
 * Booking-related ticket information
 */
export interface BookingTicketInfo {
    name: string;
    price: number;
    currency: string;
    quantity: number;
    total_price: number;
}

/**
 * Event information for ticket context
 */
export interface TicketEventInfo {
    name: string;
}

/**
 * Event occurrence information for ticket context
 */
export interface TicketEventOccurrenceInfo {
    name?: string;
    event?: TicketEventInfo;
}

/**
 * Booking information with ticket details
 */
export interface BookingWithTicketInfo {
    id: number;
    quantity: number;
    total_price: number;
    currency: string;
    ticket_definition?: BookingTicketInfo;
    event_occurrence?: TicketEventOccurrenceInfo;
}

// Type guards for runtime type checking
export const isTicketDefinition = (obj: any): obj is TicketDefinition => {
    return obj && typeof obj.id === 'number' && (typeof obj.name === 'string' || typeof obj.name === 'object');
};

export const isPublicTicketType = (obj: any): obj is PublicTicketType => {
    return obj && (typeof obj.id === 'string' || typeof obj.id === 'number') && typeof obj.name === 'string';
};

// Utility functions for working with ticket types
export const getTicketName = (ticket: TicketDefinition | PublicTicketType, locale?: string): string => {
    if (typeof ticket.name === 'string') {
        return ticket.name;
    }
    if (typeof ticket.name === 'object' && locale) {
        return ticket.name[locale] || ticket.name['en'] || Object.values(ticket.name)[0] || '';
    }
    return '';
};

export const formatTicketPrice = (price: number, currency: string): string => {
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currency.toUpperCase(),
        }).format(price / 100);
    } catch {
        return `${(price / 100).toFixed(2)} ${currency.toUpperCase()}`;
    }
};
