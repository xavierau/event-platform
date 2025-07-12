<?php

use App\Modules\Coupon\Actions\CreateCouponUsageLogAction;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Models\CouponUsageLog;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use App\Models\User;
use Carbon\Carbon;

describe('CreateCouponUsageLogAction', function () {
    beforeEach(function () {
        $this->action = new CreateCouponUsageLogAction();
        $this->user = User::factory()->create();
        $this->coupon = Coupon::factory()->create();
        $this->userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
        ]);
    });

    it('creates usage log with required fields', function () {
        $result = $this->action->execute($this->userCoupon);

        expect($result)->toBeInstanceOf(CouponUsageLog::class)
            ->and($result->user_coupon_id)->toBe($this->userCoupon->id)
            ->and($result->user_id)->toBe($this->user->id)
            ->and($result->used_at)->toBeInstanceOf(Carbon::class)
            ->and($result->used_at->isToday())->toBeTrue();
    });

    it('creates usage log with location and details', function () {
        $location = 'Event Venue A';
        $details = [
            'scanner_device' => 'iPhone 13',
            'scanner_user_id' => 456,
            'event_id' => 123,
            'notes' => 'Scanned at entrance',
        ];

        $result = $this->action->execute($this->userCoupon, $location, $details);

        expect($result)->toBeInstanceOf(CouponUsageLog::class)
            ->and($result->location)->toBe($location)
            ->and($result->details)->toBe($details)
            ->and($result->details['scanner_device'])->toBe('iPhone 13')
            ->and($result->details['event_id'])->toBe(123);
    });

    it('creates usage log with current timestamp', function () {
        $beforeTime = Carbon::now()->startOfSecond();
        $result = $this->action->execute($this->userCoupon);
        $afterTime = Carbon::now()->endOfSecond();

        expect($result->used_at)->toBeGreaterThanOrEqual($beforeTime)
            ->and($result->used_at)->toBeLessThanOrEqual($afterTime);
    });

    it('creates usage log with null location and details', function () {
        $result = $this->action->execute($this->userCoupon, null, null);

        expect($result)->toBeInstanceOf(CouponUsageLog::class)
            ->and($result->location)->toBeNull()
            ->and($result->details)->toBeNull();
    });

    it('creates usage log with empty details array', function () {
        $result = $this->action->execute($this->userCoupon, 'Test Location', []);

        expect($result)->toBeInstanceOf(CouponUsageLog::class)
            ->and($result->location)->toBe('Test Location')
            ->and($result->details)->toBe([]);
    });

    it('saves usage log to database', function () {
        $initialCount = CouponUsageLog::count();

        $result = $this->action->execute($this->userCoupon);

        expect(CouponUsageLog::count())->toBe($initialCount + 1)
            ->and($result->exists)->toBeTrue()
            ->and($result->id)->not->toBeNull();
    });

    it('creates usage log with relationships loaded', function () {
        $result = $this->action->execute($this->userCoupon);

        // Reload from database to test relationships
        $savedLog = CouponUsageLog::with(['userCoupon', 'user'])->find($result->id);

        expect($savedLog->userCoupon)->not->toBeNull()
            ->and($savedLog->userCoupon->id)->toBe($this->userCoupon->id)
            ->and($savedLog->user)->not->toBeNull()
            ->and($savedLog->user->id)->toBe($this->user->id);
    });

    it('handles complex details structure', function () {
        $complexDetails = [
            'redemption_source' => 'mobile_app',
            'geolocation' => [
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'accuracy' => 10,
            ],
            'app_version' => '2.1.4',
            'user_agent' => 'EventApp/2.1.4 (iOS 16.0)',
            'session_id' => 'sess_abc123def456',
            'metadata' => [
                'coupon_display_name' => '50% Off Special',
                'promotion_campaign' => 'summer_2024',
            ],
        ];

        $result = $this->action->execute($this->userCoupon, 'Mobile App', $complexDetails);

        expect($result->details)->toBe($complexDetails)
            ->and($result->details['geolocation']['latitude'])->toBe(40.7128)
            ->and($result->details['metadata']['promotion_campaign'])->toBe('summer_2024');
    });

    it('creates multiple usage logs for the same user coupon', function () {
        // Create first usage log
        $firstLog = $this->action->execute($this->userCoupon, 'Location 1');

        // Wait a full second to ensure different timestamps
        sleep(1);

        // Create second usage log
        $secondLog = $this->action->execute($this->userCoupon, 'Location 2');

        expect($firstLog->id)->not->toBe($secondLog->id)
            ->and($firstLog->location)->toBe('Location 1')
            ->and($secondLog->location)->toBe('Location 2')
            ->and($secondLog->used_at)->toBeGreaterThan($firstLog->used_at);
    });

    it('handles very long location names', function () {
        $longLocation = str_repeat('Very Long Location Name ', 20); // 440 characters

        $result = $this->action->execute($this->userCoupon, $longLocation);

        expect($result->location)->toBe($longLocation);
    });

    it('creates usage log for different users', function () {
        $anotherUser = User::factory()->create();
        $anotherUserCoupon = UserCoupon::factory()->create([
            'user_id' => $anotherUser->id,
            'coupon_id' => $this->coupon->id,
        ]);

        $log1 = $this->action->execute($this->userCoupon, 'Location A');
        $log2 = $this->action->execute($anotherUserCoupon, 'Location B');

        expect($log1->user_id)->toBe($this->user->id)
            ->and($log2->user_id)->toBe($anotherUser->id)
            ->and($log1->user_coupon_id)->not->toBe($log2->user_coupon_id);
    });
});
