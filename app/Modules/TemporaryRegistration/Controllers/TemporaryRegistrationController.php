<?php

declare(strict_types=1);

namespace App\Modules\TemporaryRegistration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TemporaryRegistration\Actions\RegisterUserFromTemporaryPageAction;
use App\Modules\TemporaryRegistration\DataTransferObjects\TemporaryRegistrationData;
use App\Modules\TemporaryRegistration\Exceptions\RegistrationPageExpiredException;
use App\Modules\TemporaryRegistration\Exceptions\RegistrationPageFullException;
use App\Modules\TemporaryRegistration\Exceptions\RegistrationPageInactiveException;
use App\Modules\TemporaryRegistration\Requests\TemporaryUserRegistrationRequest;
use App\Modules\TemporaryRegistration\Services\TemporaryRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TemporaryRegistrationController extends Controller
{
    public function __construct(
        private readonly TemporaryRegistrationService $service,
        private readonly RegisterUserFromTemporaryPageAction $registerAction
    ) {}

    public function show(string $identifier): Response|RedirectResponse
    {
        $page = $this->service->findByIdentifier($identifier);

        if (!$page) {
            abort(404);
        }

        if (!$page->is_active) {
            return $this->renderUnavailable('inactive', 'This registration page is no longer available.');
        }

        if ($page->isExpired()) {
            return $this->renderUnavailable('expired', 'This registration page has expired.');
        }

        if ($page->isFull()) {
            return $this->renderUnavailable('full', 'This registration page has reached its maximum capacity.');
        }

        return Inertia::render('auth/TemporaryRegistration/Register', [
            'page' => $this->transformPageForDisplay($page),
            'identifier' => $identifier,
        ]);
    }

    public function store(
        TemporaryUserRegistrationRequest $request,
        string $identifier
    ): RedirectResponse {
        $page = $this->service->findByIdentifier($identifier);

        if (!$page) {
            abort(404);
        }

        try {
            $data = new TemporaryRegistrationData(
                name: $request->validated('name'),
                email: $request->validated('email'),
                mobile_number: $request->validated('mobile_number'),
                password: $request->validated('password'),
                ip_address: $request->ip(),
                user_agent: $request->userAgent()
            );

            $user = $this->registerAction->execute($page, $data);

            Auth::login($user);

            return redirect()->route('home')
                ->with('success', 'Registration successful! Welcome to our platform.');

        } catch (RegistrationPageInactiveException) {
            return back()->withErrors(['page' => 'This registration page is no longer active.']);
        } catch (RegistrationPageExpiredException) {
            return back()->withErrors(['page' => 'This registration page has expired.']);
        } catch (RegistrationPageFullException) {
            return back()->withErrors(['page' => 'This registration page has reached its maximum capacity.']);
        }
    }

    private function renderUnavailable(string $reason, string $message): Response
    {
        return Inertia::render('auth/TemporaryRegistration/Unavailable', [
            'reason' => $reason,
            'message' => $message,
        ]);
    }

    private function transformPageForDisplay(mixed $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->getTranslations('title'),
            'description' => $page->getTranslations('description'),
            'banner_url' => $page->getBannerUrl(),
            'membership_level' => [
                'name' => $page->membershipLevel->getTranslations('name'),
                'description' => $page->membershipLevel->getTranslations('description'),
                'benefits' => $page->membershipLevel->getTranslations('benefits'),
                'duration_months' => $page->membershipLevel->duration_months,
            ],
            'remaining_slots' => $page->getRemainingSlots(),
        ];
    }
}
