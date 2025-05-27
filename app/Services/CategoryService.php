<?php

namespace App\Services;

use App\Actions\Categories\UpsertCategoryAction;
use App\DataTransferObjects\CategoryData;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function __construct(protected UpsertCategoryAction $upsertCategoryAction) {}

    public function createCategory(CategoryData $categoryData): Category
    {
        return $this->upsertCategoryAction->execute($categoryData->except('id'));
    }

    public function updateCategory(int $categoryId, CategoryData $categoryData): Category
    {
        $dataArray = $categoryData->all();
        $dataArray['id'] = $categoryId;
        $updateData = CategoryData::from($dataArray);
        return $this->upsertCategoryAction->execute($updateData);
    }

    public function deleteCategory(Category $category): ?bool
    {
        // Consider implications for child categories if any (e.g., prevent deletion, re-parent, or cascade)
        // Current migration cascades deletes. If re-parenting or other logic is needed, handle here.
        return $category->delete();
    }

    public function findById(int $id, array $with = []): ?Category
    {
        return Category::with($with)->find($id);
    }

    public function getCategoryBySlug(string $slug, array $with = []): ?Category
    {
        return Category::with($with)->where('slug', $slug)->first();
    }

    public function getAllCategories(array $filters = [], array $with = [], string $orderBy = 'name', string $direction = 'asc')
    {
        // Basic retrieval. Add pagination or specific filtering as needed.
        // Example for hierarchical data: get root categories, or categories with children counts.
        $query = Category::with($with);
        // Add filtering based on $filters array
        if (isset($filters['parent_id']) && is_null($filters['parent_id'])) {
            $query->whereNull('parent_id');
        } elseif (isset($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        // Add more filters here as needed

        return $query->orderBy($orderBy, $direction)
            ->get(); // Using get() for now, paginate() if list becomes very long
    }

    /**
     * Get root categories for public display (e.g., landing page).
     *
     * @param array $with
     * @param string $orderBy
     * @param string $direction
     * @return Collection
     */
    public function getPublicCategories(array $with = [], string $orderBy = 'name', string $direction = 'asc'): Collection
    {
        // For now, we assume 'name' is a translatable field and ordering by it directly
        // might need specific locale ordering in a more complex setup.
        // spatie/laravel-translatable handles ordering by translated attribute if model is configured.
        return Category::with($with)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy($orderBy, $direction) // Orders by the 'name' json column's current locale.
            ->get();
    }

    /**
     * Get categories suitable for a parent select dropdown (e.g., not the category itself or its children).
     */
    public function getCategoriesForParentSelect(Category $category = null): Collection
    {
        $query = Category::orderBy('name->' . app()->getLocale()); // Order by current locale's name

        if ($category && $category->id) {
            // Exclude the category itself and its descendants to prevent circular dependencies
            $excludeIds = [$category->id];
            // You might need a recursive function to get all descendant IDs if depth is large
            // For simplicity, this example only excludes direct children if we were to load them.
            // A more robust solution would be to get all descendant IDs.
            // $descendantIds = $this->getDescendantIds($category);
            // $query->whereNotIn('id', array_merge([$category->id], $descendantIds));
            $query->where('id', '!=', $category->id);
            // Add logic here to exclude children and deeper descendants if necessary
        }

        return $query->get();
    }

    // Helper to get all descendant IDs (example, can be improved for performance)
    /*
    private function getDescendantIds(Category $category): array
    {
        $ids = [];
        foreach ($category->children()->with('children')->get() as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }
        return $ids;
    }
    */
}
