<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Coupon\Services\CouponService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyCouponsController extends Controller
{
    public function __construct(
        protected CouponService $couponService
    ) {
        $this->middleware('auth');
    }

    /**
     * Display the user's coupon wallet.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        
        // Get filter parameters
        $status = $request->get('status', 'all'); // all, active, expired, used
        $search = $request->get('search');
        
        // Get user's coupons based on filters
        $coupons = $this->getUserCouponsWithFilters($user->id, $status, $search);
        
        // Get summary statistics
        $statistics = $this->getCouponStatistics($user->id);
        
        return Inertia::render('Public/MyCoupons', [
            'coupons' => $coupons,
            'statistics' => $statistics,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Get user coupons with filtering
     */
    private function getUserCouponsWithFilters(int $userId, string $status, ?string $search)
    {
        $query = \App\Modules\Coupon\Models\UserCoupon::with([
            'coupon:id,name,description,code,redemption_methods,merchant_pin',
            'usageLogs:id,user_coupon_id,created_at,location'
        ])
        ->where('user_id', $userId)
        ->orderBy('created_at', 'desc');

        // Apply status filter
        switch ($status) {
            case 'active':
                $query->where('status', \App\Modules\Coupon\Enums\UserCouponStatusEnum::ACTIVE)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
                break;
            case 'expired':
                $query->where(function ($q) {
                    $q->where('status', \App\Modules\Coupon\Enums\UserCouponStatusEnum::EXPIRED)
                        ->orWhere('expires_at', '<=', now());
                });
                break;
            case 'used':
                $query->where('status', \App\Modules\Coupon\Enums\UserCouponStatusEnum::FULLY_USED);
                break;
        }

        // Apply search filter
        if ($search) {
            $query->whereHas('coupon', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })->orWhere('unique_code', 'like', "%{$search}%");
        }

        return $query->paginate(12)->through(function ($userCoupon) {
            return [
                'id' => $userCoupon->id,
                'unique_code' => $userCoupon->unique_code,
                'status' => $userCoupon->status,
                'times_used' => $userCoupon->times_used,
                'times_can_be_used' => $userCoupon->times_can_be_used,
                'expires_at' => $userCoupon->expires_at?->format('Y-m-d H:i:s'),
                'created_at' => $userCoupon->created_at->format('Y-m-d H:i:s'),
                'coupon' => [
                    'id' => $userCoupon->coupon->id,
                    'name' => $userCoupon->coupon->name,
                    'description' => $userCoupon->coupon->description,
                    'code' => $userCoupon->coupon->code,
                    'redemption_methods' => $userCoupon->coupon->redemption_methods,
                    'has_pin' => !is_null($userCoupon->coupon->merchant_pin),
                ],
                'usage_logs' => $userCoupon->usageLogs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                        'location' => $log->location,
                    ];
                }),
                'is_expired' => $userCoupon->expires_at && $userCoupon->expires_at->isPast(),
                'is_fully_used' => $userCoupon->times_used >= $userCoupon->times_can_be_used,
                'is_active' => $userCoupon->status === \App\Modules\Coupon\Enums\UserCouponStatusEnum::ACTIVE &&
                              (!$userCoupon->expires_at || $userCoupon->expires_at->isFuture()) &&
                              $userCoupon->times_used < $userCoupon->times_can_be_used,
            ];
        });
    }

    /**
     * Get coupon statistics for the user
     */
    private function getCouponStatistics(int $userId): array
    {
        $userCoupons = \App\Modules\Coupon\Models\UserCoupon::where('user_id', $userId);
        
        return [
            'total' => $userCoupons->count(),
            'active' => $userCoupons->clone()->where('status', \App\Modules\Coupon\Enums\UserCouponStatusEnum::ACTIVE)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })->count(),
            'expired' => $userCoupons->clone()->where(function ($q) {
                $q->where('status', \App\Modules\Coupon\Enums\UserCouponStatusEnum::EXPIRED)
                    ->orWhere('expires_at', '<=', now());
            })->count(),
            'fully_used' => $userCoupons->clone()->where('status', \App\Modules\Coupon\Enums\UserCouponStatusEnum::FULLY_USED)->count(),
        ];
    }
}