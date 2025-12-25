/**
 * TypeScript types for the TicketHold module
 * Provides type definitions for ticket holds, purchase links, allocations, and analytics
 */

import type { User, Translatable, Event } from './index.d';
import type { TicketDefinition } from './ticket';

// ============================================================================
// Enums matching backend PHP enums
// ============================================================================

export type HoldStatus = 'active' | 'expired' | 'released' | 'exhausted';
export type LinkStatus = 'active' | 'expired' | 'revoked' | 'exhausted';
export type PricingMode = 'original' | 'fixed' | 'percentage_discount' | 'free';
export type QuantityMode = 'fixed' | 'maximum' | 'unlimited';

// ============================================================================
// Core Entity Interfaces
// ============================================================================

/**
 * Event occurrence for ticket hold context
 */
export interface TicketHoldEventOccurrence {
    id: number;
    event_id: number;
    name?: Translatable | string;
    start_at: string;
    end_at: string;
    venue?: TicketHoldVenue;
    event?: Event;
}

/**
 * Venue for ticket hold context
 */
export interface TicketHoldVenue {
    id: number;
    name: Translatable | string;
    address_line_1?: Translatable | string;
    city?: Translatable | string;
}

/**
 * Organizer for ticket hold context
 */
export interface TicketHoldOrganizer {
    id: number;
    name: Translatable | string;
}

/**
 * Main TicketHold entity
 */
export interface TicketHold {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    internal_notes: string | null;
    status: HoldStatus;
    expires_at: string | null;
    released_at: string | null;
    created_at: string;
    updated_at: string;

    // Foreign keys
    event_occurrence_id: number;
    organizer_id: number | null;
    created_by: number;

    // Computed properties (from backend accessors)
    total_allocated: number;
    total_purchased: number;
    total_remaining: number;
    is_expired: boolean;
    is_usable: boolean;

    // Loaded relations (optional based on eager loading)
    event_occurrence?: TicketHoldEventOccurrence;
    organizer?: TicketHoldOrganizer;
    creator?: User;
    allocations?: HoldTicketAllocation[];
    purchase_links?: PurchaseLink[];
}

/**
 * Ticket allocation within a hold
 */
export interface HoldTicketAllocation {
    id: number;
    ticket_hold_id: number;
    ticket_definition_id: number;
    allocated_quantity: number;
    purchased_quantity: number;
    pricing_mode: PricingMode;
    custom_price: number | null; // in cents
    discount_percentage: number | null;

    // Computed properties
    remaining_quantity: number;
    is_available: boolean;
    effective_price?: number; // in cents

    // Relations
    ticket_definition?: TicketDefinition;
}

/**
 * Purchase link for a ticket hold
 */
export interface PurchaseLink {
    id: number;
    uuid: string;
    ticket_hold_id: number;
    code: string;
    name: string | null;
    assigned_user_id: number | null;
    quantity_mode: QuantityMode;
    quantity_limit: number | null;
    quantity_purchased: number;
    status: LinkStatus;
    expires_at: string | null;
    revoked_at: string | null;
    notes: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;

    // Computed properties
    full_url: string;
    is_anonymous: boolean;
    is_expired: boolean;
    remaining_quantity: number | null;
    is_usable: boolean;

    // Relations
    ticket_hold?: TicketHold;
    assigned_user?: User;
}

/**
 * Access log entry for a purchase link
 */
export interface PurchaseLinkAccess {
    id: number;
    purchase_link_id: number;
    user_id: number | null;
    ip_address: string | null;
    user_agent: string | null;
    referer: string | null;
    session_id: string | null;
    resulted_in_purchase: boolean;
    accessed_at: string;
}

/**
 * Purchase record through a purchase link
 */
export interface PurchaseLinkPurchase {
    id: number;
    purchase_link_id: number;
    booking_id: number;
    transaction_id: number;
    user_id: number;
    quantity: number;
    unit_price: number; // in cents
    original_price: number; // in cents
    currency: string;
    access_id: number | null;
    created_at: string;
}

// ============================================================================
// Form Data Interfaces
// ============================================================================

/**
 * Form data for creating/editing a ticket hold
 */
export interface TicketHoldFormData {
    event_occurrence_id: number | '';
    organizer_id: number | '' | null;
    name: string;
    description: string;
    internal_notes: string;
    expires_at: string;
    allocations: AllocationFormData[];
}

/**
 * Form data for a single allocation within a hold
 */
export interface AllocationFormData {
    id?: number;
    ticket_definition_id: number | '';
    allocated_quantity: number | '';
    pricing_mode: PricingMode;
    custom_price: number | '' | null;
    discount_percentage: number | '' | null;
}

/**
 * Form data for creating/editing a purchase link
 */
export interface PurchaseLinkFormData {
    name: string;
    assigned_user_id: number | null;
    quantity_mode: QuantityMode;
    quantity_limit: number | '' | null;
    expires_at: string;
    notes: string;
}

/**
 * Form data for making a purchase through a hold link
 */
export interface HoldPurchaseFormData {
    items: Array<{
        ticket_definition_id: number;
        quantity: number;
    }>;
    coupon_code?: string;
}

// ============================================================================
// Analytics Interfaces
// ============================================================================

/**
 * Analytics data for a ticket hold
 */
export interface HoldAnalytics {
    total_allocated: number;
    total_purchased: number;
    total_remaining: number;
    utilization_rate: number; // percentage 0-100
    total_revenue: number; // in cents
    total_savings_given: number; // in cents
    link_count: number;
    active_link_count: number;
    allocation_breakdown: AllocationBreakdown[];
}

/**
 * Breakdown of allocation statistics by ticket type
 */
export interface AllocationBreakdown {
    ticket_definition_id: number;
    ticket_name: string;
    allocated: number;
    purchased: number;
    remaining: number;
    pricing_mode: PricingMode;
    effective_price: number; // in cents
    revenue: number; // in cents
}

/**
 * Analytics data for a purchase link
 */
export interface LinkAnalytics {
    total_accesses: number;
    unique_visitors: number;
    total_purchases: number;
    conversion_rate: number; // percentage 0-100
    total_revenue: number; // in cents
    tickets_purchased: number;
    tickets_remaining: number | null;
}

// ============================================================================
// API Response Interfaces
// ============================================================================

/**
 * Paginated response for ticket holds
 */
export interface TicketHoldsPaginatedResponse {
    data: TicketHold[];
    links: { url: string | null; label: string; active: boolean }[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
}

/**
 * Response for a single ticket hold with full details
 */
export interface TicketHoldDetailResponse {
    data: TicketHold;
    analytics?: HoldAnalytics;
}

/**
 * Response for purchase link details
 */
export interface PurchaseLinkDetailResponse {
    data: PurchaseLink;
    analytics?: LinkAnalytics;
    accesses?: PurchaseLinkAccess[];
    purchases?: PurchaseLinkPurchase[];
}

// ============================================================================
// Dropdown/Select Options
// ============================================================================

/**
 * Option for event occurrence selector
 */
export interface EventOccurrenceOption {
    id: number;
    name: string;
    event_name: string;
    start_at: string;
    end_at?: string;
}

/**
 * Option for organizer selector
 */
export interface OrganizerOption {
    id: number;
    name: string;
}

/**
 * Option for ticket definition selector within hold context
 */
export interface TicketDefinitionOption {
    id: number;
    name: string;
    price: number; // in cents
    currency: string;
    available_quantity?: number;
}

/**
 * Option for user selector (for link assignment)
 */
export interface UserOption {
    id: number;
    name: string;
    email: string;
}

// ============================================================================
// Type Guards
// ============================================================================

export const isTicketHold = (obj: unknown): obj is TicketHold => {
    return (
        typeof obj === 'object' &&
        obj !== null &&
        'id' in obj &&
        'uuid' in obj &&
        'name' in obj &&
        'status' in obj &&
        'event_occurrence_id' in obj
    );
};

export const isPurchaseLink = (obj: unknown): obj is PurchaseLink => {
    return (
        typeof obj === 'object' &&
        obj !== null &&
        'id' in obj &&
        'uuid' in obj &&
        'code' in obj &&
        'ticket_hold_id' in obj &&
        'quantity_mode' in obj
    );
};

export const isHoldTicketAllocation = (obj: unknown): obj is HoldTicketAllocation => {
    return (
        typeof obj === 'object' &&
        obj !== null &&
        'id' in obj &&
        'ticket_hold_id' in obj &&
        'ticket_definition_id' in obj &&
        'pricing_mode' in obj
    );
};

// ============================================================================
// Public Purchase Link Page Types
// ============================================================================

/**
 * Extended allocation with display-specific computed fields
 */
export interface HoldTicketAllocationDisplay extends HoldTicketAllocation {
    ticket_definition: TicketDefinition;
    effective_price: number; // In cents
    original_price: number; // In cents
    savings: number; // Amount saved in cents
    savings_percentage: number; // Percentage saved
}

/**
 * Event info for public purchase link page
 */
export interface PurchaseLinkEvent {
    id: number;
    name: string;
    date: string;
    venue: string;
    image_url: string;
}

/**
 * Props for the public purchase link show page
 */
export interface PurchaseLinkShowProps {
    link: PurchaseLink;
    hold: TicketHold;
    event: PurchaseLinkEvent;
    allocations: HoldTicketAllocationDisplay[];
    isUsable: boolean;
    errorMessage?: string;
    canPurchase: boolean;
    auth: {
        user: User | null;
    };
}

/**
 * Selected item for purchase
 */
export interface PurchaseItem {
    ticket_definition_id: number;
    quantity: number;
}

/**
 * Line item for order summary display
 */
export interface OrderLineItem {
    ticket: TicketDefinition;
    quantity: number;
    unitPrice: number; // In cents
    originalPrice: number; // In cents
}

/**
 * Error reason types for purchase link
 */
export type PurchaseLinkErrorReason =
    | 'expired'
    | 'revoked'
    | 'exhausted'
    | 'hold_inactive'
    | 'user_not_authorized'
    | 'sold_out'
    | 'unknown';

/**
 * Get human-readable error message for a given reason
 */
export function getErrorMessage(reason: PurchaseLinkErrorReason): string {
    switch (reason) {
        case 'expired':
            return 'This purchase link has expired.';
        case 'revoked':
            return 'This purchase link has been revoked.';
        case 'exhausted':
            return 'All tickets from this link have been purchased.';
        case 'hold_inactive':
            return 'These tickets are no longer available.';
        case 'user_not_authorized':
            return 'This link is for another user.';
        case 'sold_out':
            return 'All tickets have been sold out.';
        default:
            return 'This link is not available.';
    }
}

/**
 * Calculate savings display text
 */
export function getSavingsDisplay(savings: number, savingsPercentage: number, pricingMode: PricingMode): string {
    if (pricingMode === 'free') {
        return 'FREE';
    }
    if (savingsPercentage > 0) {
        return `Save ${savingsPercentage}%`;
    }
    return '';
}

/**
 * Check if a pricing mode indicates a discount
 */
export function hasDiscount(pricingMode: PricingMode): boolean {
    return pricingMode === 'fixed' || pricingMode === 'percentage_discount' || pricingMode === 'free';
}
