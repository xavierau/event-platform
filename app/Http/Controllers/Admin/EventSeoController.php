<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\EventSeoData;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventSeo\StoreEventSeoRequest;
use App\Http\Requests\EventSeo\UpdateEventSeoRequest;
use App\Models\Event;
use App\Services\EventSeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EventSeoController extends Controller
{
    public function __construct(private EventSeoService $eventSeoService)
    {
        // Authorization will be handled in the request classes based on event ownership
    }

    /**
     * Display the SEO settings for an event
     */
    public function show(Event $event): InertiaResponse
    {
        // Ensure user can manage this event
        $this->authorize('update', $event);

        $eventSeo = $event->seo;

        return Inertia::render('Admin/Events/Seo/Show', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
            ],
            'eventSeo' => $eventSeo ? EventSeoData::fromModel($eventSeo)->toArray() : null,
            'availableLocales' => config('app.available_locales', ['en' => 'English', 'zh-TW' => 'Traditional Chinese']),
        ]);
    }

    /**
     * Show the form for editing SEO settings
     */
    public function edit(Event $event): InertiaResponse
    {
        // Ensure user can manage this event
        $this->authorize('update', $event);

        $eventSeo = $event->seo;

        return Inertia::render('Admin/Events/Seo/Edit', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'short_summary' => $event->short_summary,
                'description' => $event->description,
            ],
            'eventSeo' => $eventSeo ? EventSeoData::fromModel($eventSeo)->toArray() : null,
            'availableLocales' => config('app.available_locales', ['en' => 'English', 'zh-TW' => 'Traditional Chinese']),
        ]);
    }

    /**
     * Store SEO settings for an event
     */
    public function store(StoreEventSeoRequest $request, Event $event): RedirectResponse
    {
        try {
            $data = EventSeoData::from($request->validated());
            $eventSeo = $this->eventSeoService->createEventSeo($data);

            return redirect()
                ->route('admin.events.seo.show', $event)
                ->with('success', 'SEO settings created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create SEO settings: '.$e->getMessage());
        }
    }

    /**
     * Update SEO settings for an event
     */
    public function update(UpdateEventSeoRequest $request, Event $event): RedirectResponse
    {
        try {
            $eventSeo = $event->seo;

            if (! $eventSeo) {
                // Create new SEO settings if they don't exist
                $data = EventSeoData::from(array_merge($request->validated(), ['event_id' => $event->id]));
                $this->eventSeoService->createEventSeo($data);
            } else {
                // Update existing SEO settings
                $data = EventSeoData::from(array_merge($request->validated(), ['event_id' => $event->id]));
                $this->eventSeoService->updateEventSeo($eventSeo, $data);
            }

            return redirect()
                ->route('admin.events.seo.show', $event)
                ->with('success', 'SEO settings updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update SEO settings: '.$e->getMessage());
        }
    }

    /**
     * Remove SEO settings for an event
     */
    public function destroy(Event $event): RedirectResponse
    {
        // Ensure user can manage this event
        $this->authorize('update', $event);

        try {
            $eventSeo = $event->seo;

            if ($eventSeo) {
                $this->eventSeoService->deleteEventSeo($eventSeo);
            }

            return redirect()
                ->route('admin.events.show', $event)
                ->with('success', 'SEO settings removed successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to remove SEO settings: '.$e->getMessage());
        }
    }

    /**
     * Get SEO settings as JSON (for AJAX requests)
     */
    public function getSeoData(Event $event): JsonResponse
    {
        // Ensure user can view this event
        $this->authorize('view', $event);

        $eventSeo = $event->seo;

        return response()->json([
            'eventSeo' => $eventSeo ? EventSeoData::fromModel($eventSeo)->toArray() : null,
        ]);
    }

    /**
     * Generate meta tags for an event (for preview)
     */
    public function preview(Event $event, Request $request): JsonResponse
    {
        // Ensure user can view this event
        $this->authorize('view', $event);

        $locale = $request->input('locale', app()->getLocale());
        $metaTags = $this->eventSeoService->generateMetaTags($event, $locale);

        return response()->json([
            'metaTags' => $metaTags,
            'locale' => $locale,
        ]);
    }

    /**
     * Validate character limits for SEO fields
     */
    public function validateLimits(Request $request): JsonResponse
    {
        try {
            $data = EventSeoData::from($request->all());
            $errors = $this->eventSeoService->validateCharacterLimits($data);

            return response()->json([
                'valid' => empty($errors),
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 422);
        }
    }
}
