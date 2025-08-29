<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ChangeMembershipPlanAction;
use App\Actions\Admin\SyncMembershipWithStripeAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MembershipLevelController extends Controller
{
    public function index(): Response
    {
        $membershipLevels = MembershipLevel::withCount(['userMemberships', 'activeUserMemberships'])
            ->ordered()
            ->get()
            ->map(function ($level) {
                return [
                    'id' => $level->id,
                    'name' => $level->name,
                    'slug' => $level->slug,
                    'description' => $level->description,
                    'price' => $level->price,
                    'price_formatted' => '$' . number_format($level->price / 100, 2),
                    'duration_months' => $level->duration_months,
                    'stripe_product_id' => $level->stripe_product_id,
                    'stripe_price_id' => $level->stripe_price_id,
                    'is_active' => $level->is_active,
                    'sort_order' => $level->sort_order,
                    'user_memberships_count' => $level->user_memberships_count,
                    'active_user_memberships_count' => $level->active_user_memberships_count,
                    'benefits' => $level->benefits,
                    'max_users' => $level->max_users,
                    'available_slots' => $level->getAvailableSlots(),
                    'metadata' => $level->metadata,
                    'created_at' => $level->created_at,
                    'updated_at' => $level->updated_at,
                ];
            });

        return Inertia::render('Admin/MembershipLevels/Index', [
            'pageTitle' => 'Membership Levels',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Membership Levels']
            ],
            'membershipLevels' => $membershipLevels,
            'stats' => $this->getStats(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/MembershipLevels/Create', [
            'pageTitle' => 'Create Membership Level',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Membership Levels', 'href' => route('admin.membership-levels.index')],
                ['text' => 'Create']
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name.en' => 'required|string|max:255',
            'name.zh-TW' => 'nullable|string|max:255',
            'name.zh-CN' => 'nullable|string|max:255',
            'slug' => 'required|string|max:255|unique:membership_levels',
            'description.en' => 'nullable|string|max:500',
            'description.zh-TW' => 'nullable|string|max:500',
            'description.zh-CN' => 'nullable|string|max:500',
            'price' => 'required|integer|min:0',
            'points_cost' => 'nullable|integer|min:0',
            'duration_months' => 'nullable|integer|min:1',
            'stripe_product_id' => 'nullable|string|max:255',
            'stripe_price_id' => 'nullable|string|max:255|unique:membership_levels',
            'benefits.en' => 'nullable|string',
            'benefits.zh-TW' => 'nullable|string',
            'benefits.zh-CN' => 'nullable|string',
            'max_users' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'sort_order' => 'required|integer|min:0',
            'metadata' => 'array',
        ]);

        MembershipLevel::create($validated);

        return redirect()
            ->route('admin.membership-levels.index')
            ->with('success', 'Membership level created successfully.');
    }

    public function show(MembershipLevel $membershipLevel): Response
    {
        $membershipLevel->load(['userMemberships.user']);
        
        $recentSubscriptions = $membershipLevel->userMemberships()
            ->with('user')
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($membership) {
                return [
                    'id' => $membership->id,
                    'user' => [
                        'id' => $membership->user->id,
                        'name' => $membership->user->name,
                        'email' => $membership->user->email,
                    ],
                    'status' => $membership->status,
                    'started_at' => $membership->started_at,
                    'expires_at' => $membership->expires_at,
                    'payment_method' => $membership->payment_method,
                    'auto_renew' => $membership->auto_renew,
                ];
            });

        return Inertia::render('Admin/MembershipLevels/Show', [
            'pageTitle' => 'Membership Level Details',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Membership Levels', 'href' => route('admin.membership-levels.index')],
                ['text' => 'Details']
            ],
            'membershipLevel' => [
                'id' => $membershipLevel->id,
                'name' => $membershipLevel->getTranslations('name') ?: ['en' => '', 'zh-TW' => '', 'zh-CN' => ''],
                'slug' => $membershipLevel->slug,
                'description' => $membershipLevel->getTranslations('description') ?: ['en' => '', 'zh-TW' => '', 'zh-CN' => ''],
                'price' => $membershipLevel->price,
                'price_formatted' => '$' . number_format($membershipLevel->price / 100, 2),
                'duration_months' => $membershipLevel->duration_months,
                'stripe_product_id' => $membershipLevel->stripe_product_id,
                'stripe_price_id' => $membershipLevel->stripe_price_id,
                'is_active' => $membershipLevel->is_active,
                'sort_order' => $membershipLevel->sort_order,
                'benefits' => $membershipLevel->benefits ?: ['en' => '', 'zh-TW' => '', 'zh-CN' => ''],
                'max_users' => $membershipLevel->max_users,
                'available_slots' => $membershipLevel->getAvailableSlots(),
                'metadata' => $membershipLevel->metadata,
                'created_at' => $membershipLevel->created_at,
                'updated_at' => $membershipLevel->updated_at,
            ],
            'recentSubscriptions' => $recentSubscriptions,
            'stats' => [
                'total_users' => $membershipLevel->userMemberships()->count(),
                'active_users' => $membershipLevel->activeUserMemberships()->count(),
                'monthly_revenue' => $membershipLevel->price * $membershipLevel->activeUserMemberships()->count(),
            ],
        ]);
    }

    public function edit(MembershipLevel $membershipLevel): Response
    {
        return Inertia::render('Admin/MembershipLevels/Edit', [
            'pageTitle' => 'Edit Membership Level',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Membership Levels', 'href' => route('admin.membership-levels.index')],
                ['text' => 'Edit']
            ],
            'membershipLevel' => [
                'id' => $membershipLevel->id,
                'name' => $membershipLevel->getTranslations('name') ?: ['en' => '', 'zh-TW' => '', 'zh-CN' => ''],
                'slug' => $membershipLevel->slug,
                'description' => $membershipLevel->getTranslations('description') ?: ['en' => '', 'zh-TW' => '', 'zh-CN' => ''],
                'price' => $membershipLevel->price,
                'points_cost' => $membershipLevel->points_cost,
                'duration_months' => $membershipLevel->duration_months,
                'stripe_product_id' => $membershipLevel->stripe_product_id,
                'stripe_price_id' => $membershipLevel->stripe_price_id,
                'is_active' => $membershipLevel->is_active,
                'sort_order' => $membershipLevel->sort_order,
                'benefits' => $membershipLevel->benefits ?: ['en' => '', 'zh-TW' => '', 'zh-CN' => ''],
                'max_users' => $membershipLevel->max_users,
                'metadata' => $membershipLevel->metadata,
            ],
        ]);
    }

    public function update(Request $request, MembershipLevel $membershipLevel)
    {
        $validated = $request->validate([
            'name.en' => 'required|string|max:255',
            'name.zh-TW' => 'nullable|string|max:255',
            'name.zh-CN' => 'nullable|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('membership_levels')->ignore($membershipLevel->id)],
            'description.en' => 'nullable|string|max:500',
            'description.zh-TW' => 'nullable|string|max:500',
            'description.zh-CN' => 'nullable|string|max:500',
            'price' => 'required|integer|min:0',
            'points_cost' => 'nullable|integer|min:0',
            'duration_months' => 'nullable|integer|min:1',
            'stripe_product_id' => 'nullable|string|max:255',
            'stripe_price_id' => ['nullable', 'string', 'max:255', Rule::unique('membership_levels')->ignore($membershipLevel->id)],
            'benefits.en' => 'nullable|string',
            'benefits.zh-TW' => 'nullable|string',
            'benefits.zh-CN' => 'nullable|string',
            'max_users' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'sort_order' => 'required|integer|min:0',
            'metadata' => 'array',
        ]);

        $membershipLevel->update($validated);

        return redirect()
            ->route('admin.membership-levels.show', $membershipLevel)
            ->with('success', 'Membership level updated successfully.');
    }

    public function destroy(MembershipLevel $membershipLevel)
    {
        if ($membershipLevel->userMemberships()->exists()) {
            return redirect()
                ->route('admin.membership-levels.index')
                ->with('error', 'Cannot delete membership level with active subscriptions.');
        }

        $membershipLevel->delete();

        return redirect()
            ->route('admin.membership-levels.index')
            ->with('success', 'Membership level deleted successfully.');
    }

    public function users(MembershipLevel $membershipLevel)
    {
        $users = $membershipLevel->userMemberships()
            ->with(['user', 'membershipLevel'])
            ->latest()
            ->paginate(25)
            ->through(function ($membership) {
                return [
                    'id' => $membership->id,
                    'user' => [
                        'id' => $membership->user->id,
                        'name' => $membership->user->name,
                        'email' => $membership->user->email,
                        'created_at' => $membership->user->created_at,
                    ],
                    'status' => $membership->status,
                    'started_at' => $membership->started_at,
                    'expires_at' => $membership->expires_at,
                    'payment_method' => $membership->payment_method,
                    'auto_renew' => $membership->auto_renew,
                    'stripe_subscription_id' => $membership->stripe_subscription_id,
                    'subscription_metadata' => $membership->subscription_metadata,
                ];
            });

        return Inertia::render('Admin/MembershipLevels/Users', [
            'membershipLevel' => [
                'id' => $membershipLevel->id,
                'name' => $membershipLevel->name,
                'slug' => $membershipLevel->slug,
                'price_formatted' => '$' . number_format($membershipLevel->price / 100, 2),
            ],
            'users' => $users,
        ]);
    }

    private function getStats(): array
    {
        $totalUsers = UserMembership::count();
        $activeUsers = UserMembership::where('status', 'active')->count();
        $totalRevenue = MembershipLevel::join('user_memberships', 'membership_levels.id', '=', 'user_memberships.membership_level_id')
            ->where('user_memberships.status', 'active')
            ->sum('membership_levels.price');

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'total_revenue' => $totalRevenue,
            'total_revenue_formatted' => '$' . number_format($totalRevenue / 100, 2),
        ];
    }

    public function changeUserPlan(Request $request, User $user)
    {
        $request->validate([
            'membership_level_id' => 'required|exists:membership_levels,id',
        ]);

        $newLevel = MembershipLevel::findOrFail($request->membership_level_id);
        $action = new ChangeMembershipPlanAction();
        
        $result = $action->execute($user, $newLevel);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], 400);
    }

    public function bulkChangePlan(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'membership_level_id' => 'required|exists:membership_levels,id',
        ]);

        $newLevel = MembershipLevel::findOrFail($request->membership_level_id);
        $action = new ChangeMembershipPlanAction();
        
        $results = $action->changePlanForMultipleUsers($request->user_ids, $newLevel);

        return response()->json([
            'success' => $results['failed'] === 0,
            'message' => "Changed {$results['successful']} of {$results['total']} user plans successfully",
            'data' => $results,
        ]);
    }

    public function syncWithStripe(Request $request, MembershipLevel $membershipLevel = null)
    {
        $action = new SyncMembershipWithStripeAction();

        if ($membershipLevel) {
            $result = $action->execute($membershipLevel);
        } else {
            $result = $action->syncAllMembershipLevels();
        }

        if (isset($result['success']) ? $result['success'] : $result['failed'] === 0) {
            return back()->with('success', $result['message'] ?? 'Sync completed successfully');
        }

        return back()->with('error', $result['message'] ?? 'Sync failed');
    }
}