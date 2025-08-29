<?php

use App\Modules\Membership\Models\MembershipLevel;

describe('MembershipLevel Model', function () {
    it('creates a membership level with all fields', function () {
        $membershipLevel = MembershipLevel::create([
            'name' => [
                'en' => 'Premium Plan',
                'zh-TW' => '高級方案',
                'zh-CN' => '高级方案',
            ],
            'slug' => 'premium',
            'description' => [
                'en' => 'Premium membership with extra features',
                'zh-TW' => '具有額外功能的高級會員',
                'zh-CN' => '具有额外功能的高级会员',
            ],
            'benefits' => [
                'en' => 'Access to premium content\nPriority support',
                'zh-TW' => '存取高級內容\n優先支援',
                'zh-CN' => '访问高级内容\n优先支持',
            ],
            'price' => 2999,
            'is_active' => true,
            'sort_order' => 1,
            'stripe_product_id' => 'prod_test123',
            'stripe_price_id' => 'price_test123',
        ]);

        expect($membershipLevel)->toBeInstanceOf(MembershipLevel::class);
        expect($membershipLevel->name)->toBe([
            'en' => 'Premium Plan',
            'zh-TW' => '高級方案',
            'zh-CN' => '高级方案',
        ]);
        expect($membershipLevel->slug)->toBe('premium');
        expect($membershipLevel->description)->toBe([
            'en' => 'Premium membership with extra features',
            'zh-TW' => '具有額外功能的高級會員',
            'zh-CN' => '具有额外功能的高级会员',
        ]);
        expect($membershipLevel->benefits)->toBe([
            'en' => 'Access to premium content\nPriority support',
            'zh-TW' => '存取高級內容\n優先支援',
            'zh-CN' => '访问高级内容\n优先支持',
        ]);
        expect($membershipLevel->price)->toBe(2999);
        expect($membershipLevel->is_active)->toBeTrue();
        expect($membershipLevel->sort_order)->toBe(1);
        expect($membershipLevel->stripe_product_id)->toBe('prod_test123');
        expect($membershipLevel->stripe_price_id)->toBe('price_test123');
        expect($membershipLevel->wasRecentlyCreated)->toBeTrue();
    });

    it('updates an existing membership level', function () {
        $existingMembershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Old Plan'],
            'slug' => 'old-plan',
            'price' => 1999,
            'is_active' => false,
        ]);

        $updateData = [
            'name' => [
                'en' => 'Updated Premium Plan',
                'zh-TW' => '更新的高級方案',
            ],
            'slug' => 'updated-premium',
            'description' => [
                'en' => 'Updated description',
                'zh-TW' => '更新的描述',
            ],
            'benefits' => [
                'en' => 'Updated benefits',
                'zh-TW' => '更新的優惠',
            ],
            'price' => 3999,
            'is_active' => true,
            'sort_order' => 2,
            'stripe_product_id' => 'prod_updated123',
            'stripe_price_id' => 'price_updated123',
        ];

        $existingMembershipLevel->update($updateData);
        $existingMembershipLevel->refresh();

        expect($existingMembershipLevel->name)->toBe([
            'en' => 'Updated Premium Plan',
            'zh-TW' => '更新的高級方案',
        ]);
        expect($existingMembershipLevel->slug)->toBe('updated-premium');
        expect($existingMembershipLevel->price)->toBe(3999);
        expect($existingMembershipLevel->is_active)->toBeTrue();
        expect($existingMembershipLevel->sort_order)->toBe(2);
        expect($existingMembershipLevel->stripe_product_id)->toBe('prod_updated123');
        expect($existingMembershipLevel->stripe_price_id)->toBe('price_updated123');
        expect($existingMembershipLevel->wasRecentlyCreated)->toBeFalse();
    });

    it('handles nullable translatable fields', function () {
        $membershipLevel = MembershipLevel::create([
            'name' => ['en' => 'Basic Plan'],
            'slug' => 'basic',
            'price' => 0,
            'is_active' => true,
            'sort_order' => 0,
            'description' => null,
            'benefits' => null,
            'stripe_product_id' => null,
            'stripe_price_id' => null,
        ]);

        expect($membershipLevel->name)->toBe(['en' => 'Basic Plan']);
        expect($membershipLevel->slug)->toBe('basic');
        expect($membershipLevel->price)->toBe(0);
        expect($membershipLevel->is_active)->toBeTrue();
        expect($membershipLevel->sort_order)->toBe(0);
        expect($membershipLevel->description)->toBeNull();
        expect($membershipLevel->benefits)->toBeNull();
        expect($membershipLevel->stripe_product_id)->toBeNull();
        expect($membershipLevel->stripe_price_id)->toBeNull();
    });

    it('handles translatable fields properly', function () {
        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Test Plan', 'zh-TW' => '測試方案'],
            'description' => ['en' => 'Test description', 'zh-TW' => '測試描述'],
            'benefits' => ['en' => 'Test benefits', 'zh-TW' => '測試優惠'],
        ]);

        expect($membershipLevel->name)->toBeArray();
        expect($membershipLevel->name['en'])->toBe('Test Plan');
        expect($membershipLevel->name['zh-TW'])->toBe('測試方案');
        expect($membershipLevel->getTranslation('name', 'en'))->toBe('Test Plan');
        expect($membershipLevel->getTranslation('name', 'zh-TW'))->toBe('測試方案');
    });
});