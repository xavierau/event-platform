<?php

namespace App\Services;

use App\Actions\Organizer\AcceptInvitationAction;
use App\Actions\Organizer\InviteUserToOrganizerAction;
use App\Actions\Organizer\RemoveUserFromOrganizerAction;
use App\Actions\Organizer\UpdateOrganizerUserRoleAction;
use App\Actions\Organizer\UpsertOrganizerAction;
use App\DataTransferObjects\Organizer\InviteUserData;
use App\DataTransferObjects\Organizer\OrganizerData;
use App\DataTransferObjects\Organizer\OrganizerUserData;
use App\Models\Organizer;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection;

class OrganizerService
{
    public function __construct(
        protected UpsertOrganizerAction $upsertOrganizerAction,
        protected InviteUserToOrganizerAction $inviteUserToOrganizerAction,
        protected AcceptInvitationAction $acceptInvitationAction,
        protected RemoveUserFromOrganizerAction $removeUserFromOrganizerAction,
        protected UpdateOrganizerUserRoleAction $updateOrganizerUserRoleAction
    ) {}

    public function createOrganizer(OrganizerData $organizerData): Organizer
    {
        // Use the action to create organizer - exclude ID to force creation
        return $this->upsertOrganizerAction->execute($organizerData->except('id'));
    }

    public function updateOrganizer(int $organizerId, OrganizerData $organizerData): Organizer
    {
        // Verify the organizer exists before attempting update
        $existingOrganizer = Organizer::find($organizerId);
        if (!$existingOrganizer) {
            throw new \InvalidArgumentException("Organizer with ID {$organizerId} not found for update operation.");
        }

        // Set the ID for the update operation
        $dataArray = $organizerData->all();
        $dataArray['id'] = $organizerId;
        $updateData = OrganizerData::from($dataArray);

        return $this->upsertOrganizerAction->execute($updateData);
    }

    public function deleteOrganizer(Organizer $organizer): ?bool
    {
        // Consider business logic before deletion:
        // - Check if organizer has active events
        // - Handle user associations
        // - Consider soft delete implications

        // For now, perform soft delete (assuming SoftDeletes trait is used)
        return $organizer->delete();
    }

    public function findOrganizerById(int $id, array $with = []): ?Organizer
    {
        // Default relationships to include
        $defaultWith = ['media', 'users'];
        $relationships = array_merge($defaultWith, $with);

        return Organizer::with($relationships)->find($id);
    }

    public function getOrganizerBySlug(string $slug, array $with = []): ?Organizer
    {
        // Default relationships to include
        $defaultWith = ['media', 'users'];
        $relationships = array_merge($defaultWith, $with);

        return Organizer::with($relationships)->where('slug', $slug)->first();
    }

    public function getAllOrganizers(array $filters = [], array $with = [], string $orderBy = 'name', string $direction = 'asc'): Collection
    {
        // Default relationships to include
        $defaultWith = ['media', 'users'];
        $relationships = array_merge($defaultWith, $with);

        $query = Organizer::with($relationships);

        // Apply filters
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Search filter for name (translatable field)
        if (isset($filters['search']) && !empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                // Search in current locale and fallback locale
                $q->whereJsonContains("name->" . app()->getLocale(), $searchTerm)
                    ->orWhereJsonContains("name->" . config('app.fallback_locale'), $searchTerm);
            });
        }

        // Handle ordering for translatable fields
        if ($orderBy === 'name') {
            $query->orderBy("name->" . app()->getLocale(), $direction);
        } else {
            $query->orderBy($orderBy, $direction);
        }

        return $query->get();
    }

    /**
     * Get active organizers for public display (e.g., event listings, registration forms).
     */
    public function getActiveOrganizers(array $with = [], string $orderBy = 'name', string $direction = 'asc'): Collection
    {
        return $this->getAllOrganizers(
            ['is_active' => true],
            $with,
            $orderBy,
            $direction
        );
    }

    /**
     * Get organizers associated with a specific user.
     */
    public function getOrganizersForUser(User $user, array $with = []): Collection
    {
        // Default relationships to include
        $defaultWith = ['media'];
        $relationships = array_merge($defaultWith, $with);

        return $user->organizers()->with($relationships)->get();
    }

    /**
     * Associate a user with an organizer.
     */
    public function addUserToOrganizer(Organizer $organizer, User $user, string $role = 'staff'): void
    {
        if (!$organizer->users()->where('user_id', $user->id)->exists()) {
            $organizer->users()->attach($user->id, [
                'role_in_organizer' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Remove a user from an organizer.
     */
    public function removeUserFromOrganizer(Organizer $organizer, User $user): void
    {
        $organizer->users()->detach($user->id);
    }

    /**
     * Check if a user can manage an organizer (is associated with it).
     */
    public function userCanManageOrganizer(User $user, Organizer $organizer): bool
    {
        return $organizer->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Get organizers suitable for selection in forms (active organizers only).
     */
    public function getOrganizersForSelection(): Collection
    {
        return $this->getActiveOrganizers(['media'], 'name', 'asc');
    }

    /**
     * Invite a user to join an organizer team.
     */
    public function inviteUser(InviteUserData $inviteUserData): bool
    {
        return $this->inviteUserToOrganizerAction->execute($inviteUserData);
    }

    /**
     * Accept an invitation to join an organizer team.
     */
    public function acceptInvitation(int $organizerId, int $userId): bool
    {
        return $this->acceptInvitationAction->execute($organizerId, $userId);
    }

    /**
     * Accept an invitation and return the updated organizer user data.
     */
    public function acceptInvitationAndReturnData(int $organizerId, int $userId): OrganizerUserData
    {
        return $this->acceptInvitationAction->executeAndReturnData($organizerId, $userId);
    }

    /**
     * Remove a user from an organizer team.
     */
    public function removeUser(int $organizerId, int $userToRemoveId, int $removedBy): bool
    {
        return $this->removeUserFromOrganizerAction->execute($organizerId, $userToRemoveId, $removedBy);
    }

    /**
     * Remove a user from an organizer team with a reason.
     */
    public function removeUserWithReason(int $organizerId, int $userToRemoveId, int $removedBy, ?string $reason = null): bool
    {
        return $this->removeUserFromOrganizerAction->executeWithReason($organizerId, $userToRemoveId, $removedBy, $reason);
    }

    /**
     * Update a user's role within an organizer team.
     */
    public function updateUserRole(int $organizerId, int $userId, string $newRole, int $updatedBy): bool
    {
        return $this->updateOrganizerUserRoleAction->execute($organizerId, $userId, $newRole, $updatedBy);
    }

    /**
     * Update a user's role with custom permissions.
     */
    public function updateUserRoleWithPermissions(
        int $organizerId,
        int $userId,
        string $newRole,
        ?array $customPermissions,
        int $updatedBy
    ): bool {
        return $this->updateOrganizerUserRoleAction->executeWithPermissions(
            $organizerId,
            $userId,
            $newRole,
            $customPermissions,
            $updatedBy
        );
    }

    /**
     * Update a user's role and return the updated data.
     */
    public function updateUserRoleAndReturnData(int $organizerId, int $userId, string $newRole, int $updatedBy): OrganizerUserData
    {
        return $this->updateOrganizerUserRoleAction->executeAndReturnData($organizerId, $userId, $newRole, $updatedBy);
    }

    /**
     * Update organizer user data using OrganizerUserData DTO.
     */
    public function updateOrganizerUserData(OrganizerUserData $organizerUserData): bool
    {
        return $this->updateOrganizerUserRoleAction->executeWithData($organizerUserData);
    }

    /**
     * Get all active team members for an organizer.
     */
    public function getOrganizerUsers(int $organizerId, bool $activeOnly = true): Collection
    {
        $organizer = Organizer::with(['users' => function ($query) use ($activeOnly) {
            if ($activeOnly) {
                $query->where('is_active', true);
            }
        }])->find($organizerId);

        if (!$organizer) {
            throw new \InvalidArgumentException("Organizer with ID {$organizerId} not found.");
        }

        return $organizer->users;
    }

    /**
     * Get all pending invitations for an organizer.
     */
    public function getPendingInvitations(int $organizerId): Collection
    {
        $organizer = Organizer::with(['users' => function ($query) {
            $query->where('is_active', true)
                ->whereNull('invitation_accepted_at');
        }])->find($organizerId);

        if (!$organizer) {
            throw new \InvalidArgumentException("Organizer with ID {$organizerId} not found.");
        }

        return $organizer->users;
    }

    /**
     * Check if a user has a specific role in an organizer.
     */
    public function userHasRole(int $organizerId, int $userId, string $role): bool
    {
        $organizer = Organizer::find($organizerId);

        if (!$organizer) {
            return false;
        }

        $membership = $organizer->users()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        return $membership && $membership->pivot->role_in_organizer === $role;
    }

    /**
     * Check if a user can invite others to an organizer.
     */
    public function userCanInvite(int $organizerId, int $userId): bool
    {
        return $this->userHasRole($organizerId, $userId, 'owner') ||
            $this->userHasRole($organizerId, $userId, 'manager');
    }

    /**
     * Check if a user can remove others from an organizer.
     */
    public function userCanRemove(int $organizerId, int $userId): bool
    {
        return $this->userHasRole($organizerId, $userId, 'owner') ||
            $this->userHasRole($organizerId, $userId, 'manager');
    }

    /**
     * Check if a user can update roles within an organizer team.
     */
    public function userCanUpdateRoles(int $organizerId, int $userId): bool
    {
        $user = User::find($userId);
        $organizer = Organizer::find($organizerId);

        if (!$user || !$organizer) {
            return false;
        }

        $role = $user->getOrganizerRole($organizer);
        return $role && in_array($role->value, ['owner', 'manager']);
    }

    // ===========================
    // VENUE MANAGEMENT METHODS (ORG-009.3)
    // ===========================

    /**
     * Assign a venue to an organizer (basic implementation until ORG-008 actions are complete).
     */
    public function assignVenue(int $organizerId, int $venueId): bool
    {
        $organizer = Organizer::find($organizerId);
        $venue = Venue::find($venueId);

        if (!$organizer || !$venue) {
            return false;
        }

        // Basic implementation: Update venue organizer_id
        // This will be enhanced when ORG-008 actions are implemented
        $venue->update(['organizer_id' => $organizerId]);

        return true;
    }

    /**
     * Unassign a venue from an organizer (make it public).
     */
    public function unassignVenue(int $venueId): bool
    {
        $venue = Venue::find($venueId);

        if (!$venue) {
            return false;
        }

        // Basic implementation: Set organizer_id to null (public venue)
        // This will be enhanced when ORG-008 actions are implemented
        $venue->update(['organizer_id' => null]);

        return true;
    }

    /**
     * Get available venues that can be assigned to organizers.
     * Returns public venues (organizer_id is null).
     */
    public function getAvailableVenues(array $with = []): Collection
    {
        $defaultWith = ['media', 'state', 'country'];
        $relationships = array_merge($defaultWith, $with);

        return Venue::with($relationships)
            ->whereNull('organizer_id')
            ->where('is_active', true)
            ->orderBy('name->' . app()->getLocale())
            ->get();
    }

    /**
     * Get venues owned by a specific organizer.
     */
    public function getOrganizerVenues(int $organizerId, array $with = []): Collection
    {
        $defaultWith = ['media', 'state', 'country'];
        $relationships = array_merge($defaultWith, $with);

        return Venue::with($relationships)
            ->where('organizer_id', $organizerId)
            ->where('is_active', true)
            ->orderBy('name->' . app()->getLocale())
            ->get();
    }

    /**
     * Get all venues accessible to an organizer (owned + public).
     */
    public function getAccessibleVenues(int $organizerId, array $with = []): Collection
    {
        $defaultWith = ['media', 'state', 'country'];
        $relationships = array_merge($defaultWith, $with);

        return Venue::with($relationships)
            ->where(function ($query) use ($organizerId) {
                $query->where('organizer_id', $organizerId)
                    ->orWhereNull('organizer_id');
            })
            ->where('is_active', true)
            ->orderBy('name->' . app()->getLocale())
            ->get();
    }

    // ===========================
    // QUERY AND UTILITY METHODS (ORG-009.4)
    // ===========================

    /**
     * Get all organizers that a user belongs to.
     */
    public function getUserOrganizers(int $userId, bool $activeOnly = true): Collection
    {
        $user = User::find($userId);

        if (!$user) {
            return new Collection();
        }

        $query = $user->organizers()->with(['media']);

        if ($activeOnly) {
            $query->where('organizers.is_active', true)
                ->wherePivot('is_active', true);
        }

        return $query->orderBy('organizers.name->' . app()->getLocale())->get();
    }

    /**
     * Search organizers by various criteria.
     */
    public function searchOrganizers(
        string $searchTerm,
        array $filters = [],
        int $limit = 50,
        array $with = []
    ): Collection {
        $defaultWith = ['media'];
        $relationships = array_merge($defaultWith, $with);

        $query = Organizer::with($relationships);

        // Search in translatable name and description fields
        $query->where(function ($q) use ($searchTerm) {
            $currentLocale = app()->getLocale();
            $fallbackLocale = config('app.fallback_locale');

            $q->where("name->{$currentLocale}", 'like', "%{$searchTerm}%")
                ->orWhere("name->{$fallbackLocale}", 'like', "%{$searchTerm}%")
                ->orWhere("description->{$currentLocale}", 'like', "%{$searchTerm}%")
                ->orWhere("description->{$fallbackLocale}", 'like', "%{$searchTerm}%")
                ->orWhere('contact_email', 'like', "%{$searchTerm}%")
                ->orWhere('website_url', 'like', "%{$searchTerm}%");
        });

        // Apply additional filters
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['country_id'])) {
            $query->where('country_id', $filters['country_id']);
        }

        if (isset($filters['state_id'])) {
            $query->where('state_id', $filters['state_id']);
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        return $query->orderBy('name->' . app()->getLocale())
            ->limit($limit)
            ->get();
    }

    /**
     * Get comprehensive statistics for an organizer.
     */
    public function getOrganizerStats(int $organizerId): array
    {
        $organizer = Organizer::with(['users', 'events', 'venues'])->find($organizerId);

        if (!$organizer) {
            return [];
        }

        // Basic stats calculation
        $stats = [
            'organizer_id' => $organizerId,
            'organizer_name' => $organizer->name,
            'organizer_slug' => $organizer->slug,

            // Team statistics
            'total_users' => $organizer->users()->count(),
            'active_users' => $organizer->users()->wherePivot('is_active', true)->count(),
            'pending_invitations' => $organizer->users()->wherePivot('invitation_accepted_at', null)->count(),

            // Event statistics (if relationships exist)
            'total_events' => $organizer->events()->count() ?? 0,
            'upcoming_events' => $organizer->events()->where('start_date', '>', now())->count() ?? 0,
            'past_events' => $organizer->events()->where('end_date', '<', now())->count() ?? 0,

            // Venue statistics
            'owned_venues' => Venue::where('organizer_id', $organizerId)->count(),
            'active_owned_venues' => Venue::where('organizer_id', $organizerId)->where('is_active', true)->count(),

            // Role distribution
            'role_distribution' => $organizer->users()
                ->selectRaw('role_in_organizer, COUNT(*) as count')
                ->groupBy('role_in_organizer')
                ->pluck('count', 'role_in_organizer')
                ->toArray(),

            // Timestamps
            'created_at' => $organizer->created_at,
            'updated_at' => $organizer->updated_at,
        ];

        return $stats;
    }

    /**
     * Get statistics for multiple organizers.
     */
    public function getMultipleOrganizerStats(array $organizerIds): array
    {
        return collect($organizerIds)->map(function ($organizerId) {
            return $this->getOrganizerStats($organizerId);
        })->filter()->toArray();
    }

    /**
     * Get organizer performance metrics.
     */
    public function getOrganizerMetrics(int $organizerId, array $dateRange = []): array
    {
        $organizer = Organizer::find($organizerId);

        if (!$organizer) {
            return [];
        }

        $metrics = [
            'organizer_id' => $organizerId,
            'metrics_period' => $dateRange,

            // Team growth metrics
            'team_size_history' => $this->getTeamGrowthMetrics($organizerId, $dateRange),

            // Activity metrics
            'recent_invitations' => $organizer->users()
                ->wherePivot('created_at', '>=', now()->subDays(30))
                ->count(),

            'recent_role_changes' => $organizer->users()
                ->wherePivot('updated_at', '>=', now()->subDays(30))
                ->count(),
        ];

        return $metrics;
    }

    /**
     * Helper method to get team growth metrics.
     */
    protected function getTeamGrowthMetrics(int $organizerId, array $dateRange = []): array
    {
        // Basic implementation - can be enhanced with more detailed tracking
        $organizer = Organizer::find($organizerId);

        if (!$organizer) {
            return [];
        }

        return [
            'current_team_size' => $organizer->users()->wherePivot('is_active', true)->count(),
            'total_invitations_sent' => $organizer->users()->count(),
            'acceptance_rate' => $this->calculateAcceptanceRate($organizerId),
        ];
    }

    /**
     * Calculate invitation acceptance rate for an organizer.
     */
    protected function calculateAcceptanceRate(int $organizerId): float
    {
        $organizer = Organizer::find($organizerId);

        if (!$organizer) {
            return 0.0;
        }

        $totalInvitations = $organizer->users()->count();
        $acceptedInvitations = $organizer->users()->whereNotNull('organizer_users.invitation_accepted_at')->count();

        if ($totalInvitations === 0) {
            return 0.0;
        }

        return round(($acceptedInvitations / $totalInvitations) * 100, 2);
    }
}
