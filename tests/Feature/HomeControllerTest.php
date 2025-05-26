<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure necessary locales are available for translatable fields
        Config::set('app.available_locales', ['en' => 'English', 'zh-TW' => 'Traditional Chinese']);
        Config::set('app.locale', 'en');
    }

    private function createTestEvent(array $occurrenceData = []): Event
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);

        // Use existing venue or create one with existing country to avoid constraint violations
        $venue = Venue::first() ?: Venue::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Test Event'],
            'slug' => ['en' => 'test-event'],
            'description' => ['en' => 'Event Description'],
            'short_summary' => ['en' => 'Short Summary'],
            'event_status' => 'published',
            'visibility' => 'public',
            'is_featured' => false,
            'cancellation_policy' => ['en' => 'Cancellation Policy'],
            'meta_title' => ['en' => 'Meta Title'],
            'meta_description' => ['en' => 'Meta Description'],
            'meta_keywords' => ['en' => 'keywords, test'],
        ]);

        $defaultOccurrenceData = [
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'name' => ['en' => 'Test Occurrence'],
            'status' => 'scheduled',
            'timezone' => 'UTC',
        ];

        EventOccurrence::factory()->create(array_merge($defaultOccurrenceData, $occurrenceData));

        return $event;
    }

    public function test_home_page_loads_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn($page) => $page->component('Public/Home'));
    }

    public function test_home_page_returns_separate_today_and_upcoming_events(): void
    {
        $todayStart = now()->utc()->startOfDay();
        $tomorrow = now()->utc()->addDay();

        // Create event happening today
        $this->createTestEvent([
            'start_at_utc' => $todayStart->copy()->addHours(10),
            'end_at_utc' => $todayStart->copy()->addHours(12),
        ]);

        // Create event happening tomorrow (upcoming)
        $this->createTestEvent([
            'start_at_utc' => $tomorrow->copy()->addHours(10),
            'end_at_utc' => $tomorrow->copy()->addHours(12),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 1)
                ->has('upcomingEvents', 2) // Both today and tomorrow events appear in upcoming
                ->has('moreEvents')
                ->has('initialCategories')
        );
    }

    public function test_home_page_shows_empty_today_events_when_none_exist(): void
    {
        // Create only future events (not today)
        $nextWeek = now()->utc()->addWeek();

        $this->createTestEvent([
            'start_at_utc' => $nextWeek->copy()->addHours(10),
            'end_at_utc' => $nextWeek->copy()->addHours(12),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 0) // No events today
                ->has('upcomingEvents', 1) // Future event appears in upcoming
        );
    }

    public function test_home_page_excludes_duplicate_events_from_more_events(): void
    {
        $todayStart = now()->utc()->startOfDay();
        $nextWeek = now()->utc()->addWeek();

        // Create event happening today (will appear in both today and upcoming)
        $this->createTestEvent([
            'start_at_utc' => $todayStart->copy()->addHours(10),
            'end_at_utc' => $todayStart->copy()->addHours(12),
        ]);

        // Create event happening next week (will appear in upcoming)
        $this->createTestEvent([
            'start_at_utc' => $nextWeek->copy()->addHours(10),
            'end_at_utc' => $nextWeek->copy()->addHours(12),
        ]);

        // Create event happening far in the future (should appear in more events)
        $farFuture = now()->utc()->addMonths(2);
        $this->createTestEvent([
            'start_at_utc' => $farFuture->copy()->addHours(10),
            'end_at_utc' => $farFuture->copy()->addHours(12),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 1)
                ->has('upcomingEvents', 2) // Today + next week events
                ->has('moreEvents', 1) // Only the far future event (others excluded as duplicates)
        );
    }

    public function test_home_page_only_includes_published_events(): void
    {
        $todayStart = now()->utc()->startOfDay();
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
        $venue = Venue::factory()->create();

        // Create published event
        $publishedEvent = Event::factory()->create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Published Event'],
            'event_status' => 'published',
            'visibility' => 'public',
        ]);

        EventOccurrence::factory()->create([
            'event_id' => $publishedEvent->id,
            'venue_id' => $venue->id,
            'start_at_utc' => $todayStart->copy()->addHours(10),
            'end_at_utc' => $todayStart->copy()->addHours(12),
            'status' => 'scheduled',
        ]);

        // Create draft event (should not appear)
        $draftEvent = Event::factory()->create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Draft Event'],
            'event_status' => 'draft',
            'visibility' => 'public',
        ]);

        EventOccurrence::factory()->create([
            'event_id' => $draftEvent->id,
            'venue_id' => $venue->id,
            'start_at_utc' => $todayStart->copy()->addHours(14),
            'end_at_utc' => $todayStart->copy()->addHours(16),
            'status' => 'scheduled',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 1) // Only published event
                ->has('upcomingEvents', 1) // Only published event
        );
    }

    public function test_home_page_only_includes_scheduled_occurrences(): void
    {
        $todayStart = now()->utc()->startOfDay();
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
        $venue = Venue::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Test Event'],
            'event_status' => 'published',
            'visibility' => 'public',
        ]);

        // Create scheduled occurrence (should appear)
        EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => $todayStart->copy()->addHours(10),
            'end_at_utc' => $todayStart->copy()->addHours(12),
            'status' => 'scheduled',
        ]);

        // Create cancelled occurrence (should not appear)
        EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => $todayStart->copy()->addHours(14),
            'end_at_utc' => $todayStart->copy()->addHours(16),
            'status' => 'cancelled',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 1) // Only scheduled occurrence
                ->has('upcomingEvents', 1) // Only scheduled occurrence
        );
    }

    public function test_home_page_includes_categories(): void
    {
        // Create some categories
        Category::factory()->create(['name' => ['en' => 'Music'], 'slug' => 'music']);
        Category::factory()->create(['name' => ['en' => 'Theater'], 'slug' => 'theater']);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('initialCategories', 2)
                ->where('initialCategories.0.name', 'Music')
                ->where('initialCategories.1.name', 'Theater')
        );
    }

    public function test_home_page_event_data_structure(): void
    {
        $todayStart = now()->utc()->startOfDay();

        $this->createTestEvent([
            'start_at_utc' => $todayStart->copy()->addHours(10),
            'end_at_utc' => $todayStart->copy()->addHours(12),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has(
                    'todayEvents.0',
                    fn($event) => $event
                        ->has('id')
                        ->has('name')
                        ->has('href')
                        ->has('image_url')
                        ->has('price_from')
                        ->has('price_to')
                        ->has('currency')
                        ->has('start_time')
                        ->has('venue_name')
                        ->has('category_name')
                )
                ->has(
                    'upcomingEvents.0',
                    fn($event) => $event
                        ->has('id')
                        ->has('name')
                        ->has('href')
                        ->has('image_url')
                        ->has('price_from')
                        ->has('price_to')
                        ->has('currency')
                        ->has('date_short')
                        ->has('category_name')
                )
        );
    }

    public function test_home_page_respects_event_limits(): void
    {
        $todayStart = now()->utc()->startOfDay();
        $tomorrow = now()->utc()->addDay();

        // Create more events than the limits
        for ($i = 0; $i < 10; $i++) {
            // Today events
            $this->createTestEvent([
                'start_at_utc' => $todayStart->copy()->addHours(9 + $i),
                'end_at_utc' => $todayStart->copy()->addHours(10 + $i),
            ]);

            // Upcoming events
            $this->createTestEvent([
                'start_at_utc' => $tomorrow->copy()->addHours(9 + $i),
                'end_at_utc' => $tomorrow->copy()->addHours(10 + $i),
            ]);
        }

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 5) // Limited to 5 as per HomeController
                ->has('upcomingEvents', 15) // Limited to 15 as per HomeController
        );
    }

    public function test_home_page_handles_no_events_gracefully(): void
    {
        // Don't create any events
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 0)
                ->has('upcomingEvents', 0)
                ->has('moreEvents', 0)
        );
    }
}
