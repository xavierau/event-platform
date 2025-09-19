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
    timezone?: string | null;
    event_occurrence_ids?: number[] | null; // Associated event occurrence IDs
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
    // Membership pricing fields (available when user is authenticated with membership)
    membership_price?: number; // Discounted price for user's membership level
    has_membership_discount?: boolean; // Whether user gets a discount
    savings_amount?: number; // Amount saved with membership discount
    savings_percentage?: number; // Percentage saved with membership discount
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
 * Event occurrence option for selectors
 */
export interface EventOccurrenceOption {
    id: number;
    name: string; // Name (translated for current UI locale)
    event_name: string; // Parent event name
    start_at?: string; // Formatted datetime string
    end_at?: string; // Formatted datetime string
}

/**
 * Form data for creating ticket definitions
 */
export interface TicketDefinitionCreateFormData {
    name: Record<string, string>; // Translatable name
    description: Record<string, string>; // Translatable description
    price: number | null | undefined;
    currency: string;
    total_quantity: number | null | undefined;
    availability_window_start: string | null | undefined;
    availability_window_end: string | null | undefined;
    min_per_order: number | null | undefined;
    max_per_order: number | null | undefined;
    status: string;
    membership_discounts?: MembershipDiscount[];
    [key: string]: any; // Index signature for form compatibility
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
 * Membership level for discount configuration
 */
export interface MembershipLevel {
    id: number;
    name: Record<string, string> | string;
    slug: string;
    is_active: boolean;
    price?: number;
    description?: Record<string, string> | string;
}

/**
 * Membership discount configuration
 */
export interface MembershipDiscount {
    membership_level_id: number;
    discount_type: 'percentage' | 'fixed';
    discount_value: number;
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
