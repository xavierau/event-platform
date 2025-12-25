<?php

declare(strict_types=1);

namespace App\Modules\TemporaryRegistration\Actions;

use App\Modules\TemporaryRegistration\DataTransferObjects\TemporaryRegistrationPageData;
use App\Modules\TemporaryRegistration\Models\TemporaryRegistrationPage;
use Illuminate\Support\Facades\DB;

class UpdateTemporaryRegistrationPageAction
{
    /**
     * Update an existing temporary registration page.
     */
    public function execute(
        TemporaryRegistrationPage $page,
        TemporaryRegistrationPageData $data
    ): TemporaryRegistrationPage {
        return DB::transaction(function () use ($page, $data): TemporaryRegistrationPage {
            $page->update([
                'title' => $data->title,
                'description' => $data->description,
                'membership_level_id' => $data->membership_level_id,
                'use_slug' => $data->use_slug,
                'slug' => $data->slug,
                'expires_at' => $data->expires_at,
                'duration_days' => $data->duration_days,
                'max_registrations' => $data->max_registrations,
                'is_active' => $data->is_active,
                'metadata' => $data->metadata,
            ]);

            if ($data->banner_image !== null) {
                $page->clearMediaCollection('banner');
                $page->addMedia($data->banner_image)->toMediaCollection('banner');
            }

            return $page->fresh();
        });
    }
}
