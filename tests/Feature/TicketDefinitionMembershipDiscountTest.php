<?php

namespace Tests\Feature;

use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketDefinitionMembershipDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function ticket_definition_can_have_membership_discounts(): void
    {
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
        ]);

        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Premium'],
        ]);

        // Create percentage discount
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'percentage',
            'discount_value' => 10, // 10%
        ]);

        $this->assertDatabaseHas('ticket_definition_membership_discounts', [
            'ticket_definition_id' => $ticketDefinition->id,
            'membership_level_id' => $membershipLevel->id,
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);

        $this->assertTrue($ticketDefinition->hasMembershipDiscount($membershipLevel));
    }

    /** @test */
    public function ticket_definition_can_have_fixed_amount_discount(): void
    {
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
        ]);

        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'VIP'],
        ]);

        // Create fixed amount discount
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'fixed',
            'discount_value' => 1500, // $15.00 off
        ]);

        $this->assertDatabaseHas('ticket_definition_membership_discounts', [
            'ticket_definition_id' => $ticketDefinition->id,
            'membership_level_id' => $membershipLevel->id,
            'discount_type' => 'fixed',
            'discount_value' => 1500,
        ]);
    }

    /** @test */
    public function user_with_active_membership_gets_discounted_price(): void
    {
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
        ]);

        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Premium'],
        ]);

        // Create user membership
        UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => 'active',
            'expires_at' => now()->addMonths(1),
        ]);

        // Create percentage discount
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'percentage',
            'discount_value' => 20, // 20% off
        ]);

        $discountedPrice = $ticketDefinition->getMembershipPrice($user);
        $this->assertEquals(8000, $discountedPrice); // $80.00 (20% off $100)
    }

    /** @test */
    public function user_with_fixed_discount_gets_correct_price(): void
    {
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
        ]);

        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'VIP'],
        ]);

        // Create user membership
        UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => 'active',
            'expires_at' => now()->addMonths(1),
        ]);

        // Create fixed discount
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'fixed',
            'discount_value' => 2500, // $25.00 off
        ]);

        $discountedPrice = $ticketDefinition->getMembershipPrice($user);
        $this->assertEquals(7500, $discountedPrice); // $75.00 ($100 - $25)
    }

    /** @test */
    public function user_without_membership_gets_regular_price(): void
    {
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
        ]);

        $membershipLevel = MembershipLevel::factory()->create();

        // Create discount but user has no membership
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);

        $price = $ticketDefinition->getMembershipPrice($user);
        $this->assertEquals(10000, $price); // Regular price
    }

    /** @test */
    public function user_with_expired_membership_gets_regular_price(): void
    {
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
        ]);

        $membershipLevel = MembershipLevel::factory()->create();

        // Create expired membership
        UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => 'active',
            'expires_at' => now()->subDays(1), // Expired
        ]);

        // Create discount
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);

        $price = $ticketDefinition->getMembershipPrice($user);
        $this->assertEquals(10000, $price); // Regular price
    }

    /** @test */
    public function user_with_inactive_membership_gets_regular_price(): void
    {
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
        ]);

        $membershipLevel = MembershipLevel::factory()->create();

        // Create inactive membership
        UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => 'cancelled',
            'expires_at' => now()->addMonths(1),
        ]);

        // Create discount
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);

        $price = $ticketDefinition->getMembershipPrice($user);
        $this->assertEquals(10000, $price); // Regular price
    }

    /** @test */
    public function fixed_discount_cannot_exceed_ticket_price(): void
    {
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 5000, // $50.00
        ]);

        $membershipLevel = MembershipLevel::factory()->create();

        UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => 'active',
            'expires_at' => now()->addMonths(1),
        ]);

        // Create discount larger than ticket price
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'fixed',
            'discount_value' => 7500, // $75.00 off (more than $50 ticket)
        ]);

        $discountedPrice = $ticketDefinition->getMembershipPrice($user);
        $this->assertEquals(0, $discountedPrice); // Minimum $0.00
    }

    /** @test */
    public function percentage_discount_calculates_correctly(): void
    {
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 12345, // $123.45
        ]);

        $membershipLevel = MembershipLevel::factory()->create();

        UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => 'active',
            'expires_at' => now()->addMonths(1),
        ]);

        // Create 15% discount
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'percentage',
            'discount_value' => 15,
        ]);

        $discountedPrice = $ticketDefinition->getMembershipPrice($user);
        $expectedPrice = 12345 - round(12345 * 0.15); // $123.45 - 15%
        $this->assertEquals($expectedPrice, $discountedPrice);
    }

    /** @test */
    public function get_public_data_includes_membership_price_when_user_provided(): void
    {
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
        ]);

        $membershipLevel = MembershipLevel::factory()->create();

        UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => 'active',
            'expires_at' => now()->addMonths(1),
        ]);

        // Create percentage discount
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);

        // Mock pivot data for getPublicData method
        $ticketDefinition->setRelation('pivot', (object)[
            'price_override' => null,
            'quantity_for_occurrence' => null,
        ]);

        $publicData = $ticketDefinition->getPublicData($user);

        $this->assertArrayHasKey('membership_price', $publicData);
        $this->assertEquals(90.00, $publicData['membership_price']); // $90.00 (10% off $100)
        $this->assertArrayHasKey('has_membership_discount', $publicData);
        $this->assertTrue($publicData['has_membership_discount']);
    }

    /** @test */
    public function get_public_data_without_user_does_not_include_membership_price(): void
    {
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
        ]);

        $membershipLevel = MembershipLevel::factory()->create();

        // Create discount
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);

        // Mock pivot data for getPublicData method
        $ticketDefinition->setRelation('pivot', (object)[
            'price_override' => null,
            'quantity_for_occurrence' => null,
        ]);

        $publicData = $ticketDefinition->getPublicData();

        $this->assertArrayNotHasKey('membership_price', $publicData);
        $this->assertArrayNotHasKey('has_membership_discount', $publicData);
    }

    /** @test */
    public function membership_level_can_access_ticket_discounts(): void
    {
        $membershipLevel = MembershipLevel::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create();

        // Create discount
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'percentage',
            'discount_value' => 15,
        ]);

        $this->assertTrue($membershipLevel->ticketDiscounts->contains($ticketDefinition));
        $pivotData = $membershipLevel->ticketDiscounts->first()->pivot;
        $this->assertEquals('percentage', $pivotData->discount_type);
        $this->assertEquals(15, $pivotData->discount_value);
    }

    /** @test */
    public function unique_constraint_prevents_duplicate_discounts(): void
    {
        $ticketDefinition = TicketDefinition::factory()->create();
        $membershipLevel = MembershipLevel::factory()->create();

        // Create first discount
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);

        // Attempt to create duplicate should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'fixed',
            'discount_value' => 500,
        ]);
    }

    /** @test */
    public function user_can_get_active_membership_level(): void
    {
        $user = User::factory()->create();
        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Premium'],
        ]);

        // Create active membership
        UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => 'active',
            'expires_at' => now()->addMonths(1),
        ]);

        $activeMembership = $user->getActiveMembershipLevel();
        $this->assertNotNull($activeMembership);
        $this->assertEquals($membershipLevel->id, $activeMembership->id);
    }

    /** @test */
    public function user_without_active_membership_returns_null(): void
    {
        $user = User::factory()->create();

        $activeMembership = $user->getActiveMembershipLevel();
        $this->assertNull($activeMembership);
    }
}