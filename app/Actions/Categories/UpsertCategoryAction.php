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

        // Ensure parent_id is null if not provided or empty, not an empty string
        if (array_key_exists('parent_id', $data) && $data['parent_id'] === '') {
            $data['parent_id'] = null;
        }

        $category = Category::updateOrCreate(
            ['id' => $categoryData->id],
            $data
        );

        return $category;
    }
}
