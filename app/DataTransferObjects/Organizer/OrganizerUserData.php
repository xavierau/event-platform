<?php

namespace App\DataTransferObjects\Organizer;

use App\Enums\OrganizerRoleEnum;
use App\Rules\ValidOrganizerPermissions;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class OrganizerUserData extends Data
{
    public function __construct(
        public readonly int $organizer_id,
        public readonly int $user_id,
        public readonly string $role_in_organizer,
        public readonly ?array $permissions,
        public readonly ?Carbon $joined_at,
        public readonly bool $is_active = true,
        public readonly int $invited_by,
        public readonly ?Carbon $invitation_accepted_at = null,
    ) {}

    /**
     * Get the role as an enum.
     */
    public function getRoleEnum(): ?OrganizerRoleEnum
    {
        return OrganizerRoleEnum::tryFrom($this->role_in_organizer);
    }

    /**
     * Check if the user can manage other users based on their role.
     */
    public function canManageUsers(): bool
    {
        $role = $this->getRoleEnum();
        return $role && $role->canManageUsers();
    }

    /**
     * Check if the user can manage the organizer based on their role.
     */
    public function canManageOrganizer(): bool
    {
        $role = $this->getRoleEnum();
        return $role && $role->canManageOrganizer();
    }

    /**
     * Check if the user can manage events based on their role.
     */
    public function canManageEvents(): bool
    {
        $role = $this->getRoleEnum();
        return $role && $role->canManageEvents();
    }

    /**
     * Check if the user is view-only based on their role.
     */
    public function isViewOnly(): bool
    {
        $role = $this->getRoleEnum();
        return $role && $role->isViewOnly();
    }

    /**
     * Check if the invitation is pending (sent but not accepted).
     */
    public function isPendingInvitation(): bool
    {
        return $this->joined_at !== null && $this->invitation_accepted_at === null;
    }

    /**
     * Check if the invitation has been accepted.
     */
    public function isInvitationAccepted(): bool
    {
        return $this->invitation_accepted_at !== null;
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions !== null && in_array($permission, $this->permissions);
    }

    /**
     * Check if the user has any of the specified permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->permissions === null) {
            return false;
        }

        return !empty(array_intersect($permissions, $this->permissions));
    }

    /**
     * Create an OrganizerUserData instance for a new invitation.
     */
    public static function forInvitation(
        int $organizerId,
        int $userId,
        string $role,
        int $invitedBy,
        ?array $customPermissions = null
    ): self {
        return new self(
            organizer_id: $organizerId,
            user_id: $userId,
            role_in_organizer: $role,
            permissions: $customPermissions,
            joined_at: now(),
            is_active: true,
            invited_by: $invitedBy,
            invitation_accepted_at: null,
        );
    }

    /**
     * Create a new instance with invitation accepted timestamp.
     */
    public function forAcceptance(?Carbon $acceptedAt = null): self
    {
        return new self(
            organizer_id: $this->organizer_id,
            user_id: $this->user_id,
            role_in_organizer: $this->role_in_organizer,
            permissions: $this->permissions,
            joined_at: $this->joined_at,
            is_active: $this->is_active,
            invited_by: $this->invited_by,
            invitation_accepted_at: $acceptedAt ?? now(),
        );
    }

    /**
     * Create a new instance with updated role.
     */
    public function withRole(string $newRole): self
    {
        return new self(
            organizer_id: $this->organizer_id,
            user_id: $this->user_id,
            role_in_organizer: $newRole,
            permissions: $this->permissions,
            joined_at: $this->joined_at,
            is_active: $this->is_active,
            invited_by: $this->invited_by,
            invitation_accepted_at: $this->invitation_accepted_at,
        );
    }

    /**
     * Create a new instance with updated permissions.
     */
    public function withPermissions(?array $newPermissions): self
    {
        return new self(
            organizer_id: $this->organizer_id,
            user_id: $this->user_id,
            role_in_organizer: $this->role_in_organizer,
            permissions: $newPermissions,
            joined_at: $this->joined_at,
            is_active: $this->is_active,
            invited_by: $this->invited_by,
            invitation_accepted_at: $this->invitation_accepted_at,
        );
    }

    /**
     * Create a new instance with updated active status.
     */
    public function withActiveStatus(bool $isActive): self
    {
        return new self(
            organizer_id: $this->organizer_id,
            user_id: $this->user_id,
            role_in_organizer: $this->role_in_organizer,
            permissions: $this->permissions,
            joined_at: $this->joined_at,
            is_active: $isActive,
            invited_by: $this->invited_by,
            invitation_accepted_at: $this->invitation_accepted_at,
        );
    }

    /**
     * Create an OrganizerUserData instance for role updates.
     */
    public static function forRoleUpdate(
        int $organizerId,
        int $userId,
        string $newRole,
        int $updatedBy,
        ?array $customPermissions = null
    ): self {
        return new self(
            organizer_id: $organizerId,
            user_id: $userId,
            role_in_organizer: $newRole,
            permissions: $customPermissions,
            joined_at: now(), // This would be fetched from existing data in real implementation
            is_active: true,
            invited_by: $updatedBy, // Reusing this field for the updater
            invitation_accepted_at: now(), // Assuming already accepted
        );
    }

    /**
     * Get validation rules for organizer user data.
     */
    public static function rules(): array
    {
        return [
            'organizer_id' => ['required', 'integer', 'exists:organizers,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_in_organizer' => ['required', 'string', 'in:owner,manager,staff,viewer'],
            'permissions' => ['nullable', 'array', new ValidOrganizerPermissions()],
            'joined_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'invited_by' => ['required', 'integer', 'exists:users,id'],
            'invitation_accepted_at' => ['nullable', 'date'],
        ];
    }
}
