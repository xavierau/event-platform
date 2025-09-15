<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Models\Organizer;
use App\Modules\Coupon\DataTransferObjects\CouponData;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CouponController extends Controller
{
    public function __construct(protected CouponService $couponService)
    {
        $this->authorizeResource(Coupon::class, 'coupon');
    }

    /**
     * Get organizers based on user permissions
     */
    private function getFilteredOrganizers(): \Illuminate\Database\Eloquent\Collection
    {
        $user = auth()->user();
        $isPlatformAdmin = $user->hasRole(RoleNameEnum::ADMIN);

        $organizersQuery = Organizer::orderBy('name');
        if (! $isPlatformAdmin) {
            $userOrganizerIds = $user->organizers->pluck('id');
            $organizersQuery->whereIn('id', $userOrganizerIds);
        }

        return $organizersQuery->get(['id', 'name']);
    }

    /**
     * Filter coupons query based on user permissions
     */
    private function filterCouponsForUser($query)
    {
        $user = auth()->user();
        $isPlatformAdmin = $user->hasRole(RoleNameEnum::ADMIN);

        if (! $isPlatformAdmin) {
            $userOrganizerIds = $user->organizers->pluck('id');
            $query->whereIn('organizer_id', $userOrganizerIds);
        }

        return $query;
    }

    /**
     * Display a listing of coupons
     */
    public function index(Request $request): InertiaResponse
    {
        $organizers = $this->getFilteredOrganizers();

        $couponsQuery = $this->filterCouponsForUser(Coupon::with('organizer'));

        $coupons = $couponsQuery
            ->when($request->input('organizer_id'), function ($query) use ($request) {
                return $query->where('organizer_id', $request->input('organizer_id'));
            })
            ->when($request->input('type'), function ($query) use ($request) {
                return $query->where('type', $request->input('type'));
            })
            ->when($request->input('search'), function ($query) use ($request) {
                return $query->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('code', 'like', '%'.$request->input('search').'%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate();

        return Inertia::render('Admin/Coupons/Index', [
            'pageTitle' => 'Coupons',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Coupons'],
            ],
            'coupons' => $coupons,
            'organizers' => $organizers,
        ]);
    }

    /**
     * Show the form for creating a new coupon
     */
    public function create(): InertiaResponse
    {
        $organizers = $this->getFilteredOrganizers();

        return Inertia::render('Admin/Coupons/Create', [
            'pageTitle' => 'Create New Coupon',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Coupons', 'href' => route('admin.coupons.index')],
                ['text' => 'Create New Coupon'],
            ],
            'organizers' => $organizers,
        ]);
    }

    /**
     * Store a newly created coupon
     */
    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Log::info('Coupon creation validated data:', $validated);

        $couponData = CouponData::from($validated);
        Log::info('CouponData object:', $couponData->toArray());

        $this->couponService->upsertCoupon($couponData);

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon created successfully.');
    }

    /**
     * Display the specified coupon
     */
    public function show(Coupon $coupon): InertiaResponse
    {
        $coupon->load(['organizer', 'userCoupons.user', 'userCoupons.usageLogs']);
        $statistics = $this->couponService->getCouponStatistics($coupon->id);

        return Inertia::render('Admin/Coupons/Show', [
            'pageTitle' => "Coupon: {$coupon->name}",
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Coupons', 'href' => route('admin.coupons.index')],
                ['text' => $coupon->name],
            ],
            'coupon' => $coupon,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Show the form for editing the specified coupon
     */
    public function edit(Coupon $coupon): InertiaResponse
    {
        $organizers = $this->getFilteredOrganizers();

        // Convert model to array to ensure proper data structure
        $couponArray = $coupon->toArray();

        // Create CouponData for consistency
        $couponData = new CouponData(
            organizer_id: $couponArray['organizer_id'],
            name: $couponArray['name'],
            description: $couponArray['description'],
            code: $couponArray['code'],
            type: $coupon->type, // Use the enum directly
            discount_value: $couponArray['discount_value'],
            discount_type: $couponArray['discount_type'],
            max_issuance: $couponArray['max_issuance'],
            valid_from: $couponArray['valid_from'],
            expires_at: $couponArray['expires_at'],
            id: $couponArray['id']
        );

        return Inertia::render('Admin/Coupons/Edit', [
            'pageTitle' => 'Edit Coupon',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Coupons', 'href' => route('admin.coupons.index')],
                ['text' => 'Edit Coupon'],
            ],
            'coupon' => $couponData,
            'organizers' => $organizers,
        ]);
    }

    /**
     * Update the specified coupon
     */
    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $validated = $request->validated();

        Log::info('Coupon update validated data:', $validated);

        // Add the ID for update
        $validated['id'] = $coupon->id;

        $couponData = CouponData::from($validated);
        Log::info('CouponData object for update:', $couponData->toArray());

        $this->couponService->upsertCoupon($couponData);

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon updated successfully.');
    }

    /**
     * Remove the specified coupon
     */
    public function destroy(Coupon $coupon): RedirectResponse
    {
        $deleted = $this->couponService->deleteCoupon($coupon->id);

        if (! $deleted) {
            return redirect()->route('admin.coupons.index')
                ->with('error', 'Failed to delete coupon.');
        }

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon deleted successfully.');
    }

    /**
     * Show the QR code scanner page
     */
    public function scanner(): InertiaResponse
    {
        return Inertia::render('Admin/Coupons/Scanner', [
            'pageTitle' => 'Coupon Scanner',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Coupon Scanner'],
            ],
        ]);
    }
}
