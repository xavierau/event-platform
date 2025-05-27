<?php

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Http\RedirectResponse;

class CategoryController extends Controller
{
    public function __construct(protected CategoryService $categoryService)
    {
        // TODO: Add permissions middleware (e.g., $this->middleware('can:manage categories'));
    }

    public function index(Request $request): InertiaResponse
    {
        // TODO: Implement filtering/searching
        // Load parent relationship for display
        $categories = $this->categoryService->getAllCategories([], ['parent']);
        return Inertia::render('Admin/Categories/Index', [
            'pageTitle' => 'Categories',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Categories']
            ],
            'categories' => $categories,
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Admin/Categories/Create', [
            'pageTitle' => 'Create New Category',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Categories', 'href' => route('admin.categories.index')],
                ['text' => 'Create New Category']
            ],
            'categoriesForSelect' => $this->categoryService->getCategoriesForParentSelect(),
        ]);
    }

    public function store(CategoryData $categoryData): RedirectResponse
    {
        $this->categoryService->createCategory($categoryData);
        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    public function edit(Category $category): InertiaResponse
    {
        // Load media relationship and convert model to array to ensure accessors and casts are applied
        $category->load('media');
        $categoryArray = $category->toArray();

        // Create CategoryData manually to ensure proper data structure
        $categoryData = new CategoryData(
            name: $categoryArray['name'],
            slug: $categoryArray['slug'],
            parent_id: $categoryArray['parent_id'],
            is_active: $categoryArray['is_active'],
            id: $categoryArray['id'],
            uploaded_icon: null, // This is only for uploads
            media: $categoryArray['media'] ?? null
        );

        return Inertia::render('Admin/Categories/Edit', [
            'pageTitle' => 'Edit Category',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Categories', 'href' => route('admin.categories.index')],
                ['text' => 'Edit Category']
            ],
            'category' => $categoryData,
            'categoriesForSelect' => $this->categoryService->getCategoriesForParentSelect($category),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        // Debug: Log the validated request data
        Log::info('Category update validated data:', $request->validated());

        // Create CategoryData from validated request data
        $categoryData = CategoryData::from($request->validated());
        Log::info('CategoryData object:', $categoryData->toArray());

        $this->categoryService->updateCategory($category->id, $categoryData);
        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->categoryService->deleteCategory($category);
        return redirect()->route('admin.categories.index')->with('success', 'Category deleted successfully.');
    }
}
