<?php

namespace Database\Factories;

use App\Enums\OrganizerRoleEnum;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganizerUser>
 */
class OrganizerUserFactory extends Factory
{
    protected $model = OrganizerUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roles = OrganizerRoleEnum::cases();
        $role = $this->faker->randomElement($roles);

        return [
            'organizer_id' => Organizer::factory(),
            'user_id' => User::factory(),
            'role_in_organizer' => $role->value,
            'permissions' => $this->getPermissionsForRole($role),
            'joined_at' => $this->faker->dateTimeBetween('-2 years', '-1 week'),
            'is_active' => $this->faker->boolean(95), // 95% active
            'invited_by' => null, // Will be set if created via invitation
            'invitation_accepted_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }

    /**
     * Get default permissions for a role.
     */
    private function getPermissionsForRole(OrganizerRoleEnum $role): ?array
    {
        return match ($role) {
            OrganizerRoleEnum::OWNER => [
                'manage_settings',
                'manage_users',
                'manage_events',
                'manage_venues',
                'view_analytics',
                'manage_billing',
            ],
            OrganizerRoleEnum::MANAGER => [
                'manage_users',
                'manage_events',
                'manage_venues',
                'view_analytics',
            ],
            OrganizerRoleEnum::STAFF => [
                'manage_events',
                'manage_venues',
            ],
            OrganizerRoleEnum::VIEWER => [
                'view_events',
                'view_venues',
            ],
        };
    }

    /**
     * Create an owner membership.
     */
    public function owner(): static
    {
        return $this->state(fn(array $attributes) => [
            'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
            'permissions' => $this->getPermissionsForRole(OrganizerRoleEnum::OWNER),
        ]);
    }

    /**
     * Create a manager membership.
     */
    public function manager(): static
    {
        return $this->state(fn(array $attributes) => [
            'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
            'permissions' => $this->getPermissionsForRole(OrganizerRoleEnum::MANAGER),
        ]);
    }

    /**
     * Create a staff membership.
     */
    public function staff(): static
    {
        return $this->state(fn(array $attributes) => [
            'role_in_organizer' => OrganizerRoleEnum::STAFF->value,
            'permissions' => $this->getPermissionsForRole(OrganizerRoleEnum::STAFF),
        ]);
    }

    /**
     * Create a viewer membership.
     */
    public function viewer(): static
    {
        return $this->state(fn(array $attributes) => [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => $this->getPermissionsForRole(OrganizerRoleEnum::VIEWER),
        ]);
    }

    /**
     * Create an inactive membership.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a pending invitation.
     */
    public function pendingInvitation(User $invitedBy): static
    {
        return $this->state(fn(array $attributes) => [
            'joined_at' => now(),
            'invited_by' => $invitedBy->id,
            'invitation_accepted_at' => null,
        ]);
    }

    /**
     * Create an accepted invitation.
     */
    public function acceptedInvitation(User $invitedBy): static
    {
        return $this->state(fn(array $attributes) => [
            'invited_by' => $invitedBy->id,
            'invitation_accepted_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Create for specific organizer and user.
     */
    public function forOrganizerAndUser(Organizer $organizer, User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create with custom permissions.
     */
    public function withPermissions(array $permissions): static
    {
        return $this->state(fn(array $attributes) => [
            'permissions' => $permissions,
        ]);
    }
}
