<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Modules\Coupon\DataTransferObjects\CouponData;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CouponController extends Controller
{
    public function __construct(protected CouponService $couponService)
    {
        $this->authorizeResource(Coupon::class, 'coupon');
    }

    /**
     * Display a listing of coupons
     */
    public function index(Request $request): InertiaResponse
    {
        // TODO: Implement filtering/searching by organizer, status, type, etc.
        $coupons = Coupon::with('organizer')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/Coupons/Index', [
            'pageTitle' => 'Coupons',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Coupons']
            ],
            'coupons' => $coupons,
        ]);
    }

    /**
     * Show the form for creating a new coupon
     */
    public function create(): InertiaResponse
    {
        $organizers = Organizer::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Coupons/Create', [
            'pageTitle' => 'Create New Coupon',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Coupons', 'href' => route('admin.coupons.index')],
                ['text' => 'Create New Coupon']
            ],
            'organizers' => $organizers,
        ]);
    }

    /**
     * Store a newly created coupon
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organizer_id' => ['required', 'integer', 'exists:organizers,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'code' => ['required', 'string', 'unique:coupons,code'],
            'type' => ['required', 'string', 'in:single_use,multi_use'],
            'discount_value' => ['required', 'integer', 'min:1'],
            'discount_type' => ['required', 'string', 'in:fixed,percentage'],
            'max_issuance' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:valid_from'],
        ]);

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
                ['text' => $coupon->name]
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
        $organizers = Organizer::orderBy('name')->get(['id', 'name']);

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
                ['text' => 'Edit Coupon']
            ],
            'coupon' => $couponData,
            'organizers' => $organizers,
        ]);
    }

    /**
     * Update the specified coupon
     */
    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $validated = $request->validate([
            'organizer_id' => ['required', 'integer', 'exists:organizers,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'code' => ['required', 'string', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'type' => ['required', 'string', 'in:single_use,multi_use'],
            'discount_value' => ['required', 'integer', 'min:1'],
            'discount_type' => ['required', 'string', 'in:fixed,percentage'],
            'max_issuance' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:valid_from'],
        ]);

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

        if (!$deleted) {
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
        return Inertia::render('Admin/CouponScanner/Index', [
            'pageTitle' => 'Coupon Scanner',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Coupon Scanner']
            ],
        ]);
    }
}
