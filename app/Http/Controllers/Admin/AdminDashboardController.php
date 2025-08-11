<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\RoleNameEnum;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $user = Auth::user();

        // Check authorization: only admins or users with organizer entity membership can access
        if (!$user->hasRole(RoleNameEnum::ADMIN) && !$user->hasOrganizerMembership()) {
            abort(403, 'You do not have permission to access the admin dashboard.');
        }

        // Placeholder for data fetching logic
        return Inertia::render('Admin/Dashboard/Index');
    }
}
