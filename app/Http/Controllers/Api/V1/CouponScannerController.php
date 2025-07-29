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
            $validationResult = $this->couponService->validateCouponForApi($uniqueCode);

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
        $validationResult = $this->couponService->validateCouponForApi($uniqueCode);
        if (! $validationResult['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Coupon',
                'errors' => $validationResult['reasons'],
            ], 422);
        }

        try {
            // Use QR-based redemption (original method but with array response handling)
            $userCoupon = $this->couponService->validateCoupon($uniqueCode);
            $redeemedCoupon = $this->couponService->redeemCoupon(
                $uniqueCode,
                $request->input('location'),
                $request->input('details', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Coupon redeemed successfully via QR code.',
                'data' => [
                    'user_coupon' => $redeemedCoupon,
                    'redemption_method' => 'qr',
                ],
            ]);
        } catch (Throwable $e) {
            Log::error("Coupon redemption error for code {$uniqueCode}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during coupon redemption.',
            ], 500);
        }
    }

    /**
     * Redeem a coupon using PIN validation
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function redeemByPin(Request $request): JsonResponse
    {
        $request->validate([
            'unique_code' => 'required|string',
            'merchant_pin' => 'required|string|size:6',
            'location' => 'nullable|string|max:255',
            'details' => 'nullable|array',
        ]);

        try {
            $redemptionResult = $this->couponService->redeemCouponByPin(
                $request->input('unique_code'),
                $request->input('merchant_pin'),
                $request->input('location'),
                $request->input('details', [])
            );

            if (!$redemptionResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $redemptionResult['message'],
                    'errors' => $redemptionResult['reasons'],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $redemptionResult['message'],
                'data' => $redemptionResult['data'],
            ]);
        } catch (Throwable $e) {
            Log::error("PIN redemption error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during PIN redemption.',
            ], 500);
        }
    }

    /**
     * Validate a coupon using PIN without redeeming it
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validatePin(Request $request): JsonResponse
    {
        $request->validate([
            'unique_code' => 'required|string',
            'merchant_pin' => 'required|string|size:6',
        ]);

        try {
            $validationResult = $this->couponService->validateCouponPin(
                $request->input('unique_code'),
                $request->input('merchant_pin')
            );

            if (!$validationResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid PIN or coupon',
                    'errors' => $validationResult['reasons'],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'PIN validation successful.',
                'data' => [
                    'user_coupon' => $validationResult['user_coupon'],
                    'details' => $validationResult['details'],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error("PIN validation error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during PIN validation.',
            ], 500);
        }
    }
}
