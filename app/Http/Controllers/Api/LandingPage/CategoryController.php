<?php

namespace App\Http\Controllers\Api\LandingPage;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\CategoryResource; // Assuming a CategoryResource might be useful, or we format manually

class CategoryController extends Controller
{
    protected CategoryService $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(): JsonResponse
    {
        $categories = $this->categoryService->getPublicCategories();

        $formattedCategories = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name, // Accessor handles translation
                'slug' => $category->slug,
                'href' => route('events.index', ['category' => $category->slug]), // Assumes a named route for event listings by category
                // 'icon' is handled by the frontend as per Home.vue
            ];
        });

        // Optionally, add the "All Events" category manually if it's a fixed UI element not from DB
        // $formattedCategories->push([
        //     'id' => 'all-events',
        //     'name' => __('All Events'), // Assuming you have a lang file for this
        //     'slug' => null, // Or a specific slug/identifier if needed
        //     'href' => route('events.index'),
        // ]);

        return response()->json(['data' => $formattedCategories]);
    }
}
