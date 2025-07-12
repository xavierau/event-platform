<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $users = User::with(['currentMembership.membershipPlan', 'organizers'])->paginate(10);

        $users->getCollection()->transform(function ($user) {
            $user->membership_level = $user->currentMembership->membershipPlan->name ?? 'N/A';
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
        $user->load(['currentMembership.membershipPlan', 'organizers']);

        $userData = $user->toArray();
        $userData['membership_level'] = $user->currentMembership->membershipPlan->name ?? 'N/A';
        $organizer = $user->organizers->first();
        $userData['organizer_info'] = $organizer ? $organizer->name . ' (' . ($organizer->pivot->role_in_organizer ?? 'N/A') . ')' : 'N/A';


        return Inertia::render('Admin/Users/Edit', [
            'user' => $userData,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'is_commenting_blocked' => 'required|boolean',
        ]);

        $user->update([
            'is_commenting_blocked' => $request->is_commenting_blocked,
        ]);

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
