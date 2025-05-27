<?php

namespace App\Actions\Categories;

use App\DataTransferObjects\CategoryData;
use App\Models\Category;
use Illuminate\Support\Arr;

class UpsertCategoryAction
{
    public function execute(CategoryData $categoryData): Category
    {
        $data = $categoryData->toArray();

        // Remove uploaded files and flags from data array as they're handled separately
        $uploadedIcon = $data['uploaded_icon'] ?? null;
        $removeIcon = $data['remove_icon'] ?? false;
        unset($data['uploaded_icon'], $data['remove_icon']);

        // Ensure parent_id is null if not provided or empty, not an empty string
        if (array_key_exists('parent_id', $data) && $data['parent_id'] === '') {
            $data['parent_id'] = null;
        }

        $category = Category::updateOrCreate(
            ['id' => $categoryData->id],
            $data
        );

        // Handle icon removal
        if ($removeIcon) {
            $category->clearMediaCollection('icon');
        }

        // Handle icon upload
        if ($uploadedIcon) {
            // Clear existing icon first (since it's a single file collection)
            $category->clearMediaCollection('icon');

            // Add new icon
            $category->addMediaFromRequest('uploaded_icon')
                ->toMediaCollection('icon');
        }

        return $category;
    }
}
