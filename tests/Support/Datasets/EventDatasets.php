<?php

/**
 * Dataset for valid event statuses
 */
dataset('event_statuses', [
    'draft',
    'pending_approval',
    'published',
    'cancelled',
    'completed',
    'past'
]);

/**
 * Dataset for published event statuses (statuses that should be visible to public)
 */
dataset('published_event_statuses', [
    'published',
    'completed'
]);

/**
 * Dataset for draft event statuses (statuses that should not be visible to public)
 */
dataset('draft_event_statuses', [
    'draft',
    'pending_approval',
    'cancelled'
]);

/**
 * Dataset for event visibility options
 */
dataset('event_visibilities', [
    'public',
    'private',
    'unlisted'
]);

/**
 * Dataset for occurrence statuses
 */
dataset('occurrence_statuses', [
    'upcoming',
    'ongoing',
    'completed',
    'cancelled'
]);

/**
 * Dataset for invalid event data (for validation testing)
 */
dataset('invalid_event_data', [
    'empty name' => [
        'data' => ['name' => ''],
        'expected_errors' => ['name']
    ],
    'missing category' => [
        'data' => ['category_id' => null],
        'expected_errors' => ['category_id']
    ],
    'invalid status' => [
        'data' => ['event_status' => 'invalid_status'],
        'expected_errors' => ['event_status']
    ],
    'invalid visibility' => [
        'data' => ['visibility' => 'invalid_visibility'],
        'expected_errors' => ['visibility']
    ],
]);

/**
 * Dataset for valid translatable event data
 */
dataset('translatable_event_data', [
    'english only' => [
        'name' => ['en' => 'English Event Name'],
        'description' => ['en' => 'English description'],
        'expected_locale' => 'en'
    ],
    'chinese traditional only' => [
        'name' => ['zh-TW' => '中文活動名稱'],
        'description' => ['zh-TW' => '中文描述'],
        'expected_locale' => 'zh-TW'
    ],
    'bilingual' => [
        'name' => [
            'en' => 'English Event Name',
            'zh-TW' => '中文活動名稱'
        ],
        'description' => [
            'en' => 'English description',
            'zh-TW' => '中文描述'
        ],
        'expected_locale' => 'en'
    ],
]);

/**
 * Dataset for date ranges for event filtering
 */
dataset('date_ranges', function () {
    return [
        'today only' => [
            'start' => now()->startOfDay(),
            'end' => now()->endOfDay(),
            'description' => 'Events happening today'
        ],
        'this week' => [
            'start' => now()->startOfWeek(),
            'end' => now()->endOfWeek(),
            'description' => 'Events happening this week'
        ],
        'next 30 days' => [
            'start' => now(),
            'end' => now()->addDays(30),
            'description' => 'Events in the next 30 days'
        ],
        'past 7 days' => [
            'start' => now()->subDays(7),
            'end' => now(),
            'description' => 'Events in the past 7 days'
        ],
    ];
});

/**
 * Dataset for price ranges
 */
dataset('price_ranges', [
    'free events' => [
        'min_price' => 0,
        'max_price' => 0,
        'description' => 'Free events'
    ],
    'budget events' => [
        'min_price' => 1,
        'max_price' => 50,
        'description' => 'Budget-friendly events'
    ],
    'premium events' => [
        'min_price' => 100,
        'max_price' => 500,
        'description' => 'Premium events'
    ],
]);

/**
 * Dataset for pagination parameters
 */
dataset('pagination_params', [
    'default pagination' => [
        'page' => 1,
        'per_page' => 15,
        'expected_count' => 15
    ],
    'second page' => [
        'page' => 2,
        'per_page' => 10,
        'expected_count' => 10
    ],
    'large page size' => [
        'page' => 1,
        'per_page' => 50,
        'expected_count' => 50
    ],
]);
