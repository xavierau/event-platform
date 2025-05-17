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

        $translatableFields = [
            'name' => $data['name'],
        ];

        $nonTranslatableData = Arr::except($data, array_keys($translatableFields));

        // Ensure parent_id is null if not provided or empty, not an empty string
        if (array_key_exists('parent_id', $nonTranslatableData) && $nonTranslatableData['parent_id'] === '') {
            $nonTranslatableData['parent_id'] = null;
        }

        $category = Category::updateOrCreate(
            ['id' => $categoryData->id],
            $nonTranslatableData
        );

        foreach ($translatableFields as $key => $value) {
            if ($value !== null) {
                $category->setTranslations($key, $value);
            }
        }
        $category->save();

        return $category;
    }
}
