<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Membership\DataTransferObjects\MembershipPurchaseData;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MembershipController extends Controller
{
    public function __construct(private readonly MembershipService $membershipService) {}

    public function getMembershipLevels()
    {
        $levels = MembershipLevel::where('is_active', true)->orderBy('sort_order')->get();

        return response()->json($levels);
    }

    public function getMyMembership(Request $request)
    {
        $membership = $this->membershipService->checkMembershipStatus($request->user());

        return response()->json($membership);
    }

    public function purchaseMembership(Request $request)
    {
        $data = MembershipPurchaseData::from(
            array_merge($request->all(), ['user_id' => Auth::id()])
        );

        $result = $this->membershipService->purchaseMembership($request->user(), $data);

        return response()->json($result);
    }

    public function renewMembership(Request $request)
    {
        $result = $this->membershipService->renewMembership($request->user());

        return response()->json($result);
    }

    public function cancelMembership(Request $request)
    {
        $result = $this->membershipService->cancelMembership($request->user());

        return response()->json($result);
    }
}
