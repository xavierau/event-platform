<?php

namespace App\Services;

use App\Actions\Promotion\UpsertPromotionAction;
use App\DataTransferObjects\PromotionData;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Collection;

class PromotionService
{
    public function __construct(
        private readonly UpsertPromotionAction $upsertPromotionAction
    ) {}

    /**
     * Create a new promotion.
     *
     * @param PromotionData $data
     * @return Promotion
     */
    public function createPromotion(PromotionData $data): Promotion
    {
        return $this->upsertPromotionAction->execute($data);
    }

    /**
     * Update an existing promotion.
     *
     * @param Promotion $promotion
     * @param PromotionData $data
     * @return Promotion
     */
    public function updatePromotion(Promotion $promotion, PromotionData $data): Promotion
    {
        return $this->upsertPromotionAction->execute($data, $promotion);
    }

    /**
     * Delete a promotion.
     *
     * @param Promotion $promotion
     * @return bool
     */
    public function deletePromotion(Promotion $promotion): bool
    {
        // Media will be automatically deleted due to cascade
        return $promotion->delete();
    }

    /**
     * Find a promotion by ID.
     *
     * @param int $id
     * @return Promotion|null
     */
    public function findPromotion(int $id): ?Promotion
    {
        return Promotion::find($id);
    }

    /**
     * Get all promotions ordered by sort order.
     *
     * @return Collection<int, Promotion>
     */
    public function getAllPromotions(): Collection
    {
        return Promotion::ordered()->get();
    }

    /**
     * Get all active promotions ordered by sort order.
     *
     * @return Collection<int, Promotion>
     */
    public function getActivePromotions(): Collection
    {
        return Promotion::active()->ordered()->get();
    }
}
