<?php

namespace App\Services;

use App\DataTransferObjects\CheckInRecordData;
use App\Enums\CheckInStatus;
use App\Enums\RoleNameEnum;
use App\Models\CheckInLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckInRecordsService
{
    /**
     * Get check-in records with filtering and pagination
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
     * Get all check-in records for export (no pagination)
     */
    public function getCheckInRecordsForExport(User $user, array $filters = []): Collection
    {
        $query = $this->buildQuery($user, $filters);

        return $query->get()->map(function ($checkInLog) {
            return CheckInRecordData::fromCheckInLog($checkInLog);
        });
    }

    /**
     * Export records to CSV
     */
    public function exportToCsv(Collection $records): Response
    {
        $filename = 'check-in-records-'.now()->format('Y-m-d-H-i-s').'.csv';

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
                'Check-in Time',
                'Status',
                'Method',
                'Event Name',
                'Occurrence Name',
                'Occurrence Start',
                'Venue',
                'Attendee Name',
                'Attendee Email',
                'Booking Number',
                'Booking Quantity',
                'Operator Name',
                'Organization',
                'Location',
                'Notes',
            ]);

            // CSV Data
            foreach ($records as $record) {
                fputcsv($file, [
                    $record->check_in_timestamp->format('Y-m-d H:i:s'),
                    $record->status->value,
                    $record->method->value,
                    is_array($record->event->name) ? ($record->event->name['en'] ?? reset($record->event->name)) : $record->event->name,
                    is_array($record->event_occurrence->name) ? ($record->event_occurrence->name['en'] ?? reset($record->event_occurrence->name)) : $record->event_occurrence->name,
                    $record->event_occurrence->start_at->format('Y-m-d H:i:s'),
                    $record->event_occurrence->venue_name ?? '',
                    $record->booking->user->name,
                    $record->booking->user->email,
                    $record->booking->booking_number,
                    $record->booking->quantity,
                    $record->operator?->name ?? '',
                    is_array($record->organizer->name) ? ($record->organizer->name['en'] ?? reset($record->organizer->name)) : $record->organizer->name,
                    $record->location_description ?? '',
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
        $query = CheckInLog::with([
            'booking.user',
            'booking.event.organizer',
            'eventOccurrence',
            'operator',
        ])->orderBy('check_in_timestamp', 'desc');

        // Apply organization filter based on user role
        $query = $this->applyOrganizationFilter($query, $user);

        // Apply search filter
        if (! empty($filters['search'])) {
            $query = $this->applySearchFilter($query, $filters['search']);
        }

        // Apply status filter
        if (! empty($filters['status'])) {
            $query = $this->applyStatusFilter($query, $filters['status']);
        }

        // Apply method filter
        if (! empty($filters['method'])) {
            $query = $this->applyMethodFilter($query, $filters['method']);
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
            return $query->whereHas('booking.event', function (Builder $eventQuery) use ($userOrganizerIds) {
                $eventQuery->whereIn('organizer_id', $userOrganizerIds);
            });
        }

        // If user has no organizer access, return empty results
        return $query->whereRaw('1 = 0');
    }

    /**
     * Apply search filter for user name, email, or booking number
     */
    public function applySearchFilter(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->whereHas('booking.user', function (Builder $userQuery) use ($search) {
                $userQuery->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            })
                ->orWhereHas('booking', function (Builder $bookingQuery) use ($search) {
                    $bookingQuery->where('booking_number', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('booking.event', function (Builder $eventQuery) use ($search) {
                    $eventQuery->where('name->en', 'LIKE', "%{$search}%")
                        ->orWhere('name', 'LIKE', "%{$search}%");
                });
        });
    }

    /**
     * Apply status filter
     */
    public function applyStatusFilter(Builder $query, string $status): Builder
    {
        if ($status === 'successful') {
            return $query->where('status', CheckInStatus::SUCCESSFUL);
        }

        if ($status === 'failed') {
            return $query->where('status', '!=', CheckInStatus::SUCCESSFUL);
        }

        // If specific status is provided, filter by that
        return $query->where('status', $status);
    }

    /**
     * Apply method filter
     */
    public function applyMethodFilter(Builder $query, string $method): Builder
    {
        return $query->where('method', $method);
    }

    /**
     * Apply date range filter
     */
    public function applyDateRangeFilter(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        if ($startDate) {
            $query->where('check_in_timestamp', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('check_in_timestamp', '<=', Carbon::parse($endDate)->endOfDay());
        }

        return $query;
    }

    /**
     * Apply specific organization filter (for platform admins only)
     */
    public function applySpecificOrganizationFilter(Builder $query, int $organizationId): Builder
    {
        return $query->whereHas('booking.event', function (Builder $eventQuery) use ($organizationId) {
            $eventQuery->where('organizer_id', $organizationId);
        });
    }

    /**
     * Apply event filter
     */
    public function applyEventFilter(Builder $query, int $eventId): Builder
    {
        return $query->whereHas('booking', function (Builder $bookingQuery) use ($eventId) {
            $bookingQuery->where('event_id', $eventId);
        });
    }

    /**
     * Get check-in statistics for dashboard
     */
    public function getCheckInStats(User $user, array $filters = []): array
    {
        $query = $this->buildQuery($user, $filters);

        // Remove ordering for aggregation queries
        $baseQuery = clone $query;
        $baseQuery->getQuery()->orders = null;

        $total = $baseQuery->count();
        $successful = $baseQuery->where('status', CheckInStatus::SUCCESSFUL)->count();
        $failed = $total - $successful;

        $todayQuery = clone $baseQuery;
        $today = $todayQuery->whereDate('check_in_timestamp', Carbon::today())->count();

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'today' => $today,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 1) : 0,
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
}
