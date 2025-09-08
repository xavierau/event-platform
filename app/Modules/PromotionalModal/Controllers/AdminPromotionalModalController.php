<?php

namespace App\Modules\PromotionalModal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PromotionalModal\DataTransferObjects\PromotionalModalData;
use App\Modules\PromotionalModal\Models\PromotionalModal;
use App\Modules\PromotionalModal\Services\PromotionalModalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPromotionalModalController extends Controller
{
    public function __construct(private PromotionalModalService $promotionalModalService)
    {
        $this->authorizeResource(PromotionalModal::class, 'promotional_modal');
    }

    /**
     * Display a listing of promotional modals.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:modal,banner',
            'status' => 'nullable|string|in:active,inactive,all',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if (!empty($validated['search'])) {
            $modals = $this->promotionalModalService->searchModals($validated['search']);
            
            return response()->json([
                'data' => $modals,
                'meta' => [
                    'search' => $validated['search'],
                    'count' => $modals->count(),
                ],
            ]);
        }

        $modals = $this->promotionalModalService->getPaginatedModals(
            $validated['per_page'] ?? 15
        );

        // Apply additional filters
        if (!empty($validated['type'])) {
            $modals->getCollection()->filter(fn($modal) => $modal->type === $validated['type']);
        }

        if (!empty($validated['status']) && $validated['status'] !== 'all') {
            $isActive = $validated['status'] === 'active';
            $modals->getCollection()->filter(fn($modal) => $modal->is_active === $isActive);
        }

        return response()->json([
            'data' => $modals->items(),
            'meta' => [
                'current_page' => $modals->currentPage(),
                'last_page' => $modals->lastPage(),
                'per_page' => $modals->perPage(),
                'total' => $modals->total(),
                'from' => $modals->firstItem(),
                'to' => $modals->lastItem(),
                'filters' => $validated,
            ],
        ]);
    }

    /**
     * Store a newly created promotional modal.
     */
    public function store(Request $request): JsonResponse
    {
        $data = PromotionalModalData::validateAndCreate($request->all());
        
        $modal = $this->promotionalModalService->createModal($data);

        return response()->json([
            'message' => 'Promotional modal created successfully.',
            'data' => $modal->load('media'),
        ], 201);
    }

    /**
     * Display the specified promotional modal.
     */
    public function show(PromotionalModal $promotionalModal): JsonResponse
    {
        return response()->json([
            'data' => $promotionalModal->load('media'),
        ]);
    }

    /**
     * Update the specified promotional modal.
     */
    public function update(Request $request, PromotionalModal $promotionalModal): JsonResponse
    {
        $data = PromotionalModalData::validateAndCreate($request->all());
        
        $modal = $this->promotionalModalService->updateModal($promotionalModal, $data);

        return response()->json([
            'message' => 'Promotional modal updated successfully.',
            'data' => $modal->load('media'),
        ]);
    }

    /**
     * Remove the specified promotional modal.
     */
    public function destroy(PromotionalModal $promotionalModal): JsonResponse
    {
        $this->promotionalModalService->deleteModal($promotionalModal);

        return response()->json([
            'message' => 'Promotional modal deleted successfully.',
        ]);
    }

    /**
     * Toggle the active status of a promotional modal.
     */
    public function toggleStatus(PromotionalModal $promotionalModal): JsonResponse
    {
        $this->authorize('toggleStatus', $promotionalModal);

        $modal = $this->promotionalModalService->toggleActive($promotionalModal);

        return response()->json([
            'message' => 'Promotional modal status updated successfully.',
            'data' => [
                'id' => $modal->id,
                'is_active' => $modal->is_active,
            ],
        ]);
    }

    /**
     * Update sort order for multiple modals.
     */
    public function updateSortOrder(Request $request): JsonResponse
    {
        $this->authorize('bulkUpdate', PromotionalModal::class);

        $validated = $request->validate([
            'modals' => 'required|array',
            'modals.*.id' => 'required|integer|exists:promotional_modals,id',
            'modals.*.sort_order' => 'required|integer|min:0',
        ]);

        $this->promotionalModalService->updateSortOrder($validated['modals']);

        return response()->json([
            'message' => 'Sort order updated successfully.',
            'data' => [
                'updated_count' => count($validated['modals']),
            ],
        ]);
    }

    /**
     * Update priorities for multiple modals.
     */
    public function updatePriorities(Request $request): JsonResponse
    {
        $this->authorize('bulkUpdate', PromotionalModal::class);

        $validated = $request->validate([
            'modals' => 'required|array',
            'modals.*.id' => 'required|integer|exists:promotional_modals,id',
            'modals.*.priority' => 'required|integer|min:0|max:999',
        ]);

        $this->promotionalModalService->bulkUpdatePriorities($validated['modals']);

        return response()->json([
            'message' => 'Priorities updated successfully.',
            'data' => [
                'updated_count' => count($validated['modals']),
            ],
        ]);
    }

    /**
     * Get analytics for a specific promotional modal.
     */
    public function analytics(PromotionalModal $promotionalModal, Request $request): JsonResponse
    {
        $this->authorize('viewAnalytics', $promotionalModal);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = isset($validated['start_date']) ? new \DateTime($validated['start_date']) : null;
        $endDate = isset($validated['end_date']) ? new \DateTime($validated['end_date']) : null;

        $analytics = $this->promotionalModalService->getModalAnalytics(
            $promotionalModal, 
            $startDate, 
            $endDate
        );

        return response()->json([
            'data' => $analytics,
            'meta' => [
                'modal_id' => $promotionalModal->id,
                'modal_title' => $promotionalModal->title,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
            ],
        ]);
    }

    /**
     * Get system-wide promotional modal analytics.
     */
    public function systemAnalytics(Request $request): JsonResponse
    {
        $this->authorize('viewAnalytics', PromotionalModal::class);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = isset($validated['start_date']) ? new \DateTime($validated['start_date']) : null;
        $endDate = isset($validated['end_date']) ? new \DateTime($validated['end_date']) : null;

        $analytics = $this->promotionalModalService->getSystemAnalytics($startDate, $endDate);

        return response()->json([
            'data' => $analytics,
            'meta' => [
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Duplicate a promotional modal.
     */
    public function duplicate(PromotionalModal $promotionalModal): JsonResponse
    {
        $this->authorize('create', PromotionalModal::class);

        $duplicatedData = new PromotionalModalData(
            title: array_map(fn($title) => $title . ' (Copy)', $promotionalModal->title),
            content: $promotionalModal->content,
            type: $promotionalModal->type,
            pages: $promotionalModal->pages,
            membership_levels: $promotionalModal->membership_levels,
            user_segments: $promotionalModal->user_segments,
            start_at: null, // Reset timing
            end_at: null,
            display_frequency: $promotionalModal->display_frequency,
            cooldown_hours: $promotionalModal->cooldown_hours,
            is_active: false, // Start inactive
            priority: 0,
            sort_order: 0,
            button_text: $promotionalModal->button_text,
            button_url: $promotionalModal->button_url,
            is_dismissible: $promotionalModal->is_dismissible,
            display_conditions: $promotionalModal->display_conditions,
        );

        $duplicatedModal = $this->promotionalModalService->createModal($duplicatedData);

        // Copy media if exists
        foreach (['banner_image', 'background_image'] as $collection) {
            $media = $promotionalModal->getFirstMedia($collection);
            if ($media) {
                $duplicatedModal->addMediaFromUrl($media->getFullUrl())
                    ->toMediaCollection($collection);
            }
        }

        return response()->json([
            'message' => 'Promotional modal duplicated successfully.',
            'data' => $duplicatedModal->load('media'),
        ], 201);
    }
}