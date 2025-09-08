<?php

namespace App\Modules\PromotionalModal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PromotionalModal\DataTransferObjects\PromotionalModalData;
use App\Modules\PromotionalModal\Models\PromotionalModal;
use App\Modules\PromotionalModal\Services\PromotionalModalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromotionalModalController extends Controller
{
    public function __construct(private PromotionalModalService $promotionalModalService)
    {
    }

    /**
     * Get promotional modals for the current user on a specific page.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'required|string|max:255',
            'type' => 'nullable|string|in:modal,banner',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        $user = Auth::user();
        $sessionId = $request->session()->getId();
        
        $modals = $this->promotionalModalService->getModalsForUser(
            user: $user,
            page: $validated['page'],
            type: $validated['type'] ?? 'modal',
            sessionId: $sessionId,
            limit: $validated['limit'] ?? 3
        );


        // Transform modals to include necessary data for frontend
        $transformedModals = $modals->map(function (PromotionalModal $modal) {
            return [
                'id' => $modal->id,
                'title' => $modal->title,
                'content' => $modal->content,
                'type' => $modal->type,
                'button_text' => $modal->button_text,
                'button_url' => $modal->button_url,
                'is_dismissible' => $modal->is_dismissible,
                'banner_image_url' => $modal->getBannerImageUrl(),
                'background_image_url' => $modal->getBackgroundImageUrl(),
                'display_conditions' => $modal->display_conditions,
            ];
        });

        return response()->json([
            'data' => $transformedModals,
            'meta' => [
                'count' => $transformedModals->count(),
                'page' => $validated['page'],
                'type' => $validated['type'] ?? 'modal',
            ],
        ]);
    }

    /**
     * Record an impression, click, or dismissal.
     */
    public function recordImpression(Request $request, PromotionalModal $promotionalModal): JsonResponse
    {
        // If route model binding fails, try to find the modal manually
        if (!$promotionalModal->exists) {
            $modalId = $request->route('promotional_modal');
            $promotionalModal = PromotionalModal::find($modalId);
            
            if (!$promotionalModal) {
                return response()->json([
                    'message' => 'Promotional modal not found.',
                ], 404);
            }
        }

        $validated = $request->validate([
            'action' => 'required|string|in:impression,click,dismiss',
            'page_url' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
        ]);

        $user = Auth::user();
        $sessionId = $request->session()->getId();

        $impression = $this->promotionalModalService->recordImpression(
            modal: $promotionalModal,
            action: $validated['action'],
            user: $user,
            sessionId: $sessionId,
            pageUrl: $validated['page_url'] ?? $request->url(),
            metadata: $validated['metadata'] ?? null,
            request: $request
        );

        return response()->json([
            'message' => ucfirst($validated['action']) . ' recorded successfully.',
            'data' => [
                'id' => $impression->id,
                'action' => $impression->action,
                'created_at' => $impression->created_at,
            ],
        ]);
    }

    /**
     * Get a specific modal for display.
     */
    public function show(PromotionalModal $promotionalModal, Request $request): JsonResponse
    {
        // If route model binding fails, try to find the modal manually
        if (!$promotionalModal->exists) {
            $modalId = $request->route('promotional_modal');
            $promotionalModal = PromotionalModal::find($modalId);
            
            if (!$promotionalModal) {
                return response()->json([
                    'message' => 'Promotional modal not found.',
                ], 404);
            }
        }

        $user = Auth::user();
        $sessionId = $request->session()->getId();

        // Check if user should see this modal
        if (!$promotionalModal->shouldShowToUser($user, $request->input('page', 'home'), $sessionId)) {
            return response()->json([
                'message' => 'Modal not available for display.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $promotionalModal->id,
                'title' => $promotionalModal->title,
                'content' => $promotionalModal->content,
                'type' => $promotionalModal->type,
                'button_text' => $promotionalModal->button_text,
                'button_url' => $promotionalModal->button_url,
                'is_dismissible' => $promotionalModal->is_dismissible,
                'banner_image_url' => $promotionalModal->getBannerImageUrl(),
                'background_image_url' => $promotionalModal->getBackgroundImageUrl(),
                'display_conditions' => $promotionalModal->display_conditions,
            ],
        ]);
    }

    /**
     * Batch record impressions (for analytics optimization).
     */
    public function batchImpressions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'impressions' => 'required|array|max:50',
            'impressions.*.modal_id' => 'required|integer|exists:promotional_modals,id',
            'impressions.*.action' => 'required|string|in:impression,click,dismiss',
            'impressions.*.page_url' => 'nullable|string|max:500',
            'impressions.*.metadata' => 'nullable|array',
        ]);

        $user = Auth::user();
        $sessionId = $request->session()->getId();

        $impressionData = collect($validated['impressions'])->map(function ($impression) use ($user, $sessionId, $request) {
            return [
                'promotional_modal_id' => $impression['modal_id'],
                'user_id' => $user?->id,
                'session_id' => $sessionId,
                'action' => $impression['action'],
                'page_url' => $impression['page_url'] ?? $request->url(),
                'metadata' => $impression['metadata'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ];
        })->toArray();

        // Use the service method to record bulk impressions
        foreach ($impressionData as $data) {
            $modal = PromotionalModal::find($data['promotional_modal_id']);
            if ($modal) {
                $this->promotionalModalService->recordImpression(
                    modal: $modal,
                    action: $data['action'],
                    user: $user,
                    sessionId: $sessionId,
                    pageUrl: $data['page_url'],
                    metadata: $data['metadata'],
                    request: $request
                );
            }
        }

        return response()->json([
            'message' => 'Impressions recorded successfully.',
            'data' => [
                'count' => count($impressionData),
                'recorded_at' => now(),
            ],
        ]);
    }
}