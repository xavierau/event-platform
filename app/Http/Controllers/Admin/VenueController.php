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
use App\DataTransferObjects\MediaData;
use Illuminate\Support\Facades\Log;
use App\Models\Country;
use App\Models\State;
use Illuminate\Validation\ValidationException;

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
        // Pass necessary data for form selects
        $countries = Country::where('is_active', true)->get()->map(function ($country) {
            return [
                'id' => $country->id,
                'name' => $country->getTranslations('name'),
            ];
        });

        $states = State::where('is_active', true)->get()->map(function ($state) {
            return [
                'id' => $state->id,
                'country_id' => $state->country_id,
                'name' => $state->getTranslations('name'),
            ];
        });

        return Inertia::render('Admin/Venues/Create', [
            'pageTitle' => 'Create New Venue',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Venues', 'href' => route('admin.venues.index')],
                ['text' => 'Create New Venue']
            ],
            'countries' => $countries,
            'states' => $states,
        ]);
    }

    public function store(VenueData $venueData): RedirectResponse
    {
        try {
            // $venueData is already validated and created by Laravel if typehinted
            $this->venueService->createVenue($venueData);
            return redirect()->route('admin.venues.index')->with('success', 'Venue created successfully.');
        } catch (\Exception $e) { // Catch general exceptions during service call or redirect
            Log::error('VenueController@store: CAUGHT EXCEPTION IN SUCCESS PATH.', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'trace_snippet' => mb_substr($e->getTraceAsString(), 0, 1500), // Increased trace snippet
            ]);
            return back()->withInput()->withErrors(['error' => 'An unexpected error occurred while creating the venue. Please try again. Details: ' . $e->getMessage()]);
        }
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
        $venue->load('country', 'state', 'organizer', 'media');

        $venueArray = $venue->toArray();

        $mainImageMedia = $venue->getFirstMedia('featured_image');
        $venueArray['existing_main_image'] = $mainImageMedia ? MediaData::fromModel($mainImageMedia) : null;

        $galleryMediaItems = $venue->getMedia('gallery');
        $venueArray['existing_gallery_images'] = $galleryMediaItems->isNotEmpty()
            ? $galleryMediaItems->map(fn($media) => MediaData::fromModel($media))->all()
            : [];

        return Inertia::render('Admin/Venues/Edit', [
            'pageTitle' => 'Edit Venue',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Venues', 'href' => route('admin.venues.index')],
                ['text' => 'Edit Venue']
            ],
            'venue' => VenueData::from($venueArray),
            'countries' => Country::all(),
            'states' => State::all(),
        ]);
    }

    public function update(Request $request, VenueData $venueData): RedirectResponse
    {
        try {
            // The DTO will handle validation based on its rules.
            // Data is sourced from $request->all(), which merges query, post, and file data.
            // For multipart/form-data, Laravel handles parsing of fields like name[en] into nested arrays if accessed directly via $request->input('name').
            // Spatie/laravel-data should correctly map these if the DTO expects an array for 'name'.

            $venue = $this->venueService->updateVenue($venueData->id, $venueData);

            // Handle media uploads if present
            if ($request->hasFile('new_featured_image')) {
                $venue->addMediaFromRequest('new_featured_image')->toMediaCollection('featured_image');
            }

            if ($request->hasFile('new_gallery_images')) {
                foreach ($request->file('new_gallery_images') as $file) {
                    $venue->addMedia($file)->toMediaCollection('gallery');
                }
            }
            if ($request->hasFile('new_floor_plan_image')) {
                $venue->addMediaFromRequest('new_floor_plan_image')->toMediaCollection('floor_plan');
            }
            if ($request->hasFile('new_menu_pdf')) {
                $venue->addMediaFromRequest('new_menu_pdf')->toMediaCollection('menu_pdf');
            }

            Log::info('Venue updated successfully', ['venue_id' => $venue->id]); // Standard operational log

            // Redirect to the venue index page upon successful update.
            return redirect()->route('admin.venues.index')->with('success', 'Venue updated successfully.');
        } catch (ValidationException $e) {
            Log::error('VenueController@update: DTO Creation/Validation Failed.', [
                'message' => $e->getMessage(),
                // 'errors' => property_exists($e, 'errors') ? $e->errors() : [], // If you need specific DTO validation errors
                'trace_snippet' => mb_substr($e->getTraceAsString(), 0, 500),
                'request_data_snippet' => mb_substr(json_encode($request->all()), 0, 1000),
            ]);
            return back()->withInput()->withErrors(['dto_error' => 'Error processing venue data: ' . $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('VenueController@update: General Exception.', [
                'message' => $e->getMessage(),
                'trace_snippet' => mb_substr($e->getTraceAsString(), 0, 500),
                'request_data_snippet' => mb_substr(json_encode($request->all()), 0, 1000),
            ]);
            return back()->withInput()->withErrors(['error' => 'An unexpected error occurred. Please try again.']);
        }
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        $this->venueService->deleteVenue($venue);
        return redirect()->route('admin.venues.index')->with('success', 'Venue deleted successfully.');
    }
}
