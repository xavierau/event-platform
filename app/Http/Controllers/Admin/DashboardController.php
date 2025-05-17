<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request): InertiaResponse
    {
        return Inertia::render('Admin/Dashboard/Index', [
            'pageTitle' => 'Dashboard',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')], // Current page, but good for consistency
                ['text' => 'Dashboard']
            ]
        ]);
    }
}
