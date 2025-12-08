<?php

declare(strict_types=1);

namespace App\Modules\TemporaryRegistration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\TemporaryRegistration\DataTransferObjects\TemporaryRegistrationPageData;
use App\Modules\TemporaryRegistration\Models\TemporaryRegistrationPage;
use App\Modules\TemporaryRegistration\Requests\StoreTemporaryRegistrationPageRequest;
use App\Modules\TemporaryRegistration\Requests\UpdateTemporaryRegistrationPageRequest;
use App\Modules\TemporaryRegistration\Services\TemporaryRegistrationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminTemporaryRegistrationController extends Controller
{
    public function __construct(
        private readonly TemporaryRegistrationService $service
    ) {}

    public function index(): Response
    {
        $pages = $this->service->getPaginated();

        return Inertia::render('Admin/TemporaryRegistration/Index', [
            'pages' => $pages,
            'stats' => [
                'total_pages' => TemporaryRegistrationPage::count(),
                'active_pages' => TemporaryRegistrationPage::active()->count(),
                'total_registrations' => (int) TemporaryRegistrationPage::sum('registrations_count'),
            ],
        ]);
    }

    public function create(): Response
    {
        $membershipLevels = MembershipLevel::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MembershipLevel $level): array => [
                'id' => $level->id,
                'name' => $level->name,
                'price_formatted' => '$' . number_format($level->price / 100, 2),
                'duration_months' => $level->duration_months,
            ]);

        return Inertia::render('Admin/TemporaryRegistration/Create', [
            'membershipLevels' => $membershipLevels,
        ]);
    }

    public function store(StoreTemporaryRegistrationPageRequest $request): RedirectResponse
    {
        $data = TemporaryRegistrationPageData::from($request->validated());
        $page = $this->service->create($data);

        return redirect()
            ->route('admin.temporary-registration.show', $page)
            ->with('success', 'Temporary registration page created successfully.');
    }

    public function show(TemporaryRegistrationPage $temporaryRegistration): Response
    {
        $temporaryRegistration->load(['membershipLevel', 'creator', 'registeredUsers']);

        return Inertia::render('Admin/TemporaryRegistration/Show', [
            'page' => [
                'id' => $temporaryRegistration->id,
                'title' => $temporaryRegistration->getTranslations('title'),
                'description' => $temporaryRegistration->getTranslations('description'),
                'slug' => $temporaryRegistration->slug,
                'token' => $temporaryRegistration->token,
                'public_url' => $temporaryRegistration->getPublicUrl(),
                'membership_level' => [
                    'id' => $temporaryRegistration->membershipLevel->id,
                    'name' => $temporaryRegistration->membershipLevel->name,
                    'duration_months' => $temporaryRegistration->membershipLevel->duration_months,
                ],
                'expires_at' => $temporaryRegistration->expires_at?->toIso8601String(),
                'expires_at_formatted' => $temporaryRegistration->expires_at?->format('M d, Y H:i'),
                'duration_days' => $temporaryRegistration->duration_days,
                'max_registrations' => $temporaryRegistration->max_registrations,
                'registrations_count' => $temporaryRegistration->registrations_count,
                'remaining_slots' => $temporaryRegistration->getRemainingSlots(),
                'is_active' => $temporaryRegistration->is_active,
                'is_available' => $temporaryRegistration->isAvailable(),
                'is_expired' => $temporaryRegistration->isExpired(),
                'is_full' => $temporaryRegistration->isFull(),
                'use_slug' => $temporaryRegistration->use_slug,
                'banner_url' => $temporaryRegistration->getBannerUrl(),
                'created_by' => [
                    'id' => $temporaryRegistration->creator->id,
                    'name' => $temporaryRegistration->creator->name,
                ],
                'registered_users' => $temporaryRegistration->registeredUsers->map(fn ($user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'registered_at' => $user->pivot->created_at?->format('M d, Y H:i'),
                ]),
                'created_at' => $temporaryRegistration->created_at->format('M d, Y H:i'),
            ],
        ]);
    }

    public function edit(TemporaryRegistrationPage $temporaryRegistration): Response
    {
        $membershipLevels = MembershipLevel::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MembershipLevel $level): array => [
                'id' => $level->id,
                'name' => $level->name,
                'price_formatted' => '$' . number_format($level->price / 100, 2),
                'duration_months' => $level->duration_months,
            ]);

        return Inertia::render('Admin/TemporaryRegistration/Edit', [
            'page' => [
                'id' => $temporaryRegistration->id,
                'title' => $temporaryRegistration->getTranslations('title'),
                'description' => $temporaryRegistration->getTranslations('description'),
                'slug' => $temporaryRegistration->slug,
                'token' => $temporaryRegistration->token,
                'membership_level_id' => $temporaryRegistration->membership_level_id,
                'expires_at' => $temporaryRegistration->expires_at?->format('Y-m-d\TH:i'),
                'duration_days' => $temporaryRegistration->duration_days,
                'max_registrations' => $temporaryRegistration->max_registrations,
                'is_active' => $temporaryRegistration->is_active,
                'use_slug' => $temporaryRegistration->use_slug,
                'banner_url' => $temporaryRegistration->getBannerUrl(),
            ],
            'membershipLevels' => $membershipLevels,
        ]);
    }

    public function update(
        UpdateTemporaryRegistrationPageRequest $request,
        TemporaryRegistrationPage $temporaryRegistration
    ): RedirectResponse {
        $data = TemporaryRegistrationPageData::from($request->validated());
        $this->service->update($temporaryRegistration, $data);

        return redirect()
            ->route('admin.temporary-registration.show', $temporaryRegistration)
            ->with('success', 'Temporary registration page updated successfully.');
    }

    public function destroy(TemporaryRegistrationPage $temporaryRegistration): RedirectResponse
    {
        $this->service->delete($temporaryRegistration);

        return redirect()
            ->route('admin.temporary-registration.index')
            ->with('success', 'Temporary registration page deleted successfully.');
    }

    public function toggleActive(TemporaryRegistrationPage $temporaryRegistration): RedirectResponse
    {
        $this->service->toggleActive($temporaryRegistration);
        $status = $temporaryRegistration->fresh()->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Registration page {$status} successfully.");
    }

    public function regenerateToken(TemporaryRegistrationPage $temporaryRegistration): RedirectResponse
    {
        $this->service->regenerateToken($temporaryRegistration);

        return back()->with('success', 'Token regenerated successfully.');
    }
}
