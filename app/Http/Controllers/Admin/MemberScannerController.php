<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\MemberCheckInServiceInterface;
use App\Enums\RoleNameEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\CheckInLoggable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MemberScannerController extends Controller
{
    use CheckInLoggable;
    public function __construct(
        private readonly MemberCheckInServiceInterface $checkInService
    ) {
        // Middleware will be applied at the route level
    }

    /**
     * Display the member scanner page
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // Check authorization: only admins or users with organizer entity membership can access
        if (! $user->hasRole(RoleNameEnum::ADMIN)) {
            $userOrganizerIds = \App\Models\Organizer::whereHas('users', function ($subQuery) use ($user) {
                $subQuery->where('user_id', $user->id);
            })->pluck('organizers.id');

            if ($userOrganizerIds->isEmpty()) {
                abort(403, 'You do not have permission to access the member scanner.');
            }
        }

        return Inertia::render('Admin/MemberScanner/Index', [
            'roles' => [
                'ADMIN' => RoleNameEnum::ADMIN->value,
                'USER' => RoleNameEnum::USER->value,
            ],
            'user_role' => $user->roles->first()?->name,
            'pageTitle' => 'Member Scanner',
            'breadcrumbs' => [
                ['text' => 'Dashboard', 'href' => route('admin.dashboard')],
                ['text' => 'Member Scanner'],
            ],
        ]);
    }

    /**
     * Validate member QR code and return member information
     */
    public function validateMember(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string',
        ]);

        $qrCode = $request->input('qr_code');

        // Validate QR code and get member information
        $result = $this->checkInService->validateMemberQr($qrCode);

        if (! $result->isSuccess()) {
            return response()->json([
                'success' => false,
                'message' => $result->getMessage(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'member' => [
                'id' => $result->getMember()->id,
                'name' => $result->getMember()->name,
                'email' => $result->getMember()->email,
            ],
            'membership_data' => $result->getMembershipData(),
        ]);
    }

    /**
     * Process member check-in
     */
    public function checkIn(Request $request): \Illuminate\Http\Response
    {
        $this->logMethodEntry('MEMBER_CHECKIN', __METHOD__, [
            'has_qr_code' => $request->has('qr_code'),
            'location' => $request->input('location'),
            'device_identifier' => $request->input('device_identifier'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $request->validate([
            'qr_code' => 'required|string',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'device_identifier' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $qrCode = $request->input('qr_code');
        $context = [
            'scanner_id' => $user->id,
            'location' => $request->input('location'),
            'notes' => $request->input('notes'),
            'device_identifier' => $request->input('device_identifier'),
        ];

        $this->logAuthorization('MEMBER_CHECKIN', 'Scanner authenticated', [
            'scanner_id' => $user->id,
            'scanner_email' => $user->email,
            'scanner_roles' => $user->roles->pluck('name')->toArray(),
        ], true);

        $this->logBusinessLogic('MEMBER_CHECKIN', 'Check-in context prepared', [
            'location' => $context['location'],
            'has_notes' => !empty($context['notes']),
            'device_identifier' => $context['device_identifier'],
        ]);

        // Process the check-in
        $this->logBusinessLogic('MEMBER_CHECKIN', 'Calling check-in service', [
            'service' => get_class($this->checkInService),
        ]);

        $result = $this->checkInService->processCheckIn($qrCode, $context);

        if (! $result->isSuccess()) {
            $this->logValidation('MEMBER_CHECKIN', 'Check-in failed', [
                'error_message' => $result->getMessage(),
                'qr_code_length' => strlen($qrCode),
            ], false);

            return response()->json([
                'success' => false,
                'message' => $result->getMessage(),
            ], 400);
        }

        $this->logMethodExit('MEMBER_CHECKIN', __METHOD__, [
            'status' => 'success',
            'member_id' => $result->getMember()?->id,
        ]);

        // Return 204 No Content for successful check-in (following existing pattern)
        return response()->noContent();
    }

    /**
     * Get check-in history for a specific member
     */
    public function getCheckInHistory(Request $request, User $member): JsonResponse
    {
        $user = Auth::user();

        // Check authorization: only admins or users with organizer entity membership can access
        if (! $user->hasRole(RoleNameEnum::ADMIN)) {
            $userOrganizerIds = \App\Models\Organizer::whereHas('users', function ($subQuery) use ($user) {
                $subQuery->where('user_id', $user->id);
            })->pluck('organizers.id');

            if ($userOrganizerIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to access member check-in history.',
                ], 403);
            }
        }

        $limit = $request->input('limit', 50);

        $history = $this->checkInService->getCheckInHistory($member, $limit);

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }
}
