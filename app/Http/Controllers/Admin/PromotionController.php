<?php

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\PromotionData;
use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotionService
    ) {}

    /**
     * Display a listing of the promotions.
     */
    public function index(): Response
    {
        $promotions = $this->promotionService->getAllPromotions();

        return Inertia::render('Admin/Promotion/Index', [
            'promotions' => $promotions->map(function (Promotion $promotion) {
                return [
                    'id' => $promotion->id,
                    'title' => $promotion->title,
                    'subtitle' => $promotion->subtitle,
                    'url' => $promotion->url,
                    'banner' => $promotion->getFirstMediaUrl('banner'),
                    'is_active' => $promotion->is_active,
                    'starts_at' => $promotion->starts_at?->format('Y-m-d H:i:s'),
                    'ends_at' => $promotion->ends_at?->format('Y-m-d H:i:s'),
                    'sort_order' => $promotion->sort_order,
                ];
            }),
        ]);
    }

    /**
     * Show the form for creating a new promotion.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Promotion/Create');
    }

    /**
     * Store a newly created promotion in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = PromotionData::validateAndCreate($request->all());

        $this->promotionService->createPromotion($data);

        return redirect()->route('admin.promotions.index')
            ->with('success', 'Promotion created successfully.');
    }

    /**
     * Show the form for editing the specified promotion.
     */
    public function edit(Promotion $promotion): Response
    {
        return Inertia::render('Admin/Promotion/Edit', [
            'promotion' => [
                'id' => $promotion->id,
                'title' => $promotion->title,
                'subtitle' => $promotion->subtitle,
                'url' => $promotion->url,
                'banner' => $promotion->getFirstMediaUrl('banner'),
                'is_active' => $promotion->is_active,
                'starts_at' => $promotion->starts_at?->format('Y-m-d H:i:s'),
                'ends_at' => $promotion->ends_at?->format('Y-m-d H:i:s'),
                'sort_order' => $promotion->sort_order,
            ],
        ]);
    }

    /**
     * Update the specified promotion in storage.
     */
    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $data = PromotionData::validateAndCreate($request->all());

        $this->promotionService->updatePromotion($promotion, $data);

        return redirect()->route('admin.promotions.index')
            ->with('success', 'Promotion updated successfully.');
    }

    /**
     * Remove the specified promotion from storage.
     */
    public function destroy(Promotion $promotion): RedirectResponse
    {
        $this->promotionService->deletePromotion($promotion);

        return redirect()->route('admin.promotions.index')
            ->with('success', 'Promotion deleted successfully.');
    }
}
