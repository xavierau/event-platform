<?php

namespace App\Actions\Promotion;

use App\DataTransferObjects\PromotionData;
use App\Models\Promotion;

class UpsertPromotionAction
{
    /**
     * Execute the action to create or update a promotion.
     *
     * @param PromotionData $data
     * @param Promotion|null $promotion
     * @return Promotion
     */
    public function execute(PromotionData $data, ?Promotion $promotion = null): Promotion
    {
        $promotion = $promotion ?? new Promotion();

        $promotion->fill([
            'title' => $data->title,
            'subtitle' => $data->subtitle,
            'url' => $data->url,
            'is_active' => $data->is_active,
            'starts_at' => $data->starts_at,
            'ends_at' => $data->ends_at,
            'sort_order' => $data->sort_order,
        ]);

        $promotion->save();

        // Handle banner image upload
        if ($data->uploaded_banner_image) {
            // Clear existing banner and add new one
            $promotion->clearMediaCollection('banner');
            $promotion->addMedia($data->uploaded_banner_image)
                ->toMediaCollection('banner');
        }

        return $promotion;
    }
}
