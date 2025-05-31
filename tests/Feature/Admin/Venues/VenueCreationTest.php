<?php

use App\Enums\RoleNameEnum;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // create a platform admin role
    $this->platformAdminRole = Role::create([
        'name' => RoleNameEnum::ADMIN->value,
    ]);

    // Create a platfor admin user for authentication
    $this->user = User::factory()->create();
    $this->user->assignRole(RoleNameEnum::ADMIN->value);


    // Create a country for the venue
    $this->country = Country::create([
        'name' => ['en' => 'United States', 'zh-TW' => '美國'],
        'iso_code_2' => 'US',
        'iso_code_3' => 'USA',
        'phone_code' => '+1',
        'is_active' => true,
    ]);
    Config::set('app.locale', 'en');
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
    $venueDataWithEmptyCity = [
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

    $response = $this->actingAs($this->user)
        ->post(route('admin.venues.store'), $venueDataWithEmptyCity);

    $response->assertRedirect(); // Should redirect back (to form or previous page)
    $response->assertSessionHasErrors(['city.en']); // Specifically check for city.en error
});
