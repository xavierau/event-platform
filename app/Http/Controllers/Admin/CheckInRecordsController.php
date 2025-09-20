<?php

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\MemberCheckInRecordData;
use App\Enums\RoleNameEnum;
use App\Http\Controllers\Controller;
use App\Services\MemberCheckInRecordsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CheckInRecordsController extends Controller
{
    public function __construct(
        private MemberCheckInRecordsService $memberCheckInRecordsService
    ) {
        // Middleware will be applied at the route level
    }

    /**
     * Display the check-in records page
     */
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();

        Log::info('[MEMBER_CHECK_IN_RECORDS] Page access attempted', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_roles' => $user->roles->pluck('name')->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Check authorization: only admins or users with organizer entity membership can access
        if (! $user->hasRole(RoleNameEnum::ADMIN)) {
            if (! $user->activeOrganizers()->exists()) {
                Log::warning('[MEMBER_CHECK_IN_RECORDS] Access denied - no organizer membership', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
                abort(403, 'You do not have permission to access member check-in records.');
            }
        }

        // Get filters from request
        $filters = $request->only([
            'search',
            'scanner_id',
            'location',
            'start_date',
            'end_date',
            'organization_id',
            'event_id',
            'page',
            'per_page',
        ]);

        // Get paginated records
        $records = $this->memberCheckInRecordsService->getCheckInRecords($user, $filters);

        // Transform records to DTOs
        $transformedRecords = $records->through(function ($memberCheckIn) {
            return MemberCheckInRecordData::fromMemberCheckIn($memberCheckIn);
        });

        // Get statistics
        $stats = $this->memberCheckInRecordsService->getCheckInStats($user, $filters);

        // Get filter options
        $availableEvents = $this->memberCheckInRecordsService->getAvailableEvents($user);
        $availableOrganizers = $this->memberCheckInRecordsService->getAvailableOrganizers($user);
        $availableScanners = $this->memberCheckInRecordsService->getAvailableScanners($user);
        $availableLocations = $this->memberCheckInRecordsService->getAvailableLocations($user);

        Log::info('[MEMBER_CHECK_IN_RECORDS] Page loaded successfully', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->roles->first()?->name,
            'records_count' => $records->total(),
            'is_platform_admin' => $user->hasRole(RoleNameEnum::ADMIN),
            'active_organizers_count' => $user->activeOrganizers()->count(),
            'filters' => $filters,
        ]);

        return Inertia::render('Admin/CheckInRecords/Index', [
            'records' => $transformedRecords,
            'stats' => $stats,
            'filters' => $filters,
            'availableEvents' => $availableEvents,
            'availableOrganizers' => $availableOrganizers,
            'availableScanners' => $availableScanners,
            'availableLocations' => $availableLocations,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
                'is_platform_admin' => $user->hasRole(RoleNameEnum::ADMIN),
            ],
            'pageTitle' => 'Member Check-in Records',
            'breadcrumbs' => [
                ['text' => 'Dashboard', 'href' => route('admin.dashboard')],
                ['text' => 'Member Check-in Records'],
            ],
        ]);
    }

    /**
     * Export member check-in records to CSV
     */
    public function export(Request $request): StreamedResponse|Response
    {
        $user = Auth::user();

        Log::info('[MEMBER_CHECK_IN_RECORDS] Export requested', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip_address' => $request->ip(),
        ]);

        // Check authorization
        if (! $user->hasRole(RoleNameEnum::ADMIN)) {
            if (! $user->activeOrganizers()->exists()) {
                Log::warning('[MEMBER_CHECK_IN_RECORDS] Export denied - no organizer membership', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
                abort(403, 'You do not have permission to export member check-in records.');
            }
        }

        // Get filters from request (same as index)
        $filters = $request->only([
            'search',
            'scanner_id',
            'location',
            'start_date',
            'end_date',
            'organization_id',
            'event_id',
        ]);

        try {
            // Get all records for export (no pagination)
            $records = $this->memberCheckInRecordsService->getCheckInRecordsForExport($user, $filters);

            Log::info('[MEMBER_CHECK_IN_RECORDS] Export completed', [
                'user_id' => $user->id,
                'records_count' => $records->count(),
                'filters' => $filters,
            ]);

            // Export to CSV
            return $this->memberCheckInRecordsService->exportToCsv($records);
        } catch (\Exception $e) {
            Log::error('[MEMBER_CHECK_IN_RECORDS] Export failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed. Please try again.',
            ], 500);
        }
    }
}
