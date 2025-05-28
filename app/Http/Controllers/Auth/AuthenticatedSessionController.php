<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route as RouteFacade;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use App\Enums\RoleNameEnum;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): InertiaResponse
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => RouteFacade::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Display the admin login view.
     */
    public function createAdminLogin(Request $request): InertiaResponse
    {
        return Inertia::render('auth/AdminLogin', [
            'canResetPassword' => RouteFacade::has('password.request'),
            'status' => $request->session()->get('status'),
            'loginRoute' => route('admin.login'),
        ]);
    }

    /**
     * Display the organizer login view.
     */
    public function createOrganizerLogin(Request $request): InertiaResponse
    {
        return Inertia::render('Auth/OrganizerLogin', [
            'canResetPassword' => RouteFacade::has('password.request'),
            'status' => $request->session()->get('status'),
            'loginRoute' => route('organizer.login'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Handle an incoming admin authentication request.
     */
    public function storeAdminLogin(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $user = Auth::user();

        if (!$user || !$user->hasRole(RoleNameEnum::ADMIN->value)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('admin.login')->withErrors(['email' => 'These credentials do not match an administrator account.']);
        }

        $request->session()->regenerate();
        return redirect()->intended(route('admin.dashboard', absolute: false));
    }

    /**
     * Handle an incoming organizer authentication request.
     */
    public function storeOrganizerLogin(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $user = Auth::user();

        if (!$user || !$user->hasRole(RoleNameEnum::ORGANIZER->value)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('organizer.login')->withErrors(['email' => 'These credentials do not match an organizer account.']);
        }

        $request->session()->regenerate();
        return redirect()->intended(route('organizer.dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
