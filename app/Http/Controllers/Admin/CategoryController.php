<?php

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\CategoryData;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
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
        // Explicitly convert model to array to ensure accessors and casts are applied
        $categoryArray = $category->toArray();

        return Inertia::render('Admin/Categories/Edit', [
            'pageTitle' => 'Edit Category',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Categories', 'href' => route('admin.categories.index')],
                ['text' => 'Edit Category']
            ],
            'category' => CategoryData::from($categoryArray), // Pass the array to from()
            'categoriesForSelect' => $this->categoryService->getCategoriesForParentSelect($category),
        ]);
    }

    public function update(CategoryData $categoryData, Category $category): RedirectResponse
    {
        $this->categoryService->updateCategory($category->id, $categoryData);
        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->categoryService->deleteCategory($category);
        return redirect()->route('admin.categories.index')->with('success', 'Category deleted successfully.');
    }
}
