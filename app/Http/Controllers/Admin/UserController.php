<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateUserByAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeMembershipRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Modules\Membership\Actions\AssignMembershipLevelAction;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = User::with(['currentMembership.level', 'organizers']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('membership_level_id')) {
            $query->whereHas('currentMembership', function ($q) use ($request) {
                $q->where('membership_level_id', $request->membership_level_id)
                  ->where('status', 'active')
                  ->where(function ($subQ) {
                      $subQ->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                  });
            });
        }

        if ($request->filled('has_membership')) {
            if ($request->has_membership === 'yes') {
                $query->whereHas('currentMembership', function ($q) {
                    $q->where('status', 'active')
                      ->where(function ($subQ) {
                          $subQ->whereNull('expires_at')
                               ->orWhere('expires_at', '>', now());
                      });
                });
            } elseif ($request->has_membership === 'no') {
                $query->whereDoesntHave('currentMembership', function ($q) {
                    $q->where('status', 'active')
                      ->where(function ($subQ) {
                          $subQ->whereNull('expires_at')
                               ->orWhere('expires_at', '>', now());
                      });
                });
            }
        }

        if ($request->filled('registered_from')) {
            $query->where('created_at', '>=', $request->registered_from);
        }

        if ($request->filled('registered_to')) {
            $query->where('created_at', '<=', $request->registered_to . ' 23:59:59');
        }

        $users = $query->paginate(10)->withQueryString();

        $users->getCollection()->transform(function ($user) {
            $user->membership_level = $user->currentMembership?->level?->name ?? 'N/A';
            $organizer = $user->organizers->first();
            $user->organizer_info = $organizer ? $organizer->name . ' (' . ($organizer->pivot->role_in_organizer ?? 'N/A') . ')' : 'N/A';
            return $user;
        });

        // Get membership levels for filter dropdown
        $membershipLevels = MembershipLevel::active()
            ->ordered()
            ->get()
            ->map(function ($level) {
                return [
                    'id' => $level->id,
                    'name' => $level->name,
                ];
            });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'membershipLevels' => $membershipLevels,
            'filters' => $request->only(['search', 'membership_level_id', 'has_membership', 'registered_from', 'registered_to']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        // Get all active membership levels
        $membershipLevels = MembershipLevel::active()
            ->ordered()
            ->get()
            ->map(function ($level) {
                return [
                    'id' => $level->id,
                    'name' => $level->name,
                    'description' => $level->description,
                    'duration_months' => $level->duration_months,
                    'price' => $level->price,
                ];
            });

        return Inertia::render('Admin/Users/Create', [
            'membershipLevels' => $membershipLevels,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        try {
            $createUserAction = app(CreateUserByAdminAction::class);
            
            $user = $createUserAction->execute(
                $request->validated(),
                $request->membership_level_id,
                $request->membership_duration_months,
                $request->reason
            );

            return redirect()
                ->route('admin.users.show', $user)
                ->with('success', 'User created successfully.');

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): Response
    {
        $user->load([
            'currentMembership.level',
            'memberships.level',
            'organizers',
            'transactions' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            },
            'wallet'
        ]);

        // Get membership history
        $membershipHistory = $user->memberships()
            ->with('level')
            ->orderBy('started_at', 'desc')
            ->get()
            ->map(function ($membership) {
                return [
                    'id' => $membership->id,
                    'level_name' => $membership->level->name,
                    'status' => $membership->status,
                    'started_at' => $membership->started_at,
                    'expires_at' => $membership->expires_at,
                    'payment_method' => $membership->payment_method,
                    'auto_renew' => $membership->auto_renew,
                    'stripe_subscription_id' => $membership->stripe_subscription_id,
                ];
            });

        // Get current membership details
        $currentMembership = null;
        if ($user->currentMembership) {
            $currentMembership = [
                'id' => $user->currentMembership->id,
                'level_name' => $user->currentMembership->level->name,
                'level_id' => $user->currentMembership->level->id,
                'status' => $user->currentMembership->status,
                'started_at' => $user->currentMembership->started_at,
                'expires_at' => $user->currentMembership->expires_at,
                'payment_method' => $user->currentMembership->payment_method,
                'auto_renew' => $user->currentMembership->auto_renew,
                'stripe_subscription_id' => $user->currentMembership->stripe_subscription_id,
                'subscription_metadata' => $user->currentMembership->subscription_metadata,
            ];
        }

        // Get organizer information
        $organizerInfo = $user->organizers->map(function ($organizer) {
            return [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'role' => $organizer->pivot->role_in_organizer,
                'is_active' => $organizer->pivot->is_active,
                'joined_at' => $organizer->pivot->joined_at,
            ];
        });

        // Get wallet information
        $walletInfo = null;
        if ($user->wallet) {
            $walletInfo = [
                'points_balance' => $user->wallet->points_balance,
                'kill_points_balance' => $user->wallet->kill_points_balance,
            ];
        }

        // Get recent transactions
        $recentTransactions = $user->transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'amount' => $transaction->total_amount,
                'type' => $transaction->status->value ?? $transaction->status,
                'description' => $transaction->notes,
                'created_at' => $transaction->created_at,
            ];
        });

        // Get Stripe information
        $stripeInfo = [
            'customer_id' => $user->stripe_id,
            'all_customer_ids' => $user->getAllStripeCustomerIds(),
            'has_payment_method' => $user->hasDefaultPaymentMethod(),
        ];

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'is_commenting_blocked' => $user->is_commenting_blocked,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'currentMembership' => $currentMembership,
            'membershipHistory' => $membershipHistory,
            'organizerInfo' => $organizerInfo,
            'walletInfo' => $walletInfo,
            'recentTransactions' => $recentTransactions,
            'stripeInfo' => $stripeInfo,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): Response
    {
        $user->load(['currentMembership.level', 'organizers']);

        $userData = $user->toArray();
        $userData['membership_level'] = $user->currentMembership?->level?->name ?? 'N/A';
        $userData['current_membership_level_id'] = $user->currentMembership?->membership_level_id;
        $userData['is_email_verified'] = $user->hasVerifiedEmail();
        $organizer = $user->organizers->first();
        $userData['organizer_info'] = $organizer ? $organizer->name . ' (' . ($organizer->pivot->role_in_organizer ?? 'N/A') . ')' : 'N/A';

        // Get all active membership levels
        $membershipLevels = MembershipLevel::active()
            ->ordered()
            ->get()
            ->map(function ($level) {
                return [
                    'id' => $level->id,
                    'name' => $level->getTranslations('name'),
                    'description' => $level->getTranslations('description'),
                    'duration_months' => $level->duration_months,
                ];
            });

        return Inertia::render('Admin/Users/Edit', [
            'user' => $userData,
            'membershipLevels' => $membershipLevels,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user, AssignMembershipLevelAction $assignMembershipLevelAction)
    {
        $request->validate([
            'is_commenting_blocked' => 'required|boolean',
            'email_verified' => 'nullable|boolean',
            'membership_level_id' => 'nullable|exists:membership_levels,id',
            'membership_duration_months' => 'nullable|integer|min:1|max:120',
        ]);

        $user->update([
            'is_commenting_blocked' => $request->is_commenting_blocked,
        ]);

        // Handle email verification toggle
        if ($request->has('email_verified')) {
            $oldVerificationStatus = $user->hasVerifiedEmail() ? 'verified' : 'unverified';

            if ($request->email_verified && !$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
                $newVerificationStatus = 'verified';
            } elseif (!$request->email_verified && $user->hasVerifiedEmail()) {
                $user->email_verified_at = null;
                $user->save();
                $newVerificationStatus = 'unverified';
            } else {
                $newVerificationStatus = $oldVerificationStatus;
            }

            // Create audit log if status changed
            if ($oldVerificationStatus !== $newVerificationStatus) {
                AdminAuditLog::create([
                    'admin_user_id' => auth()->id(),
                    'target_user_id' => $user->id,
                    'action_type' => 'email_verification_change',
                    'action_details' => [
                        'old_status' => $oldVerificationStatus,
                        'new_status' => $newVerificationStatus,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        }

        // Handle membership level assignment
        if ($request->filled('membership_level_id')) {
            $membershipLevel = MembershipLevel::find($request->membership_level_id);
            if ($membershipLevel) {
                $assignMembershipLevelAction->execute(
                    $user,
                    $membershipLevel,
                    $request->membership_duration_months
                );
            }
        }

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Change user's membership level.
     */
    public function changeMembership(ChangeMembershipRequest $request, User $user, AssignMembershipLevelAction $assignMembershipLevelAction)
    {
        try {
            $membershipLevel = MembershipLevel::findOrFail($request->membership_level_id);
            
            // Get current membership for audit purposes
            $oldMembership = $user->currentMembership;
            
            // Assign new membership
            $newMembership = $assignMembershipLevelAction->execute(
                $user,
                $membershipLevel,
                $request->membership_duration_months
            );

            // Create audit log
            $actionDetails = [
                'old_membership' => $oldMembership ? [
                    'id' => $oldMembership->id,
                    'level_name' => $oldMembership->level?->name,
                    'expires_at' => $oldMembership->expires_at,
                ] : null,
                'new_membership' => [
                    'id' => $newMembership->id,
                    'level_name' => $membershipLevel->name,
                    'expires_at' => $newMembership->expires_at,
                    'custom_duration_months' => $request->membership_duration_months,
                ],
            ];

            AdminAuditLog::create([
                'admin_user_id' => auth()->id(),
                'target_user_id' => $user->id,
                'action_type' => 'change_membership',
                'action_details' => $actionDetails,
                'reason' => $request->reason,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Membership changed successfully.',
                'user' => $user->fresh(['currentMembership.level']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change membership: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user metrics for dashboard.
     */
    public function metrics()
    {
        // Total users count
        $totalUsers = User::whereNull('deleted_at')->count();

        // Membership level distribution
        $membershipDistribution = MembershipLevel::with(['userMemberships' => function ($query) {
            $query->where('status', 'active')
                  ->where(function ($q) {
                      $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                  });
        }])
        ->get()
        ->map(function ($level) {
            return [
                'id' => $level->id,
                'name' => $level->name,
                'count' => $level->userMemberships->count(),
            ];
        });

        // Member growth trend (last 12 months)
        $memberGrowth = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = User::whereNull('deleted_at')
                        ->whereYear('created_at', $date->year)
                        ->whereMonth('created_at', $date->month)
                        ->count();

            $memberGrowth->push([
                'month' => $date->format('Y-m'),
                'month_name' => $date->format('M Y'),
                'count' => $count,
            ]);
        }

        // Users with active memberships vs without
        $usersWithMembership = User::whereHas('currentMembership', function ($q) {
            $q->where('status', 'active')
              ->where(function ($subQ) {
                  $subQ->whereNull('expires_at')
                       ->orWhere('expires_at', '>', now());
              });
        })->count();

        $usersWithoutMembership = $totalUsers - $usersWithMembership;

        return response()->json([
            'total_users' => $totalUsers,
            'membership_distribution' => $membershipDistribution,
            'member_growth' => $memberGrowth,
            'membership_status' => [
                'with_membership' => $usersWithMembership,
                'without_membership' => $usersWithoutMembership,
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }
}
