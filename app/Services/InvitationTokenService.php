<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InvitationTokenService
{
    /**
     * Generate a secure invitation token for a user.
     */
    public function generateInvitationToken(
        int $organizerId,
        string $email,
        string $role,
        ?int $existingUserId = null,
        ?Carbon $expiresAt = null
    ): string {
        $payload = [
            'organizer_id' => $organizerId,
            'email' => $email,
            'role' => $role,
            'existing_user_id' => $existingUserId,
            'timestamp' => now()->timestamp,
            'nonce' => Str::random(16),
        ];

        $expirationTime = $expiresAt ?? now()->addDays(7);
        
        // Create a signed URL that expires
        return URL::temporarySignedRoute(
            'invitation.accept',
            $expirationTime,
            $payload
        );
    }

    /**
     * Validate an invitation token and extract its data.
     */
    public function validateInvitationToken(array $parameters): array
    {
        // Laravel's signed URL validation is handled by the middleware
        // We just need to extract and validate the payload
        
        $required = ['organizer_id', 'email', 'role', 'timestamp'];
        
        foreach ($required as $field) {
            if (!isset($parameters[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Additional validation can be added here
        return [
            'organizer_id' => (int) $parameters['organizer_id'],
            'email' => $parameters['email'],
            'role' => $parameters['role'],
            'existing_user_id' => isset($parameters['existing_user_id']) ? (int) $parameters['existing_user_id'] : null,
            'timestamp' => (int) $parameters['timestamp'],
        ];
    }

    /**
     * Check if a user already exists for the invitation.
     */
    public function userExistsForInvitation(string $email, ?int $existingUserId = null): bool
    {
        if ($existingUserId) {
            return User::where('id', $existingUserId)->where('email', $email)->exists();
        }

        return User::where('email', $email)->exists();
    }

    /**
     * Determine if this is a new user invitation.
     */
    public function isNewUserInvitation(array $tokenData): bool
    {
        // If existing_user_id is provided, it's for an existing user
        if (!empty($tokenData['existing_user_id'])) {
            return false;
        }

        // Check if user exists in database
        return !$this->userExistsForInvitation($tokenData['email']);
    }

    /**
     * Generate invitation URL for new users.
     */
    public function generateNewUserInvitationUrl(
        int $organizerId,
        string $email,
        string $role,
        ?Carbon $expiresAt = null
    ): string {
        return $this->generateInvitationToken(
            organizerId: $organizerId,
            email: $email,
            role: $role,
            existingUserId: null,
            expiresAt: $expiresAt
        );
    }

    /**
     * Generate invitation URL for existing users.
     */
    public function generateExistingUserInvitationUrl(
        int $organizerId,
        int $userId,
        string $email,
        string $role,
        ?Carbon $expiresAt = null
    ): string {
        return $this->generateInvitationToken(
            organizerId: $organizerId,
            email: $email,
            role: $role,
            existingUserId: $userId,
            expiresAt: $expiresAt
        );
    }
}