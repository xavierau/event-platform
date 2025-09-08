<?php

namespace App\Modules\PromotionalModal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PromotionalModal\Models\PromotionalModal;
use App\Modules\PromotionalModal\Services\PromotionalModalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebPromotionalModalController extends Controller
{
    public function __construct(private PromotionalModalService $promotionalModalService)
    {
        $this->authorizeResource(PromotionalModal::class, 'promotional_modal');
    }

    /**
     * Display the promotional modals admin index page.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search_title' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:modal,banner',
            'status' => 'nullable|string|in:active,inactive',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Get paginated modals using the service
        $modals = $this->promotionalModalService->getPaginatedModals(
            $validated['per_page'] ?? 15
        );

        // Apply filters if provided
        if (!empty($validated['search_title'])) {
            $searchResults = $this->promotionalModalService->searchModals($validated['search_title']);
            // Convert search results to paginated format for consistency
            $modals = new \Illuminate\Pagination\LengthAwarePaginator(
                $searchResults,
                $searchResults->count(),
                $validated['per_page'] ?? 15,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        // Apply type filter
        if (!empty($validated['type'])) {
            $filteredItems = $modals->getCollection()->filter(fn($modal) => $modal->type === $validated['type']);
            $modals->setCollection($filteredItems);
        }

        // Apply status filter
        if (!empty($validated['status'])) {
            $isActive = $validated['status'] === 'active';
            $filteredItems = $modals->getCollection()->filter(fn($modal) => $modal->is_active === $isActive);
            $modals->setCollection($filteredItems);
        }

        // Transform the data for the Vue component
        $transformedModals = $modals->getCollection()->map(function ($modal) {
            return [
                'id' => $modal->id,
                'title' => $modal->title,
                'type' => $modal->type,
                'pages' => $modal->pages,
                'is_active' => $modal->is_active,
                'impressions_count' => $modal->impressions()->count(),
                'clicks_count' => $modal->impressions()->where('action', 'click')->count(),
                'conversion_rate' => $this->calculateConversionRate($modal),
                'start_at' => $modal->start_at?->toISOString(),
                'end_at' => $modal->end_at?->toISOString(),
            ];
        });

        // Create paginated response structure that matches what the Vue component expects
        $paginatedData = [
            'data' => $transformedModals->values()->all(),
            'links' => $this->buildPaginationLinks($modals),
            'from' => $modals->firstItem(),
            'to' => $modals->lastItem(),
            'total' => $modals->total(),
            'per_page' => $modals->perPage(),
        ];

        $filters = [
            'search_title' => $validated['search_title'] ?? '',
            'type' => $validated['type'] ?? '',
            'status' => $validated['status'] ?? '',
        ];

        return Inertia::render('Admin/PromotionalModals/Index', [
            'promotionalModals' => $paginatedData,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new promotional modal.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/PromotionalModals/Create');
    }

    /**
     * Show the form for editing a promotional modal.
     */
    public function edit(PromotionalModal $promotionalModal): Response
    {
        $modal = $promotionalModal->load('media');

        return Inertia::render('Admin/PromotionalModals/Edit', [
            'promotionalModal' => [
                'id' => $modal->id,
                'title' => $modal->title,
                'content' => $modal->content,
                'type' => $modal->type,
                'pages' => $modal->pages,
                'membership_levels' => $modal->membership_levels,
                'user_segments' => $modal->user_segments,
                'start_at' => $modal->start_at?->toISOString(),
                'end_at' => $modal->end_at?->toISOString(),
                'display_frequency' => $modal->display_frequency,
                'cooldown_hours' => $modal->cooldown_hours,
                'is_active' => $modal->is_active,
                'priority' => $modal->priority,
                'sort_order' => $modal->sort_order,
                'button_text' => $modal->button_text,
                'button_url' => $modal->button_url,
                'is_dismissible' => $modal->is_dismissible,
                'display_conditions' => $modal->display_conditions,
                'banner_image_url' => $modal->getBannerImageUrl(),
                'background_image_url' => $modal->getBackgroundImageUrl(),
                'impressions_count' => $modal->impressions()->count(),
                'clicks_count' => $modal->impressions()->where('action', 'click')->count(),
                'conversion_rate' => $this->calculateConversionRate($modal),
            ],
        ]);
    }

    /**
     * Display the analytics page for promotional modals.
     */
    public function analytics(): Response
    {
        return Inertia::render('Admin/PromotionalModals/Analytics');
    }

    /**
     * Calculate conversion rate for a modal.
     */
    private function calculateConversionRate(PromotionalModal $modal): float
    {
        $totalImpressions = $modal->impressions()->where('action', 'impression')->count();
        $clicks = $modal->impressions()->where('action', 'click')->count();

        if ($totalImpressions === 0) {
            return 0.0;
        }

        return round(($clicks / $totalImpressions) * 100, 2);
    }

    /**
     * Build pagination links array that matches the Vue component expectations.
     */
    private function buildPaginationLinks($paginator): array
    {
        $links = [];
        
        // Previous link
        $links[] = [
            'url' => $paginator->previousPageUrl(),
            'label' => '&laquo; Previous',
            'active' => false,
        ];
        
        // Page number links
        foreach (range(1, $paginator->lastPage()) as $page) {
            $links[] = [
                'url' => $paginator->url($page),
                'label' => (string) $page,
                'active' => $page === $paginator->currentPage(),
            ];
        }
        
        // Next link
        $links[] = [
            'url' => $paginator->nextPageUrl(),
            'label' => 'Next &raquo;',
            'active' => false,
        ];
        
        return $links;
    }
}