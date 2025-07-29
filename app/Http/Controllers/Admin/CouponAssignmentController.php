<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\User;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Services\CouponService;
use App\Modules\Coupon\DataTransferObjects\IssueCouponData;
use App\Enums\RoleNameEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CouponAssignmentController extends Controller
{
    public function __construct(protected CouponService $couponService)
    {
        $this->middleware('auth');
        $this->middleware('role:' . RoleNameEnum::ADMIN->value);
    }

    /**
     * Display the mass coupon assignment interface
     */
    public function index(Request $request): InertiaResponse
    {
        $request->validate([
            'coupon_id' => 'nullable|exists:coupons,id',
        ]);

        // Get available coupons based on user permissions
        $couponsQuery = Coupon::with('organizer')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });

        // Restrict organizers to their own coupons
        if (!auth()->user()->hasRole(RoleNameEnum::ADMIN)) {
            $organizerIds = auth()->user()->organizers->pluck('id');
            $couponsQuery->whereIn('organizer_id', $organizerIds);
        }

        $coupons = $couponsQuery->orderBy('name')->get(['id', 'name', 'code', 'organizer_id', 'type', 'discount_value', 'discount_type']);

        // Get organizers for filtering
        $organizersQuery = Organizer::orderBy('name');
        if (!auth()->user()->hasRole(RoleNameEnum::ADMIN)) {
            $organizerIds = auth()->user()->organizers->pluck('id');
            $organizersQuery->whereIn('id', $organizerIds);
        }
        $organizers = $organizersQuery->get(['id', 'name']);

        // Handle pre-selected coupon
        $preSelectedCouponId = $request->get('coupon_id');
        $preSelectedCoupon = null;
        
        if ($preSelectedCouponId) {
            $preSelectedCoupon = $coupons->firstWhere('id', $preSelectedCouponId);
            
            // Verify user has permission to assign this coupon
            if ($preSelectedCoupon && !$this->canAssignCoupon(Coupon::find($preSelectedCouponId))) {
                $preSelectedCoupon = null;
                $preSelectedCouponId = null;
            }
        }

        return Inertia::render('Admin/Coupons/MassAssignment', [
            'pageTitle' => 'Mass Coupon Assignment',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Coupons', 'href' => route('admin.coupons.index')],
                ['text' => 'Mass Assignment']
            ],
            'coupons' => $coupons,
            'organizers' => $organizers,
            'preSelectedCouponId' => $preSelectedCouponId,
            'preSelectedCoupon' => $preSelectedCoupon,
        ]);
    }

    /**
     * Search for users to assign coupons to
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|min:2|max:100',
            'organizer_id' => 'nullable|exists:organizers,id',
            'limit' => 'nullable|integer|min:5|max:100',
        ]);

        $search = $request->get('search', '');
        $organizerId = $request->get('organizer_id');
        $limit = $request->get('limit', 20);

        $query = User::select('id', 'name', 'email', 'created_at');

        // Apply search filters
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // If organizer filter is applied, get users who have used that organizer's events
        if ($organizerId) {
            // This would need to be adjusted based on your event/booking system
            // For now, we'll just return all users
        }

        $users = $query->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'member_since' => $user->created_at->format('M Y'),
                ];
            });

        return response()->json([
            'users' => $users,
            'total' => $users->count(),
        ]);
    }

    /**
     * Get user statistics for selected users
     */
    public function getUserStats(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array|min:1|max:500',
            'user_ids.*' => 'exists:users,id',
        ]);

        $userIds = $request->get('user_ids');

        // Get statistics for selected users
        $usersWithCoupons = UserCoupon::whereIn('user_id', $userIds)
            ->distinct('user_id')
            ->count('user_id');
            
        $stats = [
            'total_users' => count($userIds),
            'active_users' => User::whereIn('id', $userIds)->count(),
            'users_with_coupons' => $usersWithCoupons,
        ];

        return response()->json($stats);
    }

    /**
     * Perform mass coupon assignment
     */
    public function assign(Request $request): JsonResponse
    {
        $request->validate([
            'coupon_id' => 'required|exists:coupons,id',
            'user_ids' => 'required|array|min:1|max:500',
            'user_ids.*' => 'exists:users,id',
            'quantity' => 'required|integer|min:1|max:10',
            'expires_at' => 'nullable|date|after:now',
            'notes' => 'nullable|string|max:500',
        ]);

        $coupon = Coupon::findOrFail($request->get('coupon_id'));
        $userIds = $request->get('user_ids');
        $quantity = $request->get('quantity');
        $expiresAt = $request->get('expires_at') ? now()->parse($request->get('expires_at')) : null;
        $notes = $request->get('notes');

        // Authorization check
        if (!$this->canAssignCoupon($coupon)) {
            throw ValidationException::withMessages([
                'coupon_id' => ['You do not have permission to assign this coupon.']
            ]);
        }

        // Validate coupon is active and not expired
        if (!$coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon_id' => ['Cannot assign inactive coupon.']
            ]);
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'coupon_id' => ['Cannot assign expired coupon.']
            ]);
        }

        try {
            DB::beginTransaction();

            $successCount = 0;
            $errors = [];

            foreach ($userIds as $userId) {
                try {
                    $user = User::findOrFail($userId);
                    
                    // Create IssueCouponData for each assignment
                    $issuanceData = IssueCouponData::from([
                        'coupon_id' => $coupon->id,
                        'user_id' => $userId,
                        'issued_by_user_id' => auth()->id(),
                        'expires_at' => $expiresAt,
                        'assignment_method' => 'manual',
                        'assignment_reason' => 'mass_assignment',
                        'assignment_notes' => $notes,
                    ]);

                    // Issue the coupon(s)
                    $this->couponService->issueCoupon($issuanceData, $quantity);
                    $successCount += $quantity;

                } catch (\Exception $e) {
                    $errors[] = "Failed to assign to {$user->name} ({$user->email}): " . $e->getMessage();
                    Log::warning("Mass coupon assignment failed for user {$userId}", [
                        'coupon_id' => $coupon->id,
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            // Log the mass assignment
            Log::info("Mass coupon assignment completed", [
                'coupon_id' => $coupon->id,
                'coupon_name' => $coupon->name,
                'assigned_by' => auth()->id(),
                'total_users' => count($userIds),
                'quantity_per_user' => $quantity,
                'total_coupons_issued' => $successCount,
                'errors_count' => count($errors),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully assigned {$successCount} coupons to " . count($userIds) . " users.",
                'stats' => [
                    'total_users' => count($userIds),
                    'quantity_per_user' => $quantity,
                    'total_coupons_issued' => $successCount,
                    'errors_count' => count($errors),
                ],
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Mass coupon assignment failed", [
                'coupon_id' => $coupon->id,
                'user_ids' => $userIds,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Mass assignment failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get assignment history
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'coupon_id' => 'nullable|exists:coupons,id',
            'limit' => 'nullable|integer|min:10|max:100',
        ]);

        $couponId = $request->get('coupon_id');
        $limit = $request->get('limit', 50);

        $query = \App\Modules\Coupon\Models\UserCoupon::with(['user:id,name,email', 'coupon:id,name,code'])
            ->where('assignment_reason', 'mass_assignment')
            ->orderBy('created_at', 'desc');

        if ($couponId) {
            $query->where('coupon_id', $couponId);
        }

        // Restrict organizers to their own coupons
        if (!auth()->user()->hasRole(RoleNameEnum::ADMIN)) {
            $organizerIds = auth()->user()->organizers->pluck('id');
            $query->whereHas('coupon', function ($q) use ($organizerIds) {
                $q->whereIn('organizer_id', $organizerIds);
            });
        }

        $assignments = $query->limit($limit)->get()->map(function ($userCoupon) {
            return [
                'id' => $userCoupon->id,
                'user_name' => $userCoupon->user->name,
                'user_email' => $userCoupon->user->email,
                'coupon_name' => $userCoupon->coupon->name,
                'coupon_code' => $userCoupon->coupon->code,
                'unique_code' => $userCoupon->unique_code,
                'status' => $userCoupon->status,
                'assigned_at' => $userCoupon->created_at->format('M d, Y H:i'),
                'assigned_by' => $userCoupon->assignedBy ? $userCoupon->assignedBy->name : 'Unknown',
                'notes' => $userCoupon->assignment_notes,
            ];
        });

        return response()->json([
            'assignments' => $assignments,
            'total' => $assignments->count(),
        ]);
    }

    /**
     * Check if user can assign a specific coupon
     */
    private function canAssignCoupon(Coupon $coupon): bool
    {
        // Platform admins can assign any coupon
        if (auth()->user()->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Organizers can only assign their own coupons
        $organizerIds = auth()->user()->organizers->pluck('id');
        return $organizerIds->contains($coupon->organizer_id);
    }
}