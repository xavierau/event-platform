<?php

namespace Tests\Unit\Domains\EventManagement\Models;

use App\Models\EventOccurrence;
use App\Models\Venue; // Assuming Venue might be needed for context, can be removed if not.
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase; // Or WithFaker if preferred
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Set a default locale for testing consistency
    app()->setLocale('en');
});

it('returns correct public data structure including formatted full_date_time', function () {
    // Arrange
    $now = Carbon::parse('2024-07-15 10:00:00');
    Carbon::setTestNow($now);

    $occurrence = EventOccurrence::factory()->create([
        'start_at_utc' => $now->copy()->utc(),
        'timezone' => 'Asia/Taipei', // GMT+8
        'name' => ['en' => 'Test Event Name'], // Example translatable name
        'description' => ['en' => 'Test Event Description'], // Example translatable description
        // Add other necessary fields for EventOccurrence factory if they cause issues
        // For instance, if venue_id is required and not nullable:
        // 'venue_id' => Venue::factory()->create()->id,
    ]);

    // Act
    $publicData = $occurrence->getPublicData();

    // Assert
    expect($publicData)->toBeArray();
    expect($publicData)->toHaveKeys(['id', 'name', 'date_short', 'full_date_time', 'status_tag', 'venue_name', 'venue_address', 'tickets']);

    // Verify full_date_time format
    // Taipei is UTC+8, so 10:00 UTC is 18:00 Taipei time.
    // 2024-07-15 is a Monday.
    $expectedDateTime = '2024.07.15 Monday 18:00';
    expect($publicData['full_date_time'])->toBe($expectedDateTime);

    // Check that 'tickets' is an Eloquent Collection (or at least an iterable, depending on how specific you want to be)
    expect($publicData['tickets'])->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);

    // Check other potentially important fields
    expect($publicData['name'])->toBe('Test Event Name'); // Assuming 'en' locale
    expect($publicData['date_short'])->toBe($now->copy()->setTimezone('Asia/Taipei')->format('m.d')); // e.g., '07.15'

    // Clean up Carbon::setTestNow() if other tests might be affected, though Pest usually isolates tests.
    Carbon::setTestNow();
});

it('returns empty string for full_date_time when start_at_utc is null', function () {
    // Arrange
    $occurrence = EventOccurrence::factory()->create([
        'start_at_utc' => null,
        'timezone' => 'Asia/Taipei',
        'name' => ['en' => 'Event Without Date'],
        // 'venue_id' => Venue::factory()->create()->id, // if required
    ]);

    // Act
    $publicData = $occurrence->getPublicData();

    // Assert
    expect($publicData['full_date_time'])->toBe('');
    expect($publicData['date_short'])->toBeNull(); // Or appropriate expectation if format() on null Carbon returns something else or throws error
    // Check that 'tickets' is an Eloquent Collection
    expect($publicData['tickets'])->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

// You might need to create a basic factory for EventOccurrence if one doesn't exist
// Example: database/factories/EventOccurrenceFactory.php
// if (!class_exists('Database\\Factories\\EventOccurrenceFactory')) {
//     // This is a placeholder. You should create a proper factory.
//
//     EventOccurrence::factory()->define(function (Faker $faker) {
//         return [
//             'event_id' => \App\Models\Event::factory(), // Assuming Event model and factory exist
//             'venue_id' => null, // Or \App\Models\Venue::factory(),
//             'name' => ['en' => $faker->sentence],
//             'description' => ['en' => $faker->paragraph],
//             'start_at_utc' => $faker->dateTimeThisMonth()->setTimezone('UTC'),
//             'end_at_utc' => $faker->dateTimeThisMonth()->setTimezone('UTC'),
//             'timezone' => $faker->timezone,
//             'status' => 'scheduled',
//         ];
//     });
// }
