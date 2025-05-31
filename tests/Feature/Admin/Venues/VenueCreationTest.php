<?php

use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a user for authentication
    $this->user = User::factory()->create();

    // Create a country for the venue
    $this->country = Country::create([
        'name' => ['en' => 'United States', 'zh-TW' => '美國'],
        'iso_code_2' => 'US',
        'iso_code_3' => 'USA',
        'phone_code' => '+1',
        'is_active' => true,
    ]);
});

it('can create a venue with the problematic data from frontend', function () {
    // This is the exact data that was failing from the frontend
    $venueData = [
        "name" => ["en" => "new venue", "zh-TW" => "new venue", "zh-CN" => "new venue"],
        "slug" => "new-venue",
        "description" => ["en" => "<p>testing</p>", "zh-TW" => "<p>testing</p>", "zh-CN" => "<p>testing</p>"],
        "address_line_1" => ["en" => "address 1", "zh-TW" => "address 1", "zh-CN" => "address 1"],
        "address_line_2" => ["en" => "", "zh-TW" => "", "zh-CN" => ""],
        "city" => ["en" => "New York", "zh-TW" => "紐約", "zh-CN" => "纽约"], // Fixed: added actual city values
        "postal_code" => "10001",
        "country_id" => $this->country->id,
        "state_id" => null,
        "latitude" => null,
        "longitude" => null,
        "contact_email" => "",
        "contact_phone" => "",
        "website_url" => "",
        "seating_capacity" => null,
        "is_active" => true,
        "organizer_id" => null
    ];

    $response = $this->actingAs($this->user)
        ->post(route('admin.venues.store'), $venueData);

    $response->assertRedirect(route('admin.venues.index'));
    $response->assertSessionHas('success', 'Venue created successfully.');

    $this->assertDatabaseHas('venues', [
        'slug' => 'new-venue',
        'country_id' => $this->country->id,
    ]);
});

it('fails validation when city is empty for all locales', function () {
    // This tests the original failing scenario
    $venueData = [
        "name" => ["en" => "new venue", "zh-TW" => "new venue", "zh-CN" => "new venue"],
        "slug" => "new-venue-2",
        "description" => ["en" => "<p>testing</p>", "zh-TW" => "<p>testing</p>", "zh-CN" => "<p>testing</p>"],
        "address_line_1" => ["en" => "address 1", "zh-TW" => "address 1", "zh-CN" => "address 1"],
        "address_line_2" => ["en" => "", "zh-TW" => "", "zh-CN" => ""],
        "city" => ["en" => "", "zh-TW" => "", "zh-CN" => ""], // This should fail validation
        "postal_code" => "",
        "country_id" => $this->country->id,
        "state_id" => null,
        "latitude" => null,
        "longitude" => null,
        "contact_email" => "",
        "contact_phone" => "",
        "website_url" => "",
        "seating_capacity" => null,
        "is_active" => true,
        "organizer_id" => null
    ];

    try {
        $response = $this->actingAs($this->user)
            ->post(route('admin.venues.store'), $venueData);

        // Debug the response
        dump('Response status: ' . $response->getStatusCode());
        dump('Has errors: ' . ($response->getSession()->has('errors') ? 'yes' : 'no'));
        dump('Redirect location: ' . $response->headers->get('Location'));
        if ($response->getSession()->has('success')) {
            dump('Success message: ' . $response->getSession()->get('success'));
        }
        if ($response->getSession()->has('errors')) {
            dump('Errors: ' . json_encode($response->getSession()->get('errors')));
        }

        // Check if venue was created
        $venueExists = \App\Models\Venue::where('slug', 'new-venue-2')->exists();
        dump('Venue created: ' . ($venueExists ? 'yes' : 'no'));

        $response->assertRedirect(); // Should redirect back
        $response->assertSessionHasErrors(); // Should have validation errors
    } catch (\Exception $e) {
        dump('Exception: ' . $e->getMessage());
        throw $e;
    }
});
