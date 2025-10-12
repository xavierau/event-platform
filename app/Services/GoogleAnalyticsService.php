<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GoogleAnalyticsService
{
    /**
     * Format transaction data for GA4 e-commerce tracking.
     */
    public function formatTransactionForGA4(Transaction $transaction, Collection $bookings, ?User $user = null): array
    {
        $data = [
            'transaction_id' => (string) $transaction->id,
            'value' => (float) ($transaction->total_amount / 100), // Convert cents to dollars
            'currency' => strtoupper($transaction->currency),
            'affiliation' => config('app.name', 'Event Platform'),
            'tax' => 0,
            'shipping' => 0,
            'items' => $this->formatBookingsAsGA4Items($bookings),
        ];

        if ($user) {
            $data['user_id'] = (string) $user->id;
        }

        Log::info('[GoogleAnalyticsService] Formatted transaction for GA4', [
            'transaction_id' => $transaction->id,
            'value' => $data['value'],
            'currency' => $data['currency'],
            'items_count' => count($data['items']),
            'user_id' => $user?->id
        ]);

        return $data;
    }

    /**
     * Format bookings as GA4 e-commerce items.
     */
    public function formatBookingsAsGA4Items(Collection $bookings): array
    {
        return $bookings->map(function (Booking $booking) {
            $event = $booking->event;
            $ticketDefinition = $booking->ticketDefinition;

            return [
                'item_id' => "ticket_{$ticketDefinition?->id}",
                'item_name' => $ticketDefinition?->name ?: 'General Admission',
                'item_category' => 'Event Ticket',
                'item_category2' => $event?->category?->name ?: 'General',
                'item_brand' => $event?->organizer?->name ?: 'Event Platform',
                'price' => (float) ($booking->price_at_booking / 100), // Convert cents to dollars
                'quantity' => $booking->quantity,
                'item_variant' => $ticketDefinition?->type ?: 'standard',
                'custom_parameters' => [
                    'event_id' => $event?->id,
                    'event_name' => $event?->name,
                    'booking_id' => $booking->id,
                ],
            ];
        })->toArray();
    }

    /**
     * Format user properties for GA4 tracking.
     */
    public function formatUserPropertiesForGA4(User $user): array
    {
        $membershipTier = 'none';
        $membershipStatus = 'none';

        // Check for membership using the Membership module
        if ($user->relationLoaded('memberships') || method_exists($user, 'memberships')) {
            $activeMembership = $user->memberships()?->where('status', 'active')->first();
            if ($activeMembership) {
                $membershipTier = $activeMembership->membership_plan?->name ?? 'unknown';
                $membershipStatus = 'active';
            }
        }

        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;

        $properties = [
            'user_type' => $isAdmin ? 'admin' : 'customer',
            'membership_tier' => $membershipTier,
            'membership_status' => $membershipStatus,
            'customer_since' => $user->created_at->format('Y-m-d'),
        ];

        // Add booking-related properties if available
        if ($user->relationLoaded('bookings') || method_exists($user, 'bookings')) {
            $bookingsCount = $user->bookings()->count();
            $properties['total_bookings'] = $bookingsCount;
            $properties['customer_segment'] = $this->getCustomerSegment($bookingsCount);
        }

        Log::debug('[GoogleAnalyticsService] Formatted user properties for GA4', [
            'user_id' => $user->id,
            'properties' => $properties
        ]);

        return $properties;
    }

    /**
     * Generate JavaScript code for GA4 purchase tracking.
     */
    public function generatePurchaseTrackingScript(array $transactionData): string
    {
        $jsonData = json_encode($transactionData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return "
        if (typeof gtag !== 'undefined') {
            gtag('event', 'purchase', {$jsonData});
            console.log('[GA4] Purchase event sent:', {$jsonData});
        } else {
            console.warn('[GA4] gtag not available, purchase event not sent');
        }";
    }

    /**
     * Generate JavaScript code for setting user properties.
     */
    public function generateUserPropertiesScript(array $userProperties, string $userId): string
    {
        $propertiesJson = json_encode($userProperties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return "
        if (typeof gtag !== 'undefined') {
            gtag('set', { 'user_id': '{$userId}' });
            gtag('set', 'user_properties', {$propertiesJson});
            console.log('[GA4] User properties set for user {$userId}:', {$propertiesJson});
        } else {
            console.warn('[GA4] gtag not available, user properties not set');
        }";
    }

    /**
     * Get customer segment based on booking count.
     */
    private function getCustomerSegment(int $bookingsCount): string
    {
        return match (true) {
            $bookingsCount === 0 => 'new',
            $bookingsCount === 1 => 'first_time',
            $bookingsCount <= 5 => 'occasional',
            $bookingsCount <= 15 => 'regular',
            default => 'vip'
        };
    }

    /**
     * Check if GA4 tracking is enabled.
     */
    public function isTrackingEnabled(): bool
    {
        return !empty(config('services.google.analytics_id'));
    }

    /**
     * Validate GA4 transaction data structure.
     */
    public function validateTransactionData(array $data): array
    {
        $errors = [];

        if (empty($data['transaction_id'])) {
            $errors[] = 'transaction_id is required';
        }

        if (!isset($data['value']) || !is_numeric($data['value']) || $data['value'] < 0) {
            $errors[] = 'value must be a non-negative number';
        }

        if (empty($data['currency']) || !is_string($data['currency'])) {
            $errors[] = 'currency is required and must be a string';
        }

        if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
            $errors[] = 'items array is required and cannot be empty';
        }

        if (!empty($errors)) {
            Log::warning('[GoogleAnalyticsService] GA4 transaction data validation failed', [
                'errors' => $errors,
                'data' => $data
            ]);
        }

        return $errors;
    }
}