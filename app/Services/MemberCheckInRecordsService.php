<?php

namespace App\Services;

use App\DataTransferObjects\MemberCheckInRecordData;
use App\Enums\RoleNameEnum;
use App\Models\MemberCheckIn;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberCheckInRecordsService
{
    /**
     * Get member check-in records with filtering and pagination
     */
    public function getCheckInRecords(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $this->buildQuery($user, $filters);

        return $query->paginate(
            perPage: $filters['per_page'] ?? 25,
            page: $filters['page'] ?? 1
        );
    }

    /**
     * Get all member check-in records for export (no pagination)
     */
    public function getCheckInRecordsForExport(User $user, array $filters = []): Collection
    {
        $query = $this->buildQuery($user, $filters);

        return $query->get()->map(function ($memberCheckIn) {
            return MemberCheckInRecordData::fromMemberCheckIn($memberCheckIn);
        });
    }

    /**
     * Export records to CSV
     */
    public function exportToCsv(Collection $records): StreamedResponse
    {
        $filename = 'member-check-in-records-'.now()->format('Y-m-d-H-i-s').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
            'Pragma' => 'public',
        ];

        $callback = function () use ($records) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM for proper Excel encoding
            fwrite($file, "\xEF\xBB\xBF");

            // CSV Headers
            fputcsv($file, [
                'Scan Time',
                'Member Name',
                'Member Email',
                'Scanner Name',
                'Scanner Email',
                'Location',
                'Device Identifier',
                'Membership Level',
                'Membership Status',
                'Event Name',
                'Event Occurrence',
                'Notes',
            ]);

            // CSV Data
            foreach ($records as $record) {
                fputcsv($file, [
                    $record->scanned_at->format('Y-m-d H:i:s'),
                    $record->member->name,
                    $record->member->email,
                    $record->scanner?->name ?? '',
                    $record->scanner?->email ?? '',
                    $record->location ?? '',
                    $record->device_identifier ?? '',
                    $record->membership_data['membershipLevel'] ?? $record->membership_data['level'] ?? '',
                    $record->membership_data['membershipStatus'] ?? $record->membership_data['status'] ?? '',
                    $record->event ? (is_array($record->event->name) ? ($record->event->name['en'] ?? reset($record->event->name)) : $record->event->name) : '',
                    $record->event_occurrence ? (is_array($record->event_occurrence->name) ? ($record->event_occurrence->name['en'] ?? reset($record->event_occurrence->name)) : $record->event_occurrence->name) : '',
                    $record->notes ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Build the base query with all necessary relationships and filters
     */
    private function buildQuery(User $user, array $filters): Builder
    {
        $query = MemberCheckIn::with([
            'member',
            'scanner',
            'event.organizer',
            'eventOccurrence',
        ])->orderBy('scanned_at', 'desc');

        // Apply organization filter based on user role
        $query = $this->applyOrganizationFilter($query, $user);

        // Apply search filter
        if (! empty($filters['search'])) {
            $query = $this->applySearchFilter($query, $filters['search']);
        }

        // Apply scanner filter
        if (! empty($filters['scanner_id'])) {
            $query = $this->applyScannerFilter($query, $filters['scanner_id']);
        }

        // Apply location filter
        if (! empty($filters['location'])) {
            $query = $this->applyLocationFilter($query, $filters['location']);
        }

        // Apply date range filter
        if (! empty($filters['start_date']) || ! empty($filters['end_date'])) {
            $query = $this->applyDateRangeFilter(
                $query,
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null
            );
        }

        // Apply organization filter for platform admins
        if (! empty($filters['organization_id']) && $user->hasRole(RoleNameEnum::ADMIN)) {
            $query = $this->applySpecificOrganizationFilter($query, $filters['organization_id']);
        }

        // Apply event filter
        if (! empty($filters['event_id'])) {
            $query = $this->applyEventFilter($query, $filters['event_id']);
        }

        return $query;
    }

    /**
     * Apply organization filter based on user permissions
     */
    public function applyOrganizationFilter(Builder $query, User $user): Builder
    {
        // Platform admins can see all records
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return $query;
        }

        // Organization admins can only see records from their organizations
        $userOrganizerIds = $user->activeOrganizers()->pluck('organizers.id');

        if ($userOrganizerIds->isNotEmpty()) {
            return $query->whereHas('event', function (Builder $eventQuery) use ($userOrganizerIds) {
                $eventQuery->whereIn('organizer_id', $userOrganizerIds);
            });
        }

        // If user has no organizer access, return empty results
        return $query->whereRaw('1 = 0');
    }

    /**
     * Apply search filter for member name, email
     */
    public function applySearchFilter(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->whereHas('member', function (Builder $memberQuery) use ($search) {
                $memberQuery->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        });
    }

    /**
     * Apply scanner filter
     */
    public function applyScannerFilter(Builder $query, int $scannerId): Builder
    {
        return $query->where('scanned_by_user_id', $scannerId);
    }

    /**
     * Apply location filter
     */
    public function applyLocationFilter(Builder $query, string $location): Builder
    {
        return $query->where('location', 'LIKE', "%{$location}%");
    }

    /**
     * Apply date range filter
     */
    public function applyDateRangeFilter(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        if ($startDate) {
            $query->where('scanned_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('scanned_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        return $query;
    }

    /**
     * Apply specific organization filter (for platform admins only)
     */
    public function applySpecificOrganizationFilter(Builder $query, int $organizationId): Builder
    {
        return $query->whereHas('event', function (Builder $eventQuery) use ($organizationId) {
            $eventQuery->where('organizer_id', $organizationId);
        });
    }

    /**
     * Apply event filter
     */
    public function applyEventFilter(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    /**
     * Get member check-in statistics for dashboard
     */
    public function getCheckInStats(User $user, array $filters = []): array
    {
        $query = $this->buildQuery($user, $filters);

        // Remove ordering for aggregation queries
        $baseQuery = clone $query;
        $baseQuery->getQuery()->orders = null;

        $total = $baseQuery->count();

        $todayQuery = clone $baseQuery;
        $today = $todayQuery->whereDate('scanned_at', Carbon::today())->count();

        // Count unique members
        $uniqueMembersQuery = clone $baseQuery;
        $uniqueMembers = $uniqueMembersQuery->distinct('user_id')->count('user_id');

        return [
            'total' => $total,
            'today' => $today,
            'unique_members' => $uniqueMembers,
        ];
    }

    /**
     * Get available events for filtering based on user permissions
     */
    public function getAvailableEvents(User $user): Collection
    {
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return DB::table('events')
                ->select('id', 'name', 'organizer_id')
                ->where('event_status', 'published')
                ->orderBy('name->en')
                ->get();
        }

        $userOrganizerIds = $user->activeOrganizers()->pluck('organizers.id');

        if ($userOrganizerIds->isNotEmpty()) {
            return DB::table('events')
                ->select('id', 'name', 'organizer_id')
                ->where('event_status', 'published')
                ->whereIn('organizer_id', $userOrganizerIds)
                ->orderBy('name->en')
                ->get();
        }

        return collect();
    }

    /**
     * Get available organizers for filtering (platform admins only)
     */
    public function getAvailableOrganizers(User $user): Collection
    {
        if (! $user->hasRole(RoleNameEnum::ADMIN)) {
            return collect();
        }

        return DB::table('organizers')
            ->select('id', 'name', 'slug')
            ->where('is_active', 1)
            ->orderBy('name->en')
            ->get();
    }

    /**
     * Get available scanners for filtering
     */
    public function getAvailableScanners(User $user): Collection
    {
        $query = MemberCheckIn::with('scanner')
            ->select('scanned_by_user_id')
            ->distinct()
            ->whereNotNull('scanned_by_user_id');

        // Apply organization filter
        $query = $this->applyOrganizationFilter($query, $user);

        return $query->get()
            ->pluck('scanner')
            ->filter()
            ->unique('id')
            ->values()
            ->map(function ($scanner) {
                return [
                    'id' => $scanner->id,
                    'name' => $scanner->name,
                    'email' => $scanner->email,
                ];
            });
    }

    /**
     * Get available locations for filtering
     */
    public function getAvailableLocations(User $user): Collection
    {
        $query = MemberCheckIn::select('location')
            ->distinct()
            ->whereNotNull('location')
            ->where('location', '!=', '');

        // Apply organization filter
        $query = $this->applyOrganizationFilter($query, $user);

        return $query->pluck('location')
            ->filter()
            ->sort()
            ->values();
    }
}
