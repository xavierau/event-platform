<?php

namespace App\Modules\TicketHold\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\TicketHold\Models\TicketHold;
use App\Modules\TicketHold\Services\HoldAnalyticsService;
use Illuminate\Http\JsonResponse;

class HoldAnalyticsController extends Controller
{
    public function __construct(
        protected HoldAnalyticsService $analyticsService
    ) {}

    /**
     * Display detailed analytics for a ticket hold.
     */
    public function show(TicketHold $ticketHold): JsonResponse
    {
        $this->authorize('viewAnalytics', $ticketHold);

        $ticketHold->load([
            'allocations.ticketDefinition',
            'purchaseLinks.accesses',
            'purchaseLinks.purchases.booking',
        ]);

        $analytics = $this->analyticsService->getHoldAnalytics($ticketHold);

        // Get revenue breakdown by ticket type
        $revenueByTicketType = $this->analyticsService->getRevenueByTicketType($ticketHold);

        // Get top performing links if there are multiple links
        $topPerformingLinks = [];
        if ($ticketHold->organizer_id) {
            $topPerformingLinks = $this->analyticsService->getTopPerformingLinks(
                $ticketHold->organizer_id,
                5
            )->map(fn ($item) => [
                'link_id' => $item['link']->id,
                'link_code' => $item['link']->code,
                'link_name' => $item['link']->name,
                'access_count' => $item['access_count'],
                'purchase_count' => $item['purchase_count'],
                'conversion_rate' => $item['conversion_rate'],
            ])->toArray();
        }

        return response()->json([
            'analytics' => $analytics,
            'revenue_by_ticket_type' => $revenueByTicketType,
            'top_performing_links' => $topPerformingLinks,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
