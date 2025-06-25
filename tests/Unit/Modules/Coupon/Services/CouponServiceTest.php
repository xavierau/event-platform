<?php

use App\Modules\Coupon\Services\CouponService;
use App\Modules\Coupon\Actions\UpsertCouponAction;
use App\Modules\Coupon\Actions\IssueCouponToUserAction;
use App\Modules\Coupon\Actions\RedeemUserCouponAction;
use App\Modules\Coupon\Actions\ValidateUserCouponForRedemptionAction;
use App\Modules\Coupon\Actions\FindUserCouponByCodeAction;
use App\Modules\Coupon\DataTransferObjects\CouponData;
use App\Modules\Coupon\DataTransferObjects\IssueCouponData;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $couponService;
    private UpsertCouponAction $mockUpsertAction;
    private IssueCouponToUserAction $mockIssueAction;
    private RedeemUserCouponAction $mockRedeemAction;
    private ValidateUserCouponForRedemptionAction $mockValidateAction;
    private FindUserCouponByCodeAction $mockFindAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockUpsertAction = $this->createMock(UpsertCouponAction::class);
        $this->mockIssueAction = $this->createMock(IssueCouponToUserAction::class);
        $this->mockRedeemAction = $this->createMock(RedeemUserCouponAction::class);
        $this->mockValidateAction = $this->createMock(ValidateUserCouponForRedemptionAction::class);
        $this->mockFindAction = $this->createMock(FindUserCouponByCodeAction::class);

        $this->couponService = new CouponService(
            $this->mockUpsertAction,
            $this->mockIssueAction,
            $this->mockRedeemAction,
            $this->mockValidateAction,
            $this->mockFindAction
        );
    }

    public function test_upsert_coupon_calls_action_and_returns_coupon()
    {
        // Arrange
        $couponData = new CouponData(
            organizer_id: 1,
            name: 'Test Coupon',
            description: 'Test Description',
            code: 'TEST123',
            type: CouponTypeEnum::SINGLE_USE,
            discount_value: 10,
            discount_type: 'percentage',
            max_issuance: 100,
            valid_from: '2024-01-01',
            expires_at: '2024-12-31',
            id: null
        );

        $expectedCoupon = Coupon::factory()->make(['id' => 1]);

        $this->mockUpsertAction
            ->expects($this->once())
            ->method('execute')
            ->with($couponData)
            ->willReturn($expectedCoupon);

        // Act
        $result = $this->couponService->upsertCoupon($couponData);

        // Assert
        $this->assertEquals($expectedCoupon, $result);
    }

    public function test_issue_coupon_calls_action_with_default_quantity()
    {
        // Arrange
        $issuanceData = new IssueCouponData(
            coupon_id: 1,
            user_id: 1,
            times_can_be_used: 1,
            quantity: 1
        );

        $expectedCoupons = [UserCoupon::factory()->make(['id' => 1])];

        $this->mockIssueAction
            ->expects($this->once())
            ->method('execute')
            ->with($issuanceData, 1)
            ->willReturn($expectedCoupons);

        // Act
        $result = $this->couponService->issueCoupon($issuanceData);

        // Assert
        $this->assertEquals($expectedCoupons, $result);
    }

    public function test_issue_coupon_calls_action_with_custom_quantity()
    {
        // Arrange
        $issuanceData = new IssueCouponData(
            coupon_id: 1,
            user_id: 1,
            times_can_be_used: 1,
            quantity: 5
        );

        $expectedCoupons = array_fill(0, 5, UserCoupon::factory()->make());

        $this->mockIssueAction
            ->expects($this->once())
            ->method('execute')
            ->with($issuanceData, 5)
            ->willReturn($expectedCoupons);

        // Act
        $result = $this->couponService->issueCoupon($issuanceData, 5);

        // Assert
        $this->assertEquals($expectedCoupons, $result);
    }

    public function test_redeem_coupon_calls_action_and_returns_result()
    {
        // Arrange
        $uniqueCode = 'ABC123';
        $location = 'Test Location';
        $details = ['event_id' => 1];
        $userCoupon = UserCoupon::factory()->make();

        $this->mockFindAction->method('execute')->with($uniqueCode)->willReturn($userCoupon);
        $this->mockValidateAction->method('execute')->with($userCoupon)->willReturn(['valid' => true]);

        $this->mockRedeemAction->expects($this->once())
            ->method('execute')
            ->with($userCoupon, $location, $details)
            ->willReturn($userCoupon);

        // Act
        $result = $this->couponService->redeemCoupon($uniqueCode, $location, $details);

        // Assert
        $this->assertEquals($userCoupon, $result);
    }

    public function test_validate_coupon_when_coupon_not_found()
    {
        // Arrange
        $uniqueCode = 'INVALID';

        $this->mockFindAction->expects($this->once())
            ->method('execute')
            ->with($uniqueCode)
            ->willReturn(null);

        // Assert
        $this->expectException(\App\Modules\Coupon\Exceptions\InvalidCouponException::class);
        $this->expectExceptionMessage('Coupon not found');

        // Act
        $this->couponService->validateCoupon($uniqueCode);
    }

    public function test_validate_coupon_when_coupon_found_and_valid()
    {
        // Arrange
        $uniqueCode = 'VALID123';
        $userCoupon = UserCoupon::factory()->make(['id' => 1]);

        $validationResult = [
            'valid' => true,
            'reasons' => [],
            'details' => ['remaining_uses' => 1],
        ];

        $this->mockFindAction->expects($this->once())
            ->method('execute')
            ->with($uniqueCode)
            ->willReturn($userCoupon);

        $this->mockValidateAction->expects($this->once())
            ->method('execute')
            ->with($userCoupon)
            ->willReturn($validationResult);

        // Act
        $result = $this->couponService->validateCoupon($uniqueCode);

        // Assert
        $this->assertEquals($userCoupon, $result);
    }

    public function test_validate_coupon_when_coupon_found_but_invalid()
    {
        // Arrange
        $uniqueCode = 'EXPIRED123';
        $userCoupon = UserCoupon::factory()->make(['id' => 1]);

        $validationResult = [
            'valid' => false,
            'reasons' => ['Coupon has expired'],
            'details' => ['remaining_uses' => 0],
        ];

        $this->mockFindAction->expects($this->once())
            ->method('execute')
            ->with($uniqueCode)
            ->willReturn($userCoupon);

        $this->mockValidateAction->expects($this->once())
            ->method('execute')
            ->with($userCoupon)
            ->willReturn($validationResult);

        // Assert
        $this->expectException(\App\Modules\Coupon\Exceptions\CouponExpiredException::class);
        $this->expectExceptionMessage('Coupon has expired');

        // Act
        $this->couponService->validateCoupon($uniqueCode);
    }

    public function test_find_coupon_by_code_calls_action()
    {
        // Arrange
        $uniqueCode = 'FIND123';
        $expectedCoupon = UserCoupon::factory()->make(['id' => 1]);

        $this->mockFindAction
            ->expects($this->once())
            ->method('execute')
            ->with($uniqueCode)
            ->willReturn($expectedCoupon);

        // Act
        $result = $this->couponService->findCouponByCode($uniqueCode);

        // Assert
        $this->assertEquals($expectedCoupon, $result);
    }

    public function test_get_coupons_for_organizer_returns_collection()
    {
        // Arrange
        $organizer1 = \App\Models\Organizer::factory()->create();
        $organizer2 = \App\Models\Organizer::factory()->create();

        $coupons = Coupon::factory()
            ->count(3)
            ->create(['organizer_id' => $organizer1->id]);

        // Create coupons for different organizer to ensure filtering works
        Coupon::factory()
            ->count(2)
            ->create(['organizer_id' => $organizer2->id]);

        // Act
        $result = $this->couponService->getCouponsForOrganizer($organizer1->id);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn($coupon) => $coupon->organizer_id === $organizer1->id));
    }

    public function test_get_user_coupons_returns_all_coupons_by_default()
    {
        // Arrange
        $userId = 1;

        $userCoupons = UserCoupon::factory()
            ->count(3)
            ->create(['user_id' => $userId]);

        // Create coupons for different user to ensure filtering works
        UserCoupon::factory()
            ->count(2)
            ->create(['user_id' => 2]);

        // Act
        $result = $this->couponService->getUserCoupons($userId);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn($coupon) => $coupon->user_id === $userId));
    }

    public function test_get_user_coupons_returns_only_active_when_requested()
    {
        // Arrange
        $userId = 1;

        // Create active coupons
        UserCoupon::factory()
            ->count(2)
            ->create([
                'user_id' => $userId,
                'status' => UserCouponStatusEnum::ACTIVE,
                'expires_at' => now()->addDays(30),
            ]);

        // Create expired coupon
        UserCoupon::factory()->create([
            'user_id' => $userId,
            'status' => UserCouponStatusEnum::EXPIRED,
        ]);

        // Create fully used coupon
        UserCoupon::factory()->create([
            'user_id' => $userId,
            'status' => UserCouponStatusEnum::FULLY_USED,
        ]);

        // Act
        $result = $this->couponService->getUserCoupons($userId, true);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn($coupon) => $coupon->status === UserCouponStatusEnum::ACTIVE));
    }

    public function test_get_coupon_by_id_returns_coupon_when_found()
    {
        // Arrange
        $coupon = Coupon::factory()->create();

        // Act
        $result = $this->couponService->getCouponById($coupon->id);

        // Assert
        $this->assertInstanceOf(Coupon::class, $result);
        $this->assertEquals($coupon->id, $result->id);
    }

    public function test_get_coupon_by_id_returns_null_when_not_found()
    {
        // Act
        $result = $this->couponService->getCouponById(999);

        // Assert
        $this->assertNull($result);
    }

    public function test_delete_coupon_returns_true_when_successful()
    {
        // Arrange
        $coupon = Coupon::factory()->create();

        // Act
        $result = $this->couponService->deleteCoupon($coupon->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_delete_coupon_returns_false_when_coupon_not_found()
    {
        // Act
        $result = $this->couponService->deleteCoupon(999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_is_coupon_code_available_returns_true_when_available()
    {
        // Act
        $result = $this->couponService->isCouponCodeAvailable('AVAILABLE123');

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_coupon_code_available_returns_false_when_taken()
    {
        // Arrange
        $coupon = Coupon::factory()->create(['code' => 'TAKEN123']);

        // Act
        $result = $this->couponService->isCouponCodeAvailable('TAKEN123');

        // Assert
        $this->assertFalse($result);
    }

    public function test_is_coupon_code_available_excludes_specified_id()
    {
        // Arrange
        $coupon = Coupon::factory()->create(['code' => 'EXCLUDE123']);

        // Act
        $result = $this->couponService->isCouponCodeAvailable('EXCLUDE123', $coupon->id);

        // Assert
        $this->assertTrue($result);
    }

    public function test_get_coupon_statistics_returns_empty_stats_when_coupon_not_found()
    {
        // Act
        $result = $this->couponService->getCouponStatistics(999);

        // Assert
        $this->assertFalse($result['coupon_found']);
        $this->assertEquals(0, $result['total_issued']);
        $this->assertEquals(0, $result['total_redeemed']);
        $this->assertEquals(0, $result['total_redemptions']);
    }

    public function test_get_coupon_statistics_returns_correct_stats()
    {
        // Arrange
        $coupon = Coupon::factory()->create();

        // Create active user coupons
        UserCoupon::factory()
            ->count(2)
            ->create([
                'coupon_id' => $coupon->id,
                'status' => UserCouponStatusEnum::ACTIVE,
                'times_used' => 0,
            ]);

        // Create fully used user coupon
        UserCoupon::factory()->create([
            'coupon_id' => $coupon->id,
            'status' => UserCouponStatusEnum::FULLY_USED,
            'times_used' => 1,
        ]);

        // Create expired user coupon
        UserCoupon::factory()->create([
            'coupon_id' => $coupon->id,
            'status' => UserCouponStatusEnum::EXPIRED,
            'times_used' => 0,
        ]);

        // Act
        $result = $this->couponService->getCouponStatistics($coupon->id);

        // Assert
        $this->assertTrue($result['coupon_found']);
        $this->assertEquals(4, $result['total_issued']);
        $this->assertEquals(1, $result['total_redeemed']);
        $this->assertEquals(2, $result['active_coupons']);
        $this->assertEquals(1, $result['expired_coupons']);
        $this->assertEquals(1, $result['fully_used_coupons']);
    }
}
