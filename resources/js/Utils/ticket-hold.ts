/**
 * Utility functions for the TicketHold module
 * Provides formatting and calculation helpers for ticket holds, purchase links, and pricing
 */

import type { HoldStatus, LinkStatus, PricingMode, QuantityMode } from '@/types/ticket-hold';

// ============================================================================
// Status Formatting Functions
// ============================================================================

/**
 * Get human-readable label for hold status
 */
export function formatHoldStatus(status: HoldStatus): string {
    const labels: Record<HoldStatus, string> = {
        active: 'Active',
        expired: 'Expired',
        released: 'Released',
        exhausted: 'Exhausted',
    };
    return labels[status] ?? status;
}

/**
 * Get human-readable label for link status
 */
export function formatLinkStatus(status: LinkStatus): string {
    const labels: Record<LinkStatus, string> = {
        active: 'Active',
        expired: 'Expired',
        revoked: 'Revoked',
        exhausted: 'Fully Used',
    };
    return labels[status] ?? status;
}

/**
 * Get human-readable label for pricing mode
 */
export function formatPricingMode(mode: PricingMode): string {
    const labels: Record<PricingMode, string> = {
        original: 'Original Price',
        fixed: 'Custom Fixed Price',
        percentage_discount: 'Percentage Discount',
        free: 'Free (Complimentary)',
    };
    return labels[mode] ?? mode;
}

/**
 * Get human-readable label for quantity mode
 */
export function formatQuantityMode(mode: QuantityMode): string {
    const labels: Record<QuantityMode, string> = {
        fixed: 'Exact Quantity',
        maximum: 'Up to Maximum',
        unlimited: 'Unlimited (from pool)',
    };
    return labels[mode] ?? mode;
}

// ============================================================================
// Status Color Functions (Tailwind CSS classes)
// ============================================================================

/**
 * Get Tailwind CSS color classes for hold status badge
 */
export function getHoldStatusColor(status: HoldStatus): string {
    const colors: Record<HoldStatus, string> = {
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        expired: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        released: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        exhausted: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    };
    return colors[status] ?? 'bg-gray-100 text-gray-800';
}

/**
 * Get Tailwind CSS color classes for link status badge
 */
export function getLinkStatusColor(status: LinkStatus): string {
    const colors: Record<LinkStatus, string> = {
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        expired: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        revoked: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        exhausted: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    };
    return colors[status] ?? 'bg-gray-100 text-gray-800';
}

/**
 * Get Tailwind CSS color classes for pricing mode badge
 */
export function getPricingModeColor(mode: PricingMode): string {
    const colors: Record<PricingMode, string> = {
        original: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        fixed: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        percentage_discount: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        free: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    };
    return colors[mode] ?? 'bg-gray-100 text-gray-800';
}

// ============================================================================
// Pricing Calculation Functions
// ============================================================================

/**
 * Calculate the effective price based on pricing mode
 * @param originalPrice - Original price in cents
 * @param pricingMode - The pricing mode to apply
 * @param customPrice - Custom fixed price in cents (for 'fixed' mode)
 * @param discountPercentage - Discount percentage 0-100 (for 'percentage_discount' mode)
 * @returns Effective price in cents
 */
export function calculateEffectivePrice(
    originalPrice: number,
    pricingMode: PricingMode,
    customPrice: number | null,
    discountPercentage: number | null
): number {
    switch (pricingMode) {
        case 'original':
            return originalPrice;
        case 'fixed':
            return customPrice ?? originalPrice;
        case 'percentage_discount':
            return Math.round(originalPrice * (1 - (discountPercentage ?? 0) / 100));
        case 'free':
            return 0;
        default:
            return originalPrice;
    }
}

/**
 * Calculate savings amount
 * @param originalPrice - Original price in cents
 * @param effectivePrice - Effective/discounted price in cents
 * @returns Savings amount in cents (always >= 0)
 */
export function calculateSavings(originalPrice: number, effectivePrice: number): number {
    return Math.max(0, originalPrice - effectivePrice);
}

/**
 * Calculate savings percentage
 * @param originalPrice - Original price in cents
 * @param effectivePrice - Effective/discounted price in cents
 * @returns Savings percentage (0-100)
 */
export function calculateSavingsPercentage(originalPrice: number, effectivePrice: number): number {
    if (originalPrice <= 0) return 0;
    return Math.round(((originalPrice - effectivePrice) / originalPrice) * 100);
}

/**
 * Check if a pricing mode provides any discount
 */
export function hasDiscount(pricingMode: PricingMode): boolean {
    return pricingMode === 'fixed' || pricingMode === 'percentage_discount' || pricingMode === 'free';
}

/**
 * Check if a pricing mode requires an additional value input
 */
export function pricingModeRequiresValue(pricingMode: PricingMode): boolean {
    return pricingMode === 'fixed' || pricingMode === 'percentage_discount';
}

// ============================================================================
// Currency Formatting Functions
// ============================================================================

/**
 * Format cents to a currency string
 * @param cents - Amount in cents
 * @param currency - Currency code (default: 'HKD')
 * @param locale - Locale for formatting (default: 'en-HK')
 * @returns Formatted currency string
 */
export function formatCurrency(cents: number, currency: string = 'HKD', locale: string = 'en-HK'): string {
    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: currency.toUpperCase(),
        }).format(cents / 100);
    } catch {
        // Fallback for unsupported currencies
        return `${(cents / 100).toFixed(2)} ${currency.toUpperCase()}`;
    }
}

/**
 * Format a price with strike-through for original price when discounted
 * Returns an object with both prices for flexible rendering
 */
export function formatPriceWithDiscount(
    originalPriceCents: number,
    effectivePriceCents: number,
    currency: string = 'HKD'
): { original: string; effective: string; hasDiscount: boolean } {
    const original = formatCurrency(originalPriceCents, currency);
    const effective = formatCurrency(effectivePriceCents, currency);
    return {
        original,
        effective,
        hasDiscount: effectivePriceCents < originalPriceCents,
    };
}

// ============================================================================
// Quantity Functions
// ============================================================================

/**
 * Check if quantity mode requires a limit value
 */
export function quantityModeRequiresLimit(mode: QuantityMode): boolean {
    return mode !== 'unlimited';
}

/**
 * Format remaining quantity for display
 * @param remaining - Remaining quantity, null means unlimited
 * @returns Formatted string
 */
export function formatRemainingQuantity(remaining: number | null): string {
    if (remaining === null) {
        return 'Unlimited';
    }
    if (remaining === 0) {
        return 'None left';
    }
    return `${remaining} remaining`;
}

/**
 * Calculate utilization percentage
 * @param allocated - Total allocated quantity
 * @param purchased - Total purchased quantity
 * @returns Percentage 0-100
 */
export function calculateUtilizationRate(allocated: number, purchased: number): number {
    if (allocated <= 0) return 0;
    return Math.round((purchased / allocated) * 100);
}

// ============================================================================
// URL Functions
// ============================================================================

/**
 * Generate a purchase link URL
 * @param code - The unique link code
 * @param baseUrl - Base URL of the application (optional, uses current origin)
 * @returns Full purchase link URL
 */
export function generatePurchaseLinkUrl(code: string, baseUrl?: string): string {
    const base = baseUrl || (typeof window !== 'undefined' ? window.location.origin : '');
    return `${base}/purchase/${code}`;
}

/**
 * Copy text to clipboard
 * @param text - Text to copy
 * @returns Promise that resolves when copied successfully
 */
export async function copyToClipboard(text: string): Promise<boolean> {
    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(text);
            return true;
        }
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        return true;
    } catch {
        return false;
    }
}

// ============================================================================
// Date Functions
// ============================================================================

/**
 * Check if a date string represents an expired date
 * @param dateString - ISO date string or null
 * @returns true if expired, false if not expired or no expiry
 */
export function isExpired(dateString: string | null): boolean {
    if (!dateString) return false;
    return new Date(dateString) < new Date();
}

/**
 * Format expiry date for display
 * @param dateString - ISO date string or null
 * @param locale - Locale for formatting
 * @returns Formatted date string or 'No expiry'
 */
export function formatExpiryDate(dateString: string | null, locale: string = 'en-HK'): string {
    if (!dateString) return 'No expiry';

    const date = new Date(dateString);
    const now = new Date();

    if (date < now) {
        return `Expired on ${date.toLocaleDateString(locale, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        })}`;
    }

    return `Expires ${date.toLocaleDateString(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })}`;
}

/**
 * Get time remaining until expiry
 * @param dateString - ISO date string
 * @returns Human-readable time remaining string
 */
export function getTimeRemaining(dateString: string | null): string {
    if (!dateString) return 'No expiry';

    const date = new Date(dateString);
    const now = new Date();
    const diff = date.getTime() - now.getTime();

    if (diff <= 0) return 'Expired';

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

    if (days > 0) {
        return `${days} day${days !== 1 ? 's' : ''} ${hours} hr${hours !== 1 ? 's' : ''}`;
    }
    if (hours > 0) {
        return `${hours} hr${hours !== 1 ? 's' : ''} ${minutes} min${minutes !== 1 ? 's' : ''}`;
    }
    return `${minutes} min${minutes !== 1 ? 's' : ''}`;
}

// ============================================================================
// Validation Functions
// ============================================================================

/**
 * Validate allocation form data
 */
export function validateAllocation(allocation: {
    ticket_definition_id: number | '';
    allocated_quantity: number | '';
    pricing_mode: PricingMode;
    custom_price: number | '' | null;
    discount_percentage: number | '' | null;
}): { valid: boolean; errors: string[] } {
    const errors: string[] = [];

    if (!allocation.ticket_definition_id) {
        errors.push('Please select a ticket type');
    }

    if (!allocation.allocated_quantity || allocation.allocated_quantity <= 0) {
        errors.push('Allocated quantity must be greater than 0');
    }

    if (allocation.pricing_mode === 'fixed' && (!allocation.custom_price || allocation.custom_price < 0)) {
        errors.push('Custom price is required for fixed pricing mode');
    }

    if (allocation.pricing_mode === 'percentage_discount') {
        const discount = allocation.discount_percentage;
        if (!discount || discount < 0 || discount > 100) {
            errors.push('Discount percentage must be between 0 and 100');
        }
    }

    return { valid: errors.length === 0, errors };
}

/**
 * Validate purchase link form data
 */
export function validatePurchaseLinkForm(form: {
    quantity_mode: QuantityMode;
    quantity_limit: number | '' | null;
}): { valid: boolean; errors: string[] } {
    const errors: string[] = [];

    if (form.quantity_mode !== 'unlimited' && (!form.quantity_limit || form.quantity_limit <= 0)) {
        errors.push('Quantity limit is required for fixed and maximum quantity modes');
    }

    return { valid: errors.length === 0, errors };
}
