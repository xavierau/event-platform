<?php

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\VenueData;
use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Services\VenueService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Http\RedirectResponse;

class VenueController extends Controller
{
    public function __construct(protected VenueService $venueService)
    {
        // TODO: Add permissions middleware (e.g., $this->middleware('can:manage venues'));
    }

    public function index(Request $request): InertiaResponse
    {
        // TODO: Implement filtering/searching from request
        $venues = $this->venueService->getAllVenues([], ['country', 'state']);
        return Inertia::render('Admin/Venues/Index', [
            'pageTitle' => 'Venues',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Venues']
            ],
            'venues' => $venues,
            // Pass any filter values back to the view
        ]);
    }

    public function create(): InertiaResponse
    {
        // Pass necessary data for form selects, e.g., countries, states
        // For now, assume these will be fetched client-side or via dedicated endpoints if large
        return Inertia::render('Admin/Venues/Create', [
            'pageTitle' => 'Create New Venue',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Venues', 'href' => route('admin.venues.index')],
                ['text' => 'Create New Venue']
            ],
            // 'countries' => Country::all(), // Example
            // 'states' => State::all(), // Example
        ]);
    }

    public function store(VenueData $venueData): RedirectResponse
    {
        $this->venueService->createVenue($venueData);
        return redirect()->route('admin.venues.index')->with('success', 'Venue created successfully.');
    }

    public function show(Venue $venue): InertiaResponse
    {
        // Typically, show is for public view or a more detailed admin view if different from edit
        // For CRUD, edit is often sufficient. Can be implemented if a separate read-only view is needed.
        $venue->load('country', 'state', 'organizer');
        return Inertia::render('Admin/Venues/Show', [
            'venue' => $venue,
        ]);
    }

    public function edit(Venue $venue): InertiaResponse
    {
        $venue->load('country', 'state', 'organizer');
        // Pass necessary data for form selects

        // Explicitly convert model to array to ensure accessors and casts are applied
        $venueArray = $venue->toArray();

        return Inertia::render('Admin/Venues/Edit', [
            'pageTitle' => 'Edit Venue',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Venues', 'href' => route('admin.venues.index')],
                ['text' => 'Edit Venue']
            ],
            'venue' => VenueData::from($venueArray), // Pass the array to from()
            // 'countries' => Country::all(),
            // 'states' => State::all(),
        ]);
    }

    public function update(VenueData $venueData, Venue $venue): RedirectResponse
    {
        $this->venueService->updateVenue($venue->id, $venueData);
        return redirect()->route('admin.venues.index')->with('success', 'Venue updated successfully.');
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        $this->venueService->deleteVenue($venue);
        return redirect()->route('admin.venues.index')->with('success', 'Venue deleted successfully.');
    }
}
