/**
 * Composable for ticket hold formatting utilities
 * Provides date formatting functions for the TicketHolds module
 */

import { formatExpiryDate } from '@/Utils/ticket-hold';

/**
 * Composable providing formatting utilities for ticket holds
 */
export function useTicketHoldFormatters() {
    /**
     * Format a date string for display
     * @param dateString - ISO date string or null/undefined
     * @returns Formatted date string or fallback value
     */
    const formatDate = (dateString: string | null | undefined): string => {
        if (!dateString) return '-';
        try {
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return '-';
        }
    };

    return {
        formatDate,
        formatExpiryDate,
    };
}
