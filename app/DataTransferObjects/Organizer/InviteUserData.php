<?php

namespace App\DataTransferObjects\Organizer;

use App\Enums\OrganizerRoleEnum;
use App\Models\User;
use App\Rules\ValidOrganizerPermissions;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class InviteUserData extends Data
{
    public function __construct(
        public readonly int $organizer_id,
        public readonly string $email,
        public readonly string $role_in_organizer,
        public readonly int $invited_by,
        public readonly ?array $custom_permissions = null,
        public readonly ?string $invitation_message = null,
        public readonly ?int $existing_user_id = null,
        public readonly ?Carbon $expires_at = null,
    ) {}

    /**
     * Get the role as an enum.
     */
    public function getRoleEnum(): ?OrganizerRoleEnum
    {
        return OrganizerRoleEnum::tryFrom($this->role_in_organizer);
    }

    /**
     * Check if this invitation is for an existing user.
     */
    public function isForExistingUser(): bool
    {
        return $this->existing_user_id !== null;
    }

    /**
     * Check if this invitation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if custom permissions are provided.
     */
    public function hasCustomPermissions(): bool
    {
        return $this->custom_permissions !== null && !empty($this->custom_permissions);
    }

    /**
     * Check if an invitation message is provided.
     */
    public function hasInvitationMessage(): bool
    {
        return $this->invitation_message !== null && trim($this->invitation_message) !== '';
    }

    /**
     * Check if the user has a specific custom permission.
     */
    public function hasCustomPermission(string $permission): bool
    {
        return $this->hasCustomPermissions() && in_array($permission, $this->custom_permissions);
    }

    /**
     * Get the expiration time in a human-readable format.
     */
    public function getExpirationDescription(): string
    {
        if ($this->expires_at === null) {
            return 'No expiration';
        }

        if ($this->isExpired()) {
            return 'Expired ' . $this->expires_at->diffForHumans();
        }

        return 'Expires ' . $this->expires_at->diffForHumans();
    }

    /**
     * Create an InviteUserData instance for a new user by email.
     */
    public static function forNewUser(
        int $organizerId,
        string $email,
        string $role,
        int $invitedBy,
        ?array $customPermissions = null,
        ?string $message = null,
        ?Carbon $expiresAt = null
    ): self {
        return new self(
            organizer_id: $organizerId,
            email: $email,
            role_in_organizer: $role,
            invited_by: $invitedBy,
            custom_permissions: $customPermissions,
            invitation_message: $message,
            existing_user_id: null,
            expires_at: $expiresAt ?? now()->addDays(7), // Default 7 days expiration
        );
    }

    /**
     * Create an InviteUserData instance for an existing user.
     */
    public static function forExistingUser(
        int $organizerId,
        int $userId,
        string $role,
        int $invitedBy,
        ?array $customPermissions = null,
        ?string $message = null,
        ?Carbon $expiresAt = null
    ): self {
        $user = User::find($userId);

        if (!$user) {
            throw new \InvalidArgumentException("User with ID {$userId} not found");
        }

        return new self(
            organizer_id: $organizerId,
            email: $user->email,
            role_in_organizer: $role,
            invited_by: $invitedBy,
            custom_permissions: $customPermissions,
            invitation_message: $message,
            existing_user_id: $userId,
            expires_at: $expiresAt ?? now()->addDays(7), // Default 7 days expiration
        );
    }

    /**
     * Create a new instance with updated expiration time.
     */
    public function withExpirationTime(Carbon $expiresAt): self
    {
        return new self(
            organizer_id: $this->organizer_id,
            email: $this->email,
            role_in_organizer: $this->role_in_organizer,
            invited_by: $this->invited_by,
            custom_permissions: $this->custom_permissions,
            invitation_message: $this->invitation_message,
            existing_user_id: $this->existing_user_id,
            expires_at: $expiresAt,
        );
    }

    /**
     * Create a new instance with updated role.
     */
    public function withRole(string $newRole): self
    {
        return new self(
            organizer_id: $this->organizer_id,
            email: $this->email,
            role_in_organizer: $newRole,
            invited_by: $this->invited_by,
            custom_permissions: $this->custom_permissions,
            invitation_message: $this->invitation_message,
            existing_user_id: $this->existing_user_id,
            expires_at: $this->expires_at,
        );
    }

    /**
     * Create a new instance with updated custom permissions.
     */
    public function withCustomPermissions(?array $newPermissions): self
    {
        return new self(
            organizer_id: $this->organizer_id,
            email: $this->email,
            role_in_organizer: $this->role_in_organizer,
            invited_by: $this->invited_by,
            custom_permissions: $newPermissions,
            invitation_message: $this->invitation_message,
            existing_user_id: $this->existing_user_id,
            expires_at: $this->expires_at,
        );
    }

    /**
     * Create a new instance with updated invitation message.
     */
    public function withInvitationMessage(?string $newMessage): self
    {
        return new self(
            organizer_id: $this->organizer_id,
            email: $this->email,
            role_in_organizer: $this->role_in_organizer,
            invited_by: $this->invited_by,
            custom_permissions: $this->custom_permissions,
            invitation_message: $newMessage,
            existing_user_id: $this->existing_user_id,
            expires_at: $this->expires_at,
        );
    }

    /**
     * Transform this invitation data to OrganizerUserData for when the invitation is accepted.
     */
    public function toOrganizerUserData(int $userId): OrganizerUserData
    {
        return OrganizerUserData::forInvitation(
            organizerId: $this->organizer_id,
            userId: $userId,
            role: $this->role_in_organizer,
            invitedBy: $this->invited_by,
            customPermissions: $this->custom_permissions
        );
    }

    /**
     * Get validation rules for invite user data.
     */
    public static function rules(): array
    {
        return [
            'organizer_id' => ['required', 'integer', 'exists:organizers,id'],
            'email' => ['required', 'email', 'max:255'],
            'role_in_organizer' => ['required', 'string', 'in:owner,manager,staff,viewer'],
            'invited_by' => ['required', 'integer', 'exists:users,id'],
            'custom_permissions' => ['nullable', 'array', new ValidOrganizerPermissions()],
            'invitation_message' => ['nullable', 'string', 'max:1000'],
            'existing_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * Get validation rules for new user invitations.
     */
    public static function newUserRules(): array
    {
        $rules = static::rules();
        $rules['email'][] = 'unique:users,email'; // Email must not already exist for new users
        $rules['existing_user_id'] = ['nullable']; // Should be null for new users

        return $rules;
    }

    /**
     * Get validation rules for existing user invitations.
     */
    public static function existingUserRules(): array
    {
        $rules = static::rules();
        $rules['email'][] = 'exists:users,email'; // Email must exist for existing users
        $rules['existing_user_id'] = ['required', 'integer', 'exists:users,id']; // Must be provided for existing users

        return $rules;
    }
}
