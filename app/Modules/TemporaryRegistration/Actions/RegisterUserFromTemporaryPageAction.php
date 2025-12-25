<?php

declare(strict_types=1);

namespace App\Modules\TemporaryRegistration\Actions;

use App\Enums\RoleNameEnum;
use App\Models\User;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\TemporaryRegistration\DataTransferObjects\TemporaryRegistrationData;
use App\Modules\TemporaryRegistration\Exceptions\RegistrationPageExpiredException;
use App\Modules\TemporaryRegistration\Exceptions\RegistrationPageFullException;
use App\Modules\TemporaryRegistration\Exceptions\RegistrationPageInactiveException;
use App\Modules\TemporaryRegistration\Models\TemporaryRegistrationPage;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUserFromTemporaryPageAction
{
    /**
     * Register a new user from a temporary registration page.
     *
     * @throws RegistrationPageInactiveException
     * @throws RegistrationPageExpiredException
     * @throws RegistrationPageFullException
     */
    public function execute(
        TemporaryRegistrationPage $page,
        TemporaryRegistrationData $data
    ): User {
        $this->validatePageAvailability($page);

        return DB::transaction(function () use ($page, $data): User {
            $user = $this->createUser($data);

            $this->assignUserRole($user);

            $this->createMembership($user, $page);

            $this->attachUserToPage($page, $user, $data);

            $page->incrementRegistrationCount();

            event(new Registered($user));

            return $user;
        });
    }

    /**
     * Validate that the registration page is available for registration.
     *
     * @throws RegistrationPageInactiveException
     * @throws RegistrationPageExpiredException
     * @throws RegistrationPageFullException
     */
    private function validatePageAvailability(TemporaryRegistrationPage $page): void
    {
        if (!$page->is_active) {
            throw new RegistrationPageInactiveException();
        }

        if ($page->isExpired()) {
            throw new RegistrationPageExpiredException();
        }

        if ($page->isFull()) {
            throw new RegistrationPageFullException();
        }
    }

    /**
     * Create a new user from the registration data.
     */
    private function createUser(TemporaryRegistrationData $data): User
    {
        return User::create([
            'name' => $data->name,
            'email' => $data->email,
            'mobile_number' => $data->mobile_number,
            'password' => Hash::make($data->password),
        ]);
    }

    /**
     * Assign the default user role to the newly created user.
     */
    private function assignUserRole(User $user): void
    {
        $user->assignRole(RoleNameEnum::USER->value);
    }

    /**
     * Create the membership for the user based on the registration page's membership level.
     * If the page has a custom duration_days set, use that instead of the membership level's default duration.
     */
    private function createMembership(User $user, TemporaryRegistrationPage $page): UserMembership
    {
        $membershipLevel = $page->membershipLevel;

        // Use custom duration_days if set, otherwise use membership level's default duration
        $expiresAt = $page->duration_days !== null
            ? now()->addDays($page->duration_days)
            : now()->addMonths($membershipLevel->duration_months);

        return UserMembership::create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'started_at' => now(),
            'expires_at' => $expiresAt,
            'status' => MembershipStatus::ACTIVE,
            'payment_method' => PaymentMethod::PROMOTIONAL,
            'auto_renew' => false,
            'subscription_metadata' => [
                'registration_page_id' => $page->id,
                'registration_page_title' => $page->getTranslation('title', 'en'),
                'granted_at' => now()->toIso8601String(),
                'custom_duration_days' => $page->duration_days,
            ],
        ]);
    }

    /**
     * Attach the user to the registration page's registered users.
     */
    private function attachUserToPage(
        TemporaryRegistrationPage $page,
        User $user,
        TemporaryRegistrationData $data
    ): void {
        $page->registeredUsers()->attach($user->id, [
            'ip_address' => $data->ip_address,
            'user_agent' => $data->user_agent,
        ]);
    }
}
