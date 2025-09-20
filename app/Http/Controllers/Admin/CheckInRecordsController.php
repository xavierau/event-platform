<?php

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\CheckInRecordData;
use App\Enums\CheckInMethod;
use App\Enums\RoleNameEnum;
use App\Http\Controllers\Controller;
use App\Services\CheckInRecordsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CheckInRecordsController extends Controller
{
    public function __construct(
        private CheckInRecordsService $checkInRecordsService
    ) {
        // Middleware will be applied at the route level
    }

    /**
     * Display the check-in records page
     */
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();

        Log::info('[CHECK_IN_RECORDS] Page access attempted', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_roles' => $user->roles->pluck('name')->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Check authorization: only admins or users with organizer entity membership can access
        if (! $user->hasRole(RoleNameEnum::ADMIN)) {
            if (! $user->activeOrganizers()->exists()) {
                Log::warning('[CHECK_IN_RECORDS] Access denied - no organizer membership', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
                abort(403, 'You do not have permission to access check-in records.');
            }
        }

        // Get filters from request
        $filters = $request->only([
            'search',
            'status',
            'method',
            'start_date',
            'end_date',
            'organization_id',
            'event_id',
            'page',
            'per_page',
        ]);

        // Get paginated records
        $records = $this->checkInRecordsService->getCheckInRecords($user, $filters);

        // Transform records to DTOs
        $transformedRecords = $records->through(function ($checkInLog) {
            return CheckInRecordData::fromCheckInLog($checkInLog);
        });

        // Get statistics
        $stats = $this->checkInRecordsService->getCheckInStats($user, $filters);

        // Get filter options
        $availableEvents = $this->checkInRecordsService->getAvailableEvents($user);
        $availableOrganizers = $this->checkInRecordsService->getAvailableOrganizers($user);

        Log::info('[CHECK_IN_RECORDS] Page loaded successfully', [
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
            'statusOptions' => [
                ['value' => '', 'label' => 'All Statuses'],
                ['value' => 'successful', 'label' => 'Successful'],
                ['value' => 'failed', 'label' => 'Failed'],
            ],
            'methodOptions' => [
                ['value' => '', 'label' => 'All Methods'],
                ['value' => CheckInMethod::QR_SCAN->value, 'label' => 'QR Code Scan'],
                ['value' => CheckInMethod::MANUAL_ENTRY->value, 'label' => 'Manual Entry'],
                ['value' => CheckInMethod::API_INTEGRATION->value, 'label' => 'API Integration'],
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
                'is_platform_admin' => $user->hasRole(RoleNameEnum::ADMIN),
            ],
            'pageTitle' => 'Check-in Records',
            'breadcrumbs' => [
                ['text' => 'Dashboard', 'href' => route('admin.dashboard')],
                ['text' => 'Check-in Records'],
            ],
        ]);
    }

    /**
     * Export check-in records to CSV
     */
    public function export(Request $request): Response
    {
        $user = Auth::user();

        Log::info('[CHECK_IN_RECORDS] Export requested', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip_address' => $request->ip(),
        ]);

        // Check authorization
        if (! $user->hasRole(RoleNameEnum::ADMIN)) {
            if (! $user->activeOrganizers()->exists()) {
                Log::warning('[CHECK_IN_RECORDS] Export denied - no organizer membership', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
                abort(403, 'You do not have permission to export check-in records.');
            }
        }

        // Get filters from request (same as index)
        $filters = $request->only([
            'search',
            'status',
            'method',
            'start_date',
            'end_date',
            'organization_id',
            'event_id',
        ]);

        try {
            // Get all records for export (no pagination)
            $records = $this->checkInRecordsService->getCheckInRecordsForExport($user, $filters);

            Log::info('[CHECK_IN_RECORDS] Export completed', [
                'user_id' => $user->id,
                'records_count' => $records->count(),
                'filters' => $filters,
            ]);

            // Export to CSV
            return $this->checkInRecordsService->exportToCsv($records);
        } catch (\Exception $e) {
            Log::error('[CHECK_IN_RECORDS] Export failed', [
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
