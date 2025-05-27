<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use App\Services\EventService;
use App\Services\PromotionService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Route;

class HomeController extends Controller
{
    protected CategoryService $categoryService;
    protected EventService $eventService;
    protected PromotionService $promotionService;

    public function __construct(CategoryService $categoryService, EventService $eventService, PromotionService $promotionService)
    {
        $this->categoryService = $categoryService;
        $this->eventService = $eventService;
        $this->promotionService = $promotionService;
    }

    /**
     * Handle the incoming request.
     *
     * @return \Inertia\Response
     */
    public function __invoke(): Response
    {
        $categories = $this->categoryService->getPublicCategories();

        $formattedCategories = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name, // Accessor handles translation
                'slug' => $category->slug,
                'href' => route('events.index', ['category' => $category->slug]), // Assumes a named route
                'icon_url' => $category->getFirstMediaUrl('icon') ?: null, // Category icon URL from media library
            ];
        });

        // Fetch today's events specifically for the "Today" section
        $todayEvents = $this->eventService->getEventsToday(5);

        // Fetch upcoming events (next 30 days) for the broader upcoming section
        $upcomingEvents = $this->eventService->getUpcomingEventsForHomepage(15);

        // Fetch more events, excluding IDs from both today and upcoming events to avoid duplicates
        $excludeIds = array_merge(
            collect($todayEvents)->pluck('id')->all(),
            collect($upcomingEvents)->pluck('id')->all()
        );
        $moreEvents = $this->eventService->getMoreEventsForHomepage(15, $excludeIds);

        // Fetch active promotions
        $activePromotions = $this->promotionService->getActivePromotions()->map(function ($promotion) {
            return [
                'id' => $promotion->id,
                'title' => $promotion->getTranslation('title', app()->getLocale()),
                'subtitle' => $promotion->getTranslation('subtitle', app()->getLocale()),
                'banner' => $promotion->getFirstMediaUrl('banner'),
                'url' => $promotion->url,
                // type is not needed for the backend model but used in PromotionCarousel.vue
            ];
        });

        // Placeholder for other data to be passed to the landing page
        $featuredEvent = null; // Or fetch actual featured event

        return Inertia::render('Public/Home', [
            'initialCategories' => $formattedCategories,
            'featuredEvent' => $featuredEvent,
            'todayEvents' => $todayEvents, // Today's events specifically
            'upcomingEvents' => $upcomingEvents, // Broader upcoming events
            'moreEvents' => $moreEvents,
            'activePromotions' => $activePromotions,
            'canLogin' => Route::has('login'), // Example from original route
            'canRegister' => Route::has('register'), // Example from original route
        ]);
    }
}
