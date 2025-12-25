<?php

namespace App\Modules\TicketHold\Services;

use App\Modules\TicketHold\Enums\HoldStatusEnum;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkPurchase;
use App\Modules\TicketHold\Models\TicketHold;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HoldAnalyticsService
{
    /**
     * Get analytics for a specific ticket hold.
     */
    public function getHoldAnalytics(TicketHold $hold): array
    {
        $hold->load(['allocations', 'purchaseLinks.accesses', 'purchaseLinks.purchases']);

        $totalAllocated = $hold->allocations->sum('allocated_quantity');
        $totalPurchased = $hold->allocations->sum('purchased_quantity');
        $totalLinks = $hold->purchaseLinks->count();
        $activeLinks = $hold->purchaseLinks->where('status', LinkStatusEnum::ACTIVE)->count();
        $totalAccesses = $hold->purchaseLinks->sum(fn ($link) => $link->accesses->count());
        $totalPurchaseRecords = $hold->purchaseLinks->sum(fn ($link) => $link->purchases->count());

        return [
            'hold' => [
                'id' => $hold->id,
                'uuid' => $hold->uuid,
                'name' => $hold->name,
                'status' => $hold->status->value,
                'created_at' => $hold->created_at->toIso8601String(),
                'expires_at' => $hold->expires_at?->toIso8601String(),
            ],
            'inventory' => [
                'total_allocated' => $totalAllocated,
                'total_purchased' => $totalPurchased,
                'total_remaining' => $totalAllocated - $totalPurchased,
                'utilization_rate' => $totalAllocated > 0
                    ? round(($totalPurchased / $totalAllocated) * 100, 2)
                    : 0,
            ],
            'allocations' => $hold->allocations->map(function ($allocation) {
                return [
                    'ticket_definition_id' => $allocation->ticket_definition_id,
                    'ticket_name' => $allocation->ticketDefinition->getTranslation('name', app()->getLocale()),
                    'allocated' => $allocation->allocated_quantity,
                    'purchased' => $allocation->purchased_quantity,
                    'remaining' => $allocation->remaining_quantity,
                    'pricing_mode' => $allocation->pricing_mode->value,
                    'utilization_rate' => $allocation->allocated_quantity > 0
                        ? round(($allocation->purchased_quantity / $allocation->allocated_quantity) * 100, 2)
                        : 0,
                ];
            })->toArray(),
            'links' => [
                'total' => $totalLinks,
                'active' => $activeLinks,
                'revoked' => $hold->purchaseLinks->where('status', LinkStatusEnum::REVOKED)->count(),
                'exhausted' => $hold->purchaseLinks->where('status', LinkStatusEnum::EXHAUSTED)->count(),
                'expired' => $hold->purchaseLinks->where('status', LinkStatusEnum::EXPIRED)->count(),
            ],
            'engagement' => [
                'total_accesses' => $totalAccesses,
                'total_purchases' => $totalPurchaseRecords,
                'conversion_rate' => $totalAccesses > 0
                    ? round(($totalPurchaseRecords / $totalAccesses) * 100, 2)
                    : 0,
            ],
        ];
    }

    /**
     * Get analytics for a specific purchase link.
     */
    public function getLinkAnalytics(PurchaseLink $link): array
    {
        $link->load(['ticketHold', 'accesses', 'purchases.booking']);

        $totalAccesses = $link->accesses->count();
        $uniqueVisitors = $link->accesses->whereNotNull('user_id')->unique('user_id')->count();
        $purchasedAccesses = $link->accesses->where('resulted_in_purchase', true)->count();

        // Revenue calculations
        $totalRevenue = $link->purchases->sum(fn ($p) => $p->unit_price * $p->quantity);
        $totalOriginalValue = $link->purchases->sum(fn ($p) => $p->original_price * $p->quantity);
        $totalSavings = $totalOriginalValue - $totalRevenue;

        // Access over time (last 30 days)
        $accessesByDay = $link->accesses
            ->where('accessed_at', '>=', now()->subDays(30))
            ->groupBy(fn ($access) => $access->accessed_at->format('Y-m-d'))
            ->map->count()
            ->toArray();

        return [
            'link' => [
                'id' => $link->id,
                'uuid' => $link->uuid,
                'code' => $link->code,
                'name' => $link->name,
                'status' => $link->status->value,
                'quantity_mode' => $link->quantity_mode->value,
                'quantity_limit' => $link->quantity_limit,
                'quantity_purchased' => $link->quantity_purchased,
                'remaining_quantity' => $link->remaining_quantity,
                'is_anonymous' => $link->is_anonymous,
                'created_at' => $link->created_at->toIso8601String(),
                'expires_at' => $link->expires_at?->toIso8601String(),
            ],
            'engagement' => [
                'total_accesses' => $totalAccesses,
                'unique_visitors' => $uniqueVisitors,
                'purchases_from_access' => $purchasedAccesses,
                'conversion_rate' => $totalAccesses > 0
                    ? round(($purchasedAccesses / $totalAccesses) * 100, 2)
                    : 0,
            ],
            'revenue' => [
                'total_revenue' => $totalRevenue,
                'total_original_value' => $totalOriginalValue,
                'total_savings_given' => $totalSavings,
                'average_order_value' => $link->purchases->count() > 0
                    ? (int) round($totalRevenue / $link->purchases->count())
                    : 0,
                'currency' => config('cashier.currency', 'hkd'),
            ],
            'accesses_by_day' => $accessesByDay,
            'recent_accesses' => $link->accesses
                ->sortByDesc('accessed_at')
                ->take(10)
                ->map(function ($access) {
                    return [
                        'accessed_at' => $access->accessed_at->toIso8601String(),
                        'user_id' => $access->user_id,
                        'ip_address' => $access->ip_address,
                        'resulted_in_purchase' => $access->resulted_in_purchase,
                    ];
                })
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Get aggregated analytics for all holds of an organizer.
     */
    public function getOrganizerAnalytics(
        int $organizerId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        // Use database aggregation to avoid N+1 queries
        $holds = TicketHold::query()
            ->where('organizer_id', $organizerId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Get allocation aggregates in a single query
        $allocationAggregates = DB::table('hold_ticket_allocations')
            ->whereIn('ticket_hold_id', $holds->pluck('id'))
            ->selectRaw('SUM(allocated_quantity) as total_allocated, SUM(purchased_quantity) as total_purchased')
            ->first();

        $totalAllocated = (int) ($allocationAggregates->total_allocated ?? 0);
        $totalPurchased = (int) ($allocationAggregates->total_purchased ?? 0);

        // Get link count in a single query
        $totalLinks = DB::table('purchase_links')
            ->whereIn('ticket_hold_id', $holds->pluck('id'))
            ->whereNull('deleted_at')
            ->count();

        // Get access count in a single query via join
        $totalAccesses = DB::table('purchase_link_accesses')
            ->join('purchase_links', 'purchase_link_accesses.purchase_link_id', '=', 'purchase_links.id')
            ->whereIn('purchase_links.ticket_hold_id', $holds->pluck('id'))
            ->whereNull('purchase_links.deleted_at')
            ->count();

        // Get revenue aggregates in a single query via join
        $revenueAggregates = DB::table('purchase_link_purchases')
            ->join('purchase_links', 'purchase_link_purchases.purchase_link_id', '=', 'purchase_links.id')
            ->whereIn('purchase_links.ticket_hold_id', $holds->pluck('id'))
            ->whereNull('purchase_links.deleted_at')
            ->selectRaw('SUM(purchase_link_purchases.unit_price * purchase_link_purchases.quantity) as total_revenue')
            ->selectRaw('SUM((purchase_link_purchases.original_price - purchase_link_purchases.unit_price) * purchase_link_purchases.quantity) as total_savings')
            ->first();

        $totalRevenue = (int) ($revenueAggregates->total_revenue ?? 0);
        $totalSavings = (int) ($revenueAggregates->total_savings ?? 0);

        // Holds by status
        $holdsByStatus = $holds->groupBy(fn ($h) => $h->status->value)->map->count();

        return [
            'period' => [
                'start_date' => $startDate->toIso8601String(),
                'end_date' => $endDate->toIso8601String(),
            ],
            'summary' => [
                'total_holds' => $holds->count(),
                'total_links' => $totalLinks,
                'total_allocated_tickets' => $totalAllocated,
                'total_purchased_tickets' => $totalPurchased,
                'overall_utilization_rate' => $totalAllocated > 0
                    ? round(($totalPurchased / $totalAllocated) * 100, 2)
                    : 0,
            ],
            'holds_by_status' => [
                'active' => $holdsByStatus[HoldStatusEnum::ACTIVE->value] ?? 0,
                'expired' => $holdsByStatus[HoldStatusEnum::EXPIRED->value] ?? 0,
                'released' => $holdsByStatus[HoldStatusEnum::RELEASED->value] ?? 0,
                'exhausted' => $holdsByStatus[HoldStatusEnum::EXHAUSTED->value] ?? 0,
            ],
            'engagement' => [
                'total_link_accesses' => $totalAccesses,
                'conversion_rate' => $totalAccesses > 0
                    ? round(($totalPurchased / $totalAccesses) * 100, 2)
                    : 0,
            ],
            'revenue' => [
                'total_revenue' => $totalRevenue,
                'total_savings_given' => $totalSavings,
                'currency' => config('cashier.currency', 'hkd'),
            ],
        ];
    }

    /**
     * Get top performing links by conversion rate.
     *
     * Optimized to:
     * 1. Only fetch links that have at least 1 access (using whereHas)
     * 2. Use database aggregation via withCount to avoid N+1 queries
     * 3. Sort at database level, apply limit in PHP after filtering zero-access links
     *
     * Memory optimization: Uses whereHas('accesses') to filter out links with no accesses
     * before loading them into memory, significantly reducing data transfer for organizers
     * with many unused links.
     */
    public function getTopPerformingLinks(int $organizerId, int $limit = 10): Collection
    {
        // Pre-filter: Only fetch links that have at least one access
        // This is the key optimization - we don't load links with 0 accesses
        $links = PurchaseLink::with(['ticketHold'])
            ->whereHas('ticketHold', fn ($query) => $query->where('organizer_id', $organizerId))
            ->whereHas('accesses') // Only links with at least 1 access
            ->withCount([
                'accesses as access_count',
                'accesses as purchase_count' => fn ($query) => $query->where('resulted_in_purchase', true),
            ])
            ->get();

        // Calculate conversion rate, sort, and apply limit
        // Note: Sorting by conversion rate requires PHP since it's a calculated value
        // from two separate subquery counts. This is now efficient because we only
        // loaded links that have accesses.
        return $links->map(function ($link) {
            return [
                'link' => $link,
                'access_count' => $link->access_count,
                'purchase_count' => $link->purchase_count,
                'conversion_rate' => $link->access_count > 0
                    ? round(($link->purchase_count / $link->access_count) * 100, 2)
                    : 0,
            ];
        })
            ->sortByDesc('conversion_rate')
            ->take($limit)
            ->values();
    }

    /**
     * Get revenue breakdown by ticket type for a hold.
     */
    public function getRevenueByTicketType(TicketHold $hold): Collection
    {
        return PurchaseLinkPurchase::query()
            ->whereHas('purchaseLink', function ($query) use ($hold) {
                $query->where('ticket_hold_id', $hold->id);
            })
            ->join('bookings', 'purchase_link_purchases.booking_id', '=', 'bookings.id')
            ->join('ticket_definitions', 'bookings.ticket_definition_id', '=', 'ticket_definitions.id')
            ->select(
                'ticket_definitions.id as ticket_definition_id',
                'ticket_definitions.name as ticket_name',
                DB::raw('SUM(purchase_link_purchases.quantity) as total_quantity'),
                DB::raw('SUM(purchase_link_purchases.unit_price * purchase_link_purchases.quantity) as total_revenue'),
                DB::raw('SUM(purchase_link_purchases.original_price * purchase_link_purchases.quantity) as original_value'),
                DB::raw('SUM((purchase_link_purchases.original_price - purchase_link_purchases.unit_price) * purchase_link_purchases.quantity) as total_savings')
            )
            ->groupBy('ticket_definitions.id', 'ticket_definitions.name')
            ->get();
    }

    /**
     * Get access patterns (hourly distribution) for a link.
     */
    public function getAccessPatterns(PurchaseLink $link): array
    {
        $hourlyDistribution = $link->accesses
            ->groupBy(fn ($access) => $access->accessed_at->format('H'))
            ->map->count()
            ->toArray();

        // Fill in missing hours with 0
        $fullDistribution = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $key = str_pad($hour, 2, '0', STR_PAD_LEFT);
            $fullDistribution[$key] = $hourlyDistribution[$key] ?? 0;
        }

        $dayOfWeekDistribution = $link->accesses
            ->groupBy(fn ($access) => $access->accessed_at->format('l'))
            ->map->count()
            ->toArray();

        return [
            'hourly_distribution' => $fullDistribution,
            'day_of_week_distribution' => $dayOfWeekDistribution,
            'peak_hour' => array_search(max($fullDistribution), $fullDistribution),
            'peak_day' => ! empty($dayOfWeekDistribution)
                ? array_search(max($dayOfWeekDistribution), $dayOfWeekDistribution)
                : null,
        ];
    }

    /**
     * Get referrer analysis for a link.
     */
    public function getReferrerAnalysis(PurchaseLink $link): Collection
    {
        return $link->accesses
            ->whereNotNull('referer')
            ->groupBy(function ($access) {
                $url = parse_url($access->referer);

                return $url['host'] ?? 'direct';
            })
            ->map(function ($accesses, $domain) {
                return [
                    'domain' => $domain,
                    'access_count' => $accesses->count(),
                    'purchases' => $accesses->where('resulted_in_purchase', true)->count(),
                    'conversion_rate' => $accesses->count() > 0
                        ? round(($accesses->where('resulted_in_purchase', true)->count() / $accesses->count()) * 100, 2)
                        : 0,
                ];
            })
            ->sortByDesc('access_count')
            ->values();
    }
}
