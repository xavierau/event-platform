<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class WishlistController extends Controller
{
    public function __construct(
        protected WishlistService $wishlistService
    ) {}

    // Note: Authentication is now handled by route middleware (session-based)

    /**
     * Get user's wishlist
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Use the formatted method for consistent frontend data structure
        $formattedWishlist = $this->wishlistService->getUserWishlistFormatted($userId);
        $count = count($formattedWishlist);

        return response()->json([
            'success' => true,
            'data' => [
                'wishlist' => $formattedWishlist,
                'count' => $count,
            ],
        ]);
    }

    /**
     * Add event to wishlist
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $result = $this->wishlistService->addToWishlist($userId, $request->event_id);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Event added to wishlist successfully',
                    'data' => [
                        'in_wishlist' => true,
                    ],
                ], Response::HTTP_CREATED);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Event is already in your wishlist',
                    'data' => [
                        'in_wishlist' => true,
                    ],
                ], Response::HTTP_OK);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Remove event from wishlist
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        $userId = $request->user()->id;

        $result = $this->wishlistService->removeFromWishlist($userId, $event->id);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Event removed from wishlist successfully',
                'data' => [
                    'in_wishlist' => false,
                ],
            ]);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'Event was not in your wishlist',
                'data' => [
                    'in_wishlist' => false,
                ],
            ]);
        }
    }

    /**
     * Toggle event in wishlist
     */
    public function toggle(Request $request, Event $event): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $result = $this->wishlistService->toggleWishlist($userId, $event->id);

            $message = $result['added']
                ? 'Event added to wishlist successfully'
                : 'Event removed from wishlist successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Check if event is in wishlist
     */
    public function check(Request $request, Event $event): JsonResponse
    {
        $userId = $request->user()->id;

        $inWishlist = $this->wishlistService->isInWishlist($userId, $event->id);

        return response()->json([
            'success' => true,
            'data' => [
                'in_wishlist' => $inWishlist,
            ],
        ]);
    }

    /**
     * Clear user's entire wishlist
     */
    public function clear(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $this->wishlistService->clearUserWishlist($userId);

        return response()->json([
            'success' => true,
            'message' => 'Wishlist cleared successfully',
            'data' => [
                'count' => 0,
            ],
        ]);
    }
}
