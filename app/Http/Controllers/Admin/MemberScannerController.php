<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\MemberCheckInServiceInterface;
use App\Enums\RoleNameEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MemberScannerController extends Controller
{
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
        if (!$user->hasRole(RoleNameEnum::ADMIN)) {
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

        if (!$result->isSuccess()) {
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
        $request->validate([
            'qr_code' => 'required|string',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'device_identifier' => 'nullable|string|max:255',
        ]);

        $qrCode = $request->input('qr_code');
        $context = [
            'scanner_id' => Auth::id(),
            'location' => $request->input('location'),
            'notes' => $request->input('notes'),
            'device_identifier' => $request->input('device_identifier'),
        ];

        // Process the check-in
        $result = $this->checkInService->processCheckIn($qrCode, $context);

        if (!$result->isSuccess()) {
            return response()->json([
                'success' => false,
                'message' => $result->getMessage(),
            ], 400);
        }

        // Return 204 No Content for successful check-in (following existing pattern)
        return response()->noContent();
    }

    /**
     * Get check-in history for a specific member
     */
    public function getCheckInHistory(Request $request, User $member): JsonResponse
    {
        $limit = $request->input('limit', 50);
        
        $history = $this->checkInService->getCheckInHistory($member, $limit);

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }
}