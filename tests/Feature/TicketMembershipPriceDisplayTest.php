<?php

use App\Models\TicketDefinition;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns all membership prices in getPublicData when discounts exist', function () {
    // Arrange
    $ticketDefinition = TicketDefinition::factory()->create([
        'price' => 10000, // $100.00 in cents
    ]);

    $goldLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Gold'],
        'slug' => 'gold',
    ]);

    $silverLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Silver'],
        'slug' => 'silver',
    ]);

    // Attach discounts: Gold 20% off, Silver 10% off
    $ticketDefinition->membershipDiscounts()->attach($goldLevel->id, [
        'discount_type' => 'percentage',
        'discount_value' => 20,
    ]);

    $ticketDefinition->membershipDiscounts()->attach($silverLevel->id, [
        'discount_type' => 'percentage',
        'discount_value' => 10,
    ]);

    // Mock pivot data for getPublicData method
    $ticketDefinition->setRelation('pivot', (object) [
        'price_override' => null,
        'quantity_for_occurrence' => null,
    ]);

    // Act
    $publicData = $ticketDefinition->getPublicData();

    // Assert
    expect($publicData)->toHaveKey('all_membership_prices');
    expect($publicData['all_membership_prices'])->toHaveCount(2);

    // Find Gold level data (20% off = $80.00, savings $20.00)
    $goldData = collect($publicData['all_membership_prices'])
        ->firstWhere('membership_level_slug', 'gold');
    expect($goldData)->not->toBeNull();
    expect($goldData['discounted_price'])->toEqual(80.00);
    expect($goldData['savings_amount'])->toEqual(20.00);
    expect($goldData['savings_percentage'])->toEqual(20);

    // Find Silver level data (10% off = $90.00, savings $10.00)
    $silverData = collect($publicData['all_membership_prices'])
        ->firstWhere('membership_level_slug', 'silver');
    expect($silverData)->not->toBeNull();
    expect($silverData['discounted_price'])->toEqual(90.00);
    expect($silverData['savings_amount'])->toEqual(10.00);
    expect($silverData['savings_percentage'])->toEqual(10);
});

it('returns empty array for all_membership_prices when no discounts exist', function () {
    // Arrange
    $ticketDefinition = TicketDefinition::factory()->create([
        'price' => 10000, // $100.00 in cents
    ]);

    // Mock pivot data for getPublicData method
    $ticketDefinition->setRelation('pivot', (object) [
        'price_override' => null,
        'quantity_for_occurrence' => null,
    ]);

    // Act
    $publicData = $ticketDefinition->getPublicData();

    // Assert
    expect($publicData)->toHaveKey('all_membership_prices');
    expect($publicData['all_membership_prices'])->toBeArray();
    expect($publicData['all_membership_prices'])->toBeEmpty();
});

it('sorts all_membership_prices by highest savings first', function () {
    // Arrange
    $ticketDefinition = TicketDefinition::factory()->create([
        'price' => 10000, // $100.00 in cents
    ]);

    $lowLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Bronze'],
        'slug' => 'bronze',
    ]);

    $highLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Platinum'],
        'slug' => 'platinum',
    ]);

    $midLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Silver'],
        'slug' => 'silver',
    ]);

    // Attach discounts in random order: 5%, 15%, 10%
    $ticketDefinition->membershipDiscounts()->attach($lowLevel->id, [
        'discount_type' => 'percentage',
        'discount_value' => 5,
    ]);

    $ticketDefinition->membershipDiscounts()->attach($highLevel->id, [
        'discount_type' => 'percentage',
        'discount_value' => 15,
    ]);

    $ticketDefinition->membershipDiscounts()->attach($midLevel->id, [
        'discount_type' => 'percentage',
        'discount_value' => 10,
    ]);

    // Mock pivot data for getPublicData method
    $ticketDefinition->setRelation('pivot', (object) [
        'price_override' => null,
        'quantity_for_occurrence' => null,
    ]);

    // Act
    $publicData = $ticketDefinition->getPublicData();

    // Assert - should be sorted: 15%, 10%, 5%
    expect($publicData['all_membership_prices'])->toHaveCount(3);
    expect($publicData['all_membership_prices'][0]['savings_percentage'])->toEqual(15);
    expect($publicData['all_membership_prices'][0]['membership_level_slug'])->toBe('platinum');
    expect($publicData['all_membership_prices'][1]['savings_percentage'])->toEqual(10);
    expect($publicData['all_membership_prices'][1]['membership_level_slug'])->toBe('silver');
    expect($publicData['all_membership_prices'][2]['savings_percentage'])->toEqual(5);
    expect($publicData['all_membership_prices'][2]['membership_level_slug'])->toBe('bronze');
});

it('handles fixed amount discounts correctly', function () {
    // Arrange
    $ticketDefinition = TicketDefinition::factory()->create([
        'price' => 10000, // $100.00 in cents
    ]);

    $membershipLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'VIP'],
        'slug' => 'vip',
    ]);

    // Attach fixed discount of $20.00 (2000 cents)
    $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
        'discount_type' => 'fixed',
        'discount_value' => 2000,
    ]);

    // Mock pivot data for getPublicData method
    $ticketDefinition->setRelation('pivot', (object) [
        'price_override' => null,
        'quantity_for_occurrence' => null,
    ]);

    // Act
    $publicData = $ticketDefinition->getPublicData();

    // Assert
    expect($publicData['all_membership_prices'])->toHaveCount(1);

    $vipData = $publicData['all_membership_prices'][0];
    expect($vipData['discounted_price'])->toEqual(80.00); // $100 - $20 = $80
    expect($vipData['savings_amount'])->toEqual(20.00);
    expect($vipData['savings_percentage'])->toEqual(20); // 20% savings
    expect($vipData['discount_type'])->toBe('fixed');
    expect($vipData['discount_value'])->toBe(2000);
});

it('includes membership level name and slug in all_membership_prices', function () {
    // Arrange
    $ticketDefinition = TicketDefinition::factory()->create([
        'price' => 10000, // $100.00 in cents
    ]);

    $membershipLevel = MembershipLevel::factory()->create([
        'name' => ['en' => 'Premium Member', 'zh-TW' => '高級會員'],
        'slug' => 'premium-member',
    ]);

    // Attach discount
    $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
        'discount_type' => 'percentage',
        'discount_value' => 25,
    ]);

    // Mock pivot data for getPublicData method
    $ticketDefinition->setRelation('pivot', (object) [
        'price_override' => null,
        'quantity_for_occurrence' => null,
    ]);

    // Act
    $publicData = $ticketDefinition->getPublicData();

    // Assert
    expect($publicData['all_membership_prices'])->toHaveCount(1);

    $membershipData = $publicData['all_membership_prices'][0];
    expect($membershipData)->toHaveKey('membership_level_id');
    expect($membershipData)->toHaveKey('membership_level_name');
    expect($membershipData)->toHaveKey('membership_level_slug');

    expect($membershipData['membership_level_id'])->toBe($membershipLevel->id);
    // Name is a translatable field - Spatie Translatable returns the current locale translation
    // when accessing the attribute (default locale is 'en')
    expect($membershipData['membership_level_name'])->toBe('Premium Member');
    expect($membershipData['membership_level_slug'])->toBe('premium-member');
});
