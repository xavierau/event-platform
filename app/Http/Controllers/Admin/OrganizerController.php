<?php

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\Organizer\OrganizerData;
use App\DataTransferObjects\MediaData;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Organizer;
use App\Models\State;
use App\Services\OrganizerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use App\DataTransferObjects\Organizer\InviteUserData;
use App\Actions\Organizer\InviteUserToOrganizerAction;

class OrganizerController extends Controller
{
    public function __construct(protected OrganizerService $organizerService)
    {
        $this->middleware('can:viewAny,App\Models\Organizer')->only(['index']);
        $this->middleware('can:view,organizer')->only(['show']);
        $this->middleware('can:create,App\Models\Organizer')->only(['create', 'store']);
        $this->middleware('can:update,organizer')->only(['edit', 'update']);
        $this->middleware('can:delete,organizer')->only(['destroy']);
        $this->middleware('can:update,organizer')->only('inviteUser');
    }

    public function index(Request $request): InertiaResponse
    {
        $organizers = $this->organizerService->getPaginatedOrganizers(
            $request->all(),
            ['country', 'state', 'users', 'media'],
            $request->get('per_page', 10)
        );

        return Inertia::render('Admin/Organizers/Index', [
            'pageTitle' => 'Organizers',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Organizers']
            ],
            'organizers' => $organizers,
            'filters' => $request->only(['search', 'is_active', 'sort', 'direction', 'per_page']),
        ]);
    }

    public function create(): InertiaResponse
    {
        // Get countries and states for form selects
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

        return Inertia::render('Admin/Organizers/Create', [
            'pageTitle' => 'Create New Organizer',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Organizers', 'href' => route('admin.organizers.index')],
                ['text' => 'Create New Organizer']
            ],
            'countries' => $countries,
            'states' => $states,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            // Add created_by to request data
            $requestData = $request->all();
            $requestData['created_by'] = Auth::id();

            // Create DTO from request data
            $organizerData = OrganizerData::from($requestData);

            // Create organizer
            $this->organizerService->createOrganizer($organizerData);

            return redirect()->route('admin.organizers.index')
                ->with('success', 'Organizer created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'An unexpected error occurred while creating the organizer. Please try again.');
        }
    }

    public function show(Organizer $organizer): InertiaResponse
    {
        // Load relationships
        $organizer->load(['country', 'state', 'users', 'media', 'events']);

        $organizerArray = $organizer->toArray();

        // Add logo media
        $logoMedia = $organizer->getFirstMedia('logo');
        $organizerArray['logo'] = $logoMedia ? MediaData::fromModel($logoMedia) : null;
        $organizerArray['logo_url'] = $logoMedia ? $logoMedia->getFullUrl() : null;

        // Add team members with their roles
        $organizerArray['team_members'] = $organizer->users()->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_in_organizer' => $user->pivot->role_in_organizer,
                'joined_at' => $user->pivot->joined_at,
                'is_active' => $user->pivot->is_active,
            ];
        });

        return Inertia::render('Admin/Organizers/Show', [
            'pageTitle' => 'Organizer Details',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Organizers', 'href' => route('admin.organizers.index')],
                ['text' => $organizer->name]
            ],
            'organizer' => $organizerArray,
        ]);
    }

    public function edit(Organizer $organizer): InertiaResponse
    {
        // Load relationships
        $organizer->load(['country', 'state', 'media']);

        // Prepare data for DTO - ensure proper types
        $organizerData = [
            'id' => $organizer->id,
            'name' => $organizer->getTranslations('name'), // Get translations as array
            'slug' => $organizer->slug,
            'description' => $organizer->getTranslations('description'), // Get translations as array
            'contact_email' => $organizer->contact_email,
            'contact_phone' => $organizer->contact_phone,
            'website_url' => $organizer->website_url,
            'social_media_links' => $organizer->social_media_links,
            'address_line_1' => $organizer->address_line_1,
            'address_line_2' => $organizer->address_line_2,
            'city' => $organizer->city,
            'state' => $organizer->state_id, // Use ID, not the relationship object
            'postal_code' => $organizer->postal_code,
            'country_id' => $organizer->country_id, // Use ID, not the relationship object
            'state_id' => $organizer->state_id, // Use ID, not the relationship object
            'is_active' => $organizer->is_active,
            'contract_details' => $organizer->contract_details,
            'created_by' => $organizer->created_by,
            'logo_upload' => null, // This will be handled separately
        ];

        // Add existing logo
        $logoMedia = $organizer->getFirstMedia('logo');
        $organizerData['existing_logo'] = $logoMedia ? MediaData::fromModel($logoMedia) : null;

        // Get countries and states for form selects
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

        return Inertia::render('Admin/Organizers/Edit', [
            'pageTitle' => 'Edit Organizer',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Organizers', 'href' => route('admin.organizers.index')],
                ['text' => 'Edit Organizer']
            ],
            'organizer' => OrganizerData::from($organizerData),
            'countries' => $countries,
            'states' => $states,
        ]);
    }

    public function update(Request $request, Organizer $organizer): RedirectResponse
    {
        try {
            // Create DTO from request data
            $organizerData = OrganizerData::from($request->all());

            // Update organizer
            $this->organizerService->updateOrganizer($organizer->id, $organizerData);

            return redirect()->route('admin.organizers.index')
                ->with('success', 'Organizer updated successfully.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('OrganizerController@update: General Exception.', [
                'message' => $e->getMessage(),
                'trace_snippet' => mb_substr($e->getTraceAsString(), 0, 500),
                'request_data_snippet' => mb_substr(json_encode($request->all()), 0, 1000),
            ]);
            return back()->withInput()->withErrors(['error' => 'An unexpected error occurred while updating the organizer. Please try again.']);
        }
    }

    public function destroy(Organizer $organizer): RedirectResponse
    {
        try {
            $organizerName = $organizer->getTranslation('name', 'en');
            // Delete organizer (soft delete)
            $this->organizerService->deleteOrganizer($organizer);

            return redirect()->route('admin.organizers.index')
                ->with('success', 'Organizer deleted successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'An unexpected error occurred while deleting the organizer.');
        }
    }

    public function inviteUser(Request $request, Organizer $organizer, InviteUserToOrganizerAction $inviteUserAction): RedirectResponse
    {
        // Use a form request or manual validation
        $validatedData = $request->validate([
            'email' => 'required|email',
            'role_in_organizer' => 'required|string',
            'existing_user_id' => 'nullable|exists:users,id',
            'invitation_message' => 'nullable|string|max:1000',
            'custom_permissions' => 'nullable|array'
        ]);

        try {
            $validatedData['organizer_id'] = $organizer->id;
            $validatedData['invited_by'] = Auth::id();

            $inviteUserData = InviteUserData::from($validatedData);

            $inviteUserAction->execute($inviteUserData);

            return back()->with('success', 'Invitation sent successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send invitation. ' . $e->getMessage());
        }
    }
}
