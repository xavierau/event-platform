<?php

namespace App\Http\Controllers\Settings;

use App\Enums\RoleNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Modules\Membership\Services\MembershipService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's membership page.
     */
    public function myMembership(Request $request, MembershipService $membershipService): Response
    {
        $membership = $membershipService->checkMembershipStatus($request->user());

        return Inertia::render('Profile/MyMembership', [
            'membership' => $membership,
        ]);
    }

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request, MembershipService $membershipService): Response
    {
        $user = $request->user();

        // Check if user is platform admin
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return Inertia::render('settings/Profile', [
                'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
                'status' => $request->session()->get('status'),
            ]);
        }

        // Get user's membership information
        $membership = $membershipService->checkMembershipStatus($user);

        // For non-admin users, show the simplified profile page
        return Inertia::render('Profile/MyProfile', [
            'user' => $user,
            'membership' => $membership,
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
