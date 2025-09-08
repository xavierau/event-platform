<?php

namespace App\Modules\PromotionalModal\Actions;

use App\Modules\PromotionalModal\DataTransferObjects\PromotionalModalData;
use App\Modules\PromotionalModal\Models\PromotionalModal;
use Illuminate\Support\Facades\DB;

class UpsertPromotionalModalAction
{
    /**
     * Create or update a promotional modal.
     */
    public function execute(PromotionalModalData $data, ?PromotionalModal $promotionalModal = null): PromotionalModal
    {
        return DB::transaction(function () use ($data, $promotionalModal) {
            $attributes = [
                'title' => $data->title,
                'content' => $data->content,
                'type' => $data->type,
                'pages' => $data->pages,
                'membership_levels' => $data->membership_levels,
                'user_segments' => $data->user_segments,
                'start_at' => $data->start_at,
                'end_at' => $data->end_at,
                'display_frequency' => $data->display_frequency,
                'cooldown_hours' => $data->cooldown_hours,
                'is_active' => $data->is_active,
                'priority' => $data->priority,
                'sort_order' => $data->sort_order,
                'button_text' => $data->button_text,
                'button_url' => $data->button_url,
                'is_dismissible' => $data->is_dismissible,
                'display_conditions' => $data->display_conditions,
            ];

            // Only include analytics fields for updates, not creation
            if ($promotionalModal) {
                $attributes['impressions_count'] = $data->impressions_count ?? $promotionalModal->impressions_count;
                $attributes['clicks_count'] = $data->clicks_count ?? $promotionalModal->clicks_count;
                $attributes['conversion_rate'] = $data->conversion_rate ?? $promotionalModal->conversion_rate;
            }

            if ($promotionalModal) {
                $promotionalModal->update($attributes);
            } else {
                $promotionalModal = PromotionalModal::create($attributes);
            }

            // Handle media uploads
            if ($data->banner_image) {
                $promotionalModal->clearMediaCollection('banner_image');
                $promotionalModal->addMediaFromRequest('banner_image')
                    ->toMediaCollection('banner_image');
            }

            if ($data->background_image) {
                $promotionalModal->clearMediaCollection('background_image');
                $promotionalModal->addMediaFromRequest('background_image')
                    ->toMediaCollection('background_image');
            }

            return $promotionalModal;
        });
    }
}