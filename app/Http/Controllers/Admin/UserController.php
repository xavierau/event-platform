<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
    public function index(): Response
    {
        $users = User::with(['currentMembership.level', 'organizers'])->paginate(10);

        $users->getCollection()->transform(function ($user) {
            $user->membership_level = $user->currentMembership?->level?->name ?? 'N/A';
            $organizer = $user->organizers->first();
            $user->organizer_info = $organizer ? $organizer->name . ' (' . ($organizer->pivot->role_in_organizer ?? 'N/A') . ')' : 'N/A';
            return $user;
        });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
                'amount' => $transaction->amount,
                'type' => $transaction->type,
                'description' => $transaction->description,
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
        $organizer = $user->organizers->first();
        $userData['organizer_info'] = $organizer ? $organizer->name . ' (' . ($organizer->pivot->role_in_organizer ?? 'N/A') . ')' : 'N/A';

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
            'membership_level_id' => 'nullable|exists:membership_levels,id',
            'membership_duration_months' => 'nullable|integer|min:1|max:120',
        ]);

        $user->update([
            'is_commenting_blocked' => $request->is_commenting_blocked,
        ]);

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
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }
}
