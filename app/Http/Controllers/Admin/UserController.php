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
    public function show(User $user)
    {
        //
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
