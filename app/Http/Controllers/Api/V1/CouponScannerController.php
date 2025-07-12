<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Coupon\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CouponScannerController extends Controller
{
    public function __construct(private CouponService $couponService)
    {
        // Add auth middleware for scanner access
        // For now, it's open for development
        // $this->middleware('auth:sanctum');
    }

    /**
     * Validate a coupon by its unique code.
     * This is used by the scanner to check validity before redemption.
     *
     * @param string $uniqueCode
     * @return JsonResponse
     */
    public function show(string $uniqueCode): JsonResponse
    {
        try {
            $validationResult = $this->couponService->validateCoupon($uniqueCode);

            if (! $validationResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Coupon',
                    'errors' => $validationResult['reasons'],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Coupon is valid for redemption.',
                'data' => [
                    'user_coupon' => $validationResult['user_coupon'],
                    'details' => $validationResult['details'],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error("Coupon validation error for code {$uniqueCode}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during coupon validation.',
            ], 500);
        }
    }

    /**
     * Redeem a coupon by its unique code.
     *
     * @param Request $request
     * @param string $uniqueCode
     * @return JsonResponse
     */
    public function store(Request $request, string $uniqueCode): JsonResponse
    {
        // First, validate the coupon to ensure it's still valid before attempting redemption.
        $validationResult = $this->couponService->validateCoupon($uniqueCode);
        if (! $validationResult['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Coupon',
                'errors' => $validationResult['reasons'],
            ], 422);
        }

        try {
            $redemptionResult = $this->couponService->redeemCoupon(
                $uniqueCode,
                $request->input('location'),
                $request->input('details', [])
            );

            if (! $redemptionResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $redemptionResult['message'] ?? 'Failed to redeem coupon.',
                    'errors' => $redemptionResult['reasons'] ?? [],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $redemptionResult['message'] ?? 'Coupon redeemed successfully.',
                'data' => $redemptionResult['data'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error("Coupon redemption error for code {$uniqueCode}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during coupon redemption.',
            ], 500);
        }
    }
}
