<?php

namespace App\Modules\PromotionalModal\Services;

use App\Models\User;
use App\Modules\PromotionalModal\Actions\RecordImpressionAction;
use App\Modules\PromotionalModal\Actions\UpsertPromotionalModalAction;
use App\Modules\PromotionalModal\DataTransferObjects\PromotionalModalData;
use App\Modules\PromotionalModal\Models\PromotionalModal;
use App\Modules\PromotionalModal\Models\PromotionalModalImpression;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class PromotionalModalService
{
    public function __construct(
        private UpsertPromotionalModalAction $upsertPromotionalModalAction,
        private RecordImpressionAction $recordImpressionAction
    ) {}

    public function createModal(PromotionalModalData $data): PromotionalModal
    {
        return $this->upsertPromotionalModalAction->execute($data);
    }

    public function updateModal(PromotionalModal $modal, PromotionalModalData $data): PromotionalModal
    {
        return $this->upsertPromotionalModalAction->execute($data, $modal);
    }

    public function deleteModal(PromotionalModal $modal): void
    {
        $modal->delete();
    }

    public function getAllModals(): Collection
    {
        return PromotionalModal::withTrashed()
            ->byPriority()
            ->get();
    }

    public function getPaginatedModals(int $perPage = 15): LengthAwarePaginator
    {
        return PromotionalModal::withTrashed()
            ->byPriority()
            ->paginate($perPage);
    }

    public function getActiveModals(): Collection
    {
        return PromotionalModal::active()
            ->inTimeframe()
            ->byPriority()
            ->get();
    }

    /**
     * Get promotional modals that should be displayed to a specific user on a specific page.
     */
    public function getModalsForUser(
        ?User $user = null, 
        string $page = 'home',
        string $type = 'modal',
        ?string $sessionId = null,
        int $limit = 3
    ): Collection {
        // Get user's membership levels if authenticated
        $membershipLevelIds = [];
        if ($user) {
            $membershipLevelIds = $user->memberships()
                ->where('is_active', true)
                ->pluck('membership_level_id')
                ->toArray();
        }

        $query = PromotionalModal::active()
            ->inTimeframe()
            ->forType($type)
            ->forPage($page)
            ->forMembershipLevels($membershipLevelIds)
            ->byPriority();

        $potentialModals = $query->get();


        // Filter based on display frequency and user history
        $validModals = $potentialModals->filter(function (PromotionalModal $modal) use ($user, $page, $sessionId) {
            return $modal->shouldShowToUser($user, $page, $sessionId);
        });

        return $validModals->take($limit);
    }

    /**
     * Record an impression, click, or dismissal.
     */
    public function recordImpression(
        PromotionalModal $modal,
        string $action = 'impression',
        ?User $user = null,
        ?string $sessionId = null,
        ?string $pageUrl = null,
        ?array $metadata = null,
        ?Request $request = null
    ): PromotionalModalImpression {
        return $this->recordImpressionAction->execute(
            $modal, 
            $action, 
            $user, 
            $sessionId, 
            $pageUrl, 
            $metadata, 
            $request
        );
    }

    public function toggleActive(PromotionalModal $modal): PromotionalModal
    {
        $modal->update(['is_active' => !$modal->is_active]);
        return $modal;
    }

    public function updateSortOrder(array $modalOrders): void
    {
        foreach ($modalOrders as $order) {
            if (isset($order['id']) && isset($order['sort_order'])) {
                PromotionalModal::where('id', $order['id'])
                    ->update(['sort_order' => $order['sort_order']]);
            }
        }
    }

    public function searchModals(string $query): Collection
    {
        return PromotionalModal::where(function ($q) use ($query) {
            $q->where('title->en', 'like', "%{$query}%")
                ->orWhere('title->zh-TW', 'like', "%{$query}%")
                ->orWhere('title->zh-CN', 'like', "%{$query}%")
                ->orWhere('content->en', 'like', "%{$query}%")
                ->orWhere('content->zh-TW', 'like', "%{$query}%")
                ->orWhere('content->zh-CN', 'like', "%{$query}%")
                ->orWhere('button_text', 'like', "%{$query}%")
                ->orWhere('button_url', 'like', "%{$query}%");
        })
            ->byPriority()
            ->get();
    }

    /**
     * Get analytics data for a modal.
     */
    public function getModalAnalytics(PromotionalModal $modal, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $impressionsQuery = $modal->impressions();
        
        if ($startDate && $endDate) {
            $impressionsQuery->inDateRange($startDate, $endDate);
        }

        $totalImpressions = $impressionsQuery->impressions()->count();
        $totalClicks = $impressionsQuery->clicks()->count();
        $totalDismissals = $impressionsQuery->dismissals()->count();

        $conversionRate = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) * 100 : 0;
        $dismissalRate = $totalImpressions > 0 ? ($totalDismissals / $totalImpressions) * 100 : 0;

        // Get daily breakdown
        $dailyStats = $impressionsQuery->select([
            \DB::raw('DATE(created_at) as date'),
            \DB::raw('SUM(CASE WHEN action = "impression" THEN 1 ELSE 0 END) as impressions'),
            \DB::raw('SUM(CASE WHEN action = "click" THEN 1 ELSE 0 END) as clicks'),
            \DB::raw('SUM(CASE WHEN action = "dismiss" THEN 1 ELSE 0 END) as dismissals')
        ])
            ->groupBy(\DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->toArray();

        // Get top pages
        $topPages = $impressionsQuery->select('page_url')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull('page_url')
            ->groupBy('page_url')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'total_impressions' => $totalImpressions,
            'total_clicks' => $totalClicks,
            'total_dismissals' => $totalDismissals,
            'conversion_rate' => round($conversionRate, 2),
            'dismissal_rate' => round($dismissalRate, 2),
            'daily_stats' => $dailyStats,
            'top_pages' => $topPages,
        ];
    }

    /**
     * Get system-wide promotional modal analytics.
     */
    public function getSystemAnalytics(?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $impressionsQuery = PromotionalModalImpression::query();
        
        if ($startDate && $endDate) {
            $impressionsQuery->inDateRange($startDate, $endDate);
        }

        $totalImpressions = $impressionsQuery->impressions()->count();
        $totalClicks = $impressionsQuery->clicks()->count();
        $totalDismissals = $impressionsQuery->dismissals()->count();

        $conversionRate = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) * 100 : 0;

        // Get top performing modals
        $topModals = PromotionalModal::withCount(['impressions as impression_count' => function ($q) use ($startDate, $endDate) {
            $q->impressions();
            if ($startDate && $endDate) {
                $q->inDateRange($startDate, $endDate);
            }
        }])
            ->withCount(['impressions as click_count' => function ($q) use ($startDate, $endDate) {
                $q->clicks();
                if ($startDate && $endDate) {
                    $q->inDateRange($startDate, $endDate);
                }
            }])
            ->get()
            ->filter(fn($modal) => $modal->impression_count > 0)
            ->sortByDesc('impression_count')
            ->take(10)
            ->map(function ($modal) {
                $modal->conversion_rate = $modal->impression_count > 0 
                    ? round(($modal->click_count / $modal->impression_count) * 100, 2)
                    : 0;
                return $modal;
            })
            ->values();

        return [
            'total_impressions' => $totalImpressions,
            'total_clicks' => $totalClicks,
            'total_dismissals' => $totalDismissals,
            'conversion_rate' => round($conversionRate, 2),
            'active_modals_count' => PromotionalModal::active()->count(),
            'total_modals_count' => PromotionalModal::count(),
            'top_modals' => $topModals->toArray(),
        ];
    }

    /**
     * Bulk update modal priorities.
     */
    public function bulkUpdatePriorities(array $priorities): void
    {
        foreach ($priorities as $data) {
            if (isset($data['id']) && isset($data['priority'])) {
                PromotionalModal::where('id', $data['id'])
                    ->update(['priority' => $data['priority']]);
            }
        }
    }
}