<?php

use App\DataTransferObjects\VenueData;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelData\Exceptions\CannotCreateData;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->country = Country::create([
        'name' => ['en' => 'United States', 'zh-TW' => '美國'],
        'iso_code_2' => 'US',
        'iso_code_3' => 'USA',
        'phone_code' => '+1',
        'is_active' => true,
    ]);
});

it('validates basic required fields', function () {
    $data = [
        // Missing required fields like name, slug, etc.
        "country_id" => $this->country->id,
    ];

    expect(fn() => VenueData::from($data))
        ->toThrow(CannotCreateData::class);
});

it('debugs validation with empty city values', function () {
    $data = [
        "name" => ["en" => "new venue", "zh-TW" => "new venue", "zh-CN" => "new venue"],
        "slug" => "new-venue",
        "address_line_1" => ["en" => "address 1", "zh-TW" => "address 1", "zh-CN" => "address 1"],
        "city" => ["en" => "", "zh-TW" => "", "zh-CN" => ""], // All empty
        "country_id" => $this->country->id,
    ];

    try {
        $venueData = VenueData::from($data);
        dump('VenueData created successfully');
        dump('City value: ' . json_encode($venueData->city));
    } catch (CannotCreateData $e) {
        dump('Validation failed as expected: ' . $e->getMessage());
        throw $e; // Re-throw to make test pass
    } catch (\Exception $e) {
        dump('Unexpected exception: ' . $e->getMessage());
        throw $e;
    }
});

it('validates city field correctly when all locales are empty strings', function () {
    $data = [
        "name" => ["en" => "new venue", "zh-TW" => "new venue", "zh-CN" => "new venue"],
        "slug" => "new-venue",
        "address_line_1" => ["en" => "address 1", "zh-TW" => "address 1", "zh-CN" => "address 1"],
        "city" => ["en" => "", "zh-TW" => "", "zh-CN" => ""], // All empty
        "country_id" => $this->country->id,
    ];

    expect(fn() => VenueData::from($data))
        ->toThrow(CannotCreateData::class);
});

it('creates venue data successfully when at least one city locale has value', function () {
    $data = [
        "name" => ["en" => "new venue", "zh-TW" => "new venue", "zh-CN" => "new venue"],
        "slug" => "new-venue",
        "address_line_1" => ["en" => "address 1", "zh-TW" => "address 1", "zh-CN" => "address 1"],
        "city" => ["en" => "New York", "zh-TW" => "", "zh-CN" => ""], // At least one has value
        "country_id" => $this->country->id,
    ];

    $venueData = VenueData::from($data);
    expect($venueData)->toBeInstanceOf(VenueData::class);
    expect($venueData->city)->toBe(["en" => "New York", "zh-TW" => "", "zh-CN" => ""]);
});
