<?php

use App\DataTransferObjects\VenueData;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelData\Exceptions\CannotCreateData;
use Illuminate\Validation\ValidationException;
// use Spatie\LaravelData\Exceptions\ValidationException; // No longer directly expected
// use Illuminate\Support\Facades\Validator; // No longer needed for manual validation
// use Spatie\LaravelData\Support\Validation\ValidationContext as SpatieValidationContext; // No longer needed
// use Spatie\LaravelData\Support\Validation\ValidationPath; // No longer needed

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->country = Country::create([
        'name' => ['en' => 'United States', 'zh-TW' => '美國', 'zh-CN' => '美国'],
        'iso_code_2' => 'US',
        'iso_code_3' => 'USA',
        'phone_code' => '+1',
        'is_active' => true,
    ]);
});

it('validates basic required fields', function () {
    $data = [
        "country_id" => $this->country->id,
    ];
    expect(fn() => VenueData::validateAndCreate($data))->toThrow(ValidationException::class);
});

it('debugs validation with empty city values', function () {
    $data = [
        "name" => ["en" => "new venue", "zh-TW" => "new venue", "zh-CN" => "new venue"],
        "slug" => "new-venue",
        "address_line_1" => ["en" => "address 1", "zh-TW" => "address 1", "zh-CN" => "address 1"],
        "city" => ["en" => "", "zh-TW" => "", "zh-CN" => ""],
        "country_id" => $this->country->id,
    ];
    expect(fn() => VenueData::validateAndCreate($data))->toThrow(ValidationException::class);
});

it('validates city field correctly when all locales are empty strings', function () {
    $data = [
        "name" => ["en" => "new venue", "zh-TW" => "new venue", "zh-CN" => "new venue"],
        "slug" => "new-venue",
        "address_line_1" => ["en" => "address 1", "zh-TW" => "address 1", "zh-CN" => "address 1"],
        "city" => ["en" => "", "zh-TW" => "", "zh-CN" => ""],
        "country_id" => $this->country->id,
    ];
    expect(fn() => VenueData::validateAndCreate($data))->toThrow(ValidationException::class);
});

it('validates name field correctly when all locales are empty strings', function () {
    $data = [
        "name" => ["en" => "", "zh-TW" => "", "zh-CN" => ""],
        "slug" => "a-valid-slug-for-name-test",
        "address_line_1" => ["en" => "Valid Address", "zh-TW" => "有效地址", "zh-CN" => "有效地址"],
        "city" => ["en" => "Valid City", "zh-TW" => "有效城市", "zh-CN" => "有效城市"],
        "country_id" => $this->country->id,
    ];


    expect(fn() => VenueData::validateAndCreate($data))->toThrow(ValidationException::class);
});

it('creates venue data successfully when at least one city locale has value', function () {
    $data = [
        "name" => ["en" => "new venue", "zh-TW" => "new venue", "zh-CN" => "new venue"],
        "slug" => "new-venue",
        "address_line_1" => ["en" => "address 1", "zh-TW" => "address 1", "zh-CN" => "address 1"],
        "city" => ["en" => "New York", "zh-TW" => "", "zh-CN" => ""],
        "country_id" => $this->country->id,
    ];
    $venueData = VenueData::validateAndCreate($data);
    expect($venueData)->toBeInstanceOf(VenueData::class);
    expect($venueData->city)->toBe(["en" => "New York", "zh-TW" => "", "zh-CN" => ""]);
});
