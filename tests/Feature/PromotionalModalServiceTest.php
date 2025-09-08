<?php

use App\Models\User;
use App\Modules\PromotionalModal\DataTransferObjects\PromotionalModalData;
use App\Modules\PromotionalModal\Models\PromotionalModal;
use App\Modules\PromotionalModal\Models\PromotionalModalImpression;
use App\Modules\PromotionalModal\Services\PromotionalModalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(PromotionalModalService::class);
});

describe('PromotionalModalService', function () {
    it('can create a promotional modal', function () {
        $data = new PromotionalModalData(
            title: ['en' => 'Test Modal', 'zh-TW' => '測試彈窗'],
            content: ['en' => 'Test content', 'zh-TW' => '測試內容'],
            type: 'modal',
            is_active: true
        );

        $modal = $this->service->createModal($data);

        expect($modal)->toBeInstanceOf(PromotionalModal::class);
        expect($modal->getTranslation('title', 'en'))->toBe('Test Modal');
        expect($modal->getTranslation('title', 'zh-TW'))->toBe('測試彈窗');
        expect($modal->is_active)->toBe(true);
    });

    it('can update a promotional modal', function () {
        $modal = PromotionalModal::factory()->create([
            'title' => ['en' => 'Original Title'],
        ]);

        $data = new PromotionalModalData(
            title: ['en' => 'Updated Title', 'zh-TW' => '更新標題'],
            content: ['en' => 'Updated content'],
            type: 'modal'
        );

        $updatedModal = $this->service->updateModal($modal, $data);

        expect($updatedModal->getTranslation('title', 'en'))->toBe('Updated Title');
        expect($updatedModal->getTranslation('title', 'zh-TW'))->toBe('更新標題');
        expect($updatedModal->id)->toBe($modal->id);
    });

    it('can delete a promotional modal', function () {
        $modal = PromotionalModal::factory()->create();

        $this->service->deleteModal($modal);

        expect($modal->refresh()->deleted_at)->not->toBeNull();
    });

    it('can get active modals', function () {
        PromotionalModal::factory()->active()->count(3)->create();
        PromotionalModal::factory()->inactive()->count(2)->create();

        $activeModals = $this->service->pappsidgetActiveModals();

        expect($activeModals)->toHaveCount(3);
        $activeModals->each(function (PromotionalModal $modal) {
            expect($modal->is_active)->toBe(true);
        });
    });

    it('can get modals for specific user and page', function () {
        $user = User::factory()->create();

        // Create modal for home page
        $homeModal = PromotionalModal::factory()->active()->forHomePage()->create();

        // Create modal for all pages
        $globalModal = PromotionalModal::factory()->active()->forAllPages()->create();

        // Create modal for events page only
        PromotionalModal::factory()->active()->create(['pages' => ['events']]);

        $modals = $this->service->getModalsForUser($user, 'home');

        expect($modals)->toHaveCount(2);
        expect($modals->pluck('id'))->toContain($homeModal->id, $globalModal->id);
    });

    it('respects display frequency when getting modals for user', function () {
        $user = User::factory()->create();
        $modal = PromotionalModal::factory()->active()->displayOnce()->modal()->create();

        // First request should return the modal
        $modals = $this->service->getModalsForUser($user, 'home');
        expect($modals)->toHaveCount(1);

        // Record an impression
        $this->service->recordImpression($modal, 'impression', $user);

        // Second request should not return the modal (display once)
        $modals = $this->service->getModalsForUser($user, 'home');
        expect($modals)->toHaveCount(0);
    });

    it('can record impressions', function () {
        $user = User::factory()->create();
        $modal = PromotionalModal::factory()->create(['impressions_count' => 0]);

        $impression = $this->service->recordImpression(
            $modal,
            'impression',
            $user,
            'session-123',
            'https://example.com/home'
        );

        expect($impression)->toBeInstanceOf(PromotionalModalImpression::class);
        expect($impression->action)->toBe('impression');
        expect($impression->user_id)->toBe($user->id);
        expect($modal->refresh()->impressions_count)->toBe(1);
    });

    it('can record clicks and update conversion rate', function () {
        $modal = PromotionalModal::factory()->create([
            'impressions_count' => 100,
            'clicks_count' => 5,
        ]);
        $user = User::factory()->create();

        $this->service->recordImpression($modal, 'click', $user);

        $modal->refresh();
        expect($modal->clicks_count)->toBe(6);
        expect((float) $modal->conversion_rate)->toBe(6.0); // 6/100 * 100
    });

    it('can toggle modal active status', function () {
        $modal = PromotionalModal::factory()->active()->create();

        $toggledModal = $this->service->toggleActive($modal);

        expect($toggledModal->is_active)->toBe(false);

        $toggledAgain = $this->service->toggleActive($toggledModal);
        expect($toggledAgain->is_active)->toBe(true);
    });

    it('can search modals by title and content', function () {
        PromotionalModal::factory()->create([
            'title' => ['en' => 'Special Promotion', 'zh-TW' => '特別促銷'],
            'content' => ['en' => 'Limited time offer'],
        ]);

        PromotionalModal::factory()->create([
            'title' => ['en' => 'Regular Sale'],
            'content' => ['en' => 'Normal discount'],
        ]);

        $results = $this->service->searchModals('Special');
        expect($results)->toHaveCount(1);
        expect($results->first()->getTranslation('title', 'en'))->toBe('Special Promotion');

        $results = $this->service->searchModals('特別');
        expect($results)->toHaveCount(1);
    });

    it('can update sort order for multiple modals', function () {
        $modal1 = PromotionalModal::factory()->create(['sort_order' => 1]);
        $modal2 = PromotionalModal::factory()->create(['sort_order' => 2]);

        $this->service->updateSortOrder([
            ['id' => $modal1->id, 'sort_order' => 10],
            ['id' => $modal2->id, 'sort_order' => 5],
        ]);

        expect($modal1->refresh()->sort_order)->toBe(10);
        expect($modal2->refresh()->sort_order)->toBe(5);
    });

    it('can get modal analytics', function () {
        $modal = PromotionalModal::factory()->create();

        // Create some impressions
        PromotionalModalImpression::factory()->impression()->count(100)->create([
            'promotional_modal_id' => $modal->id,
            'created_at' => now(),
        ]);

        // Create some clicks
        PromotionalModalImpression::factory()->click()->count(10)->create([
            'promotional_modal_id' => $modal->id,
            'created_at' => now(),
        ]);

        // Create some dismissals
        PromotionalModalImpression::factory()->dismiss()->count(5)->create([
            'promotional_modal_id' => $modal->id,
            'created_at' => now(),
        ]);

        $analytics = $this->service->getModalAnalytics($modal);

        expect($analytics['total_impressions'])->toBe(100);
        expect($analytics['total_clicks'])->toBe(10);
        expect($analytics['total_dismissals'])->toBe(5);
        expect($analytics['conversion_rate'])->toBe(10.0);
        expect($analytics['dismissal_rate'])->toBe(5.0);
    });

    it('can get system-wide analytics', function () {
        $modal1 = PromotionalModal::factory()->create();
        $modal2 = PromotionalModal::factory()->create();

        PromotionalModalImpression::factory()->impression()->count(50)->create([
            'promotional_modal_id' => $modal1->id,
        ]);

        PromotionalModalImpression::factory()->impression()->count(30)->create([
            'promotional_modal_id' => $modal2->id,
        ]);

        PromotionalModalImpression::factory()->click()->count(8)->create([
            'promotional_modal_id' => $modal1->id,
        ]);

        $analytics = $this->service->getSystemAnalytics();

        expect($analytics['total_impressions'])->toBe(80);
        expect($analytics['total_clicks'])->toBe(8);
        expect($analytics['conversion_rate'])->toBe(10.0);
        expect($analytics['total_modals_count'])->toBe(2);
    });

    it('respects membership level restrictions', function () {
        // For now, skip this test as it requires complex user membership setup
        // We'll implement this after the membership module integration
        $this->markTestSkipped('Membership level integration test - requires UserMembership setup');
    });

    it('can bulk update priorities', function () {
        $modal1 = PromotionalModal::factory()->create(['priority' => 10]);
        $modal2 = PromotionalModal::factory()->create(['priority' => 20]);

        $this->service->bulkUpdatePriorities([
            ['id' => $modal1->id, 'priority' => 50],
            ['id' => $modal2->id, 'priority' => 75],
        ]);

        expect($modal1->refresh()->priority)->toBe(50);
        expect($modal2->refresh()->priority)->toBe(75);
    });
});

describe('PromotionalModal Model', function () {
    it('can determine if modal is active based on time constraints', function () {
        $activeModal = PromotionalModal::factory()->create([
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ]);

        $expiredModal = PromotionalModal::factory()->create([
            'is_active' => true,
            'start_at' => now()->subWeek(),
            'end_at' => now()->subDay(),
        ]);

        $futureModal = PromotionalModal::factory()->create([
            'is_active' => true,
            'start_at' => now()->addDay(),
            'end_at' => now()->addWeek(),
        ]);

        expect($activeModal->isActive())->toBe(true);
        expect($expiredModal->isActive())->toBe(false);
        expect($futureModal->isActive())->toBe(false);
    });

    it('should not show modal to user who has already seen it with display_once', function () {
        $user = User::factory()->create();
        $modal = PromotionalModal::factory()->active()->displayOnce()->create();

        // First time - should show
        expect($modal->shouldShowToUser($user, 'home'))->toBe(true);

        // Create impression
        PromotionalModalImpression::factory()->impression()->create([
            'promotional_modal_id' => $modal->id,
            'user_id' => $user->id,
        ]);

        // Second time - should not show
        expect($modal->shouldShowToUser($user, 'home'))->toBe(false);
    });

    it('respects cooldown period for display frequency', function () {
        $user = User::factory()->create();
        $modal = PromotionalModal::factory()->active()->create([
            'display_frequency' => 'daily',
            'cooldown_hours' => 24,
        ]);

        // Create recent impression (within cooldown)
        PromotionalModalImpression::factory()->impression()->create([
            'promotional_modal_id' => $modal->id,
            'user_id' => $user->id,
            'created_at' => now()->subHours(12),
        ]);

        expect($modal->shouldShowToUser($user, 'home'))->toBe(false);

        // Create old impression (outside cooldown)
        PromotionalModalImpression::where('promotional_modal_id', $modal->id)
            ->where('user_id', $user->id)
            ->update(['created_at' => now()->subHours(25)]);

        expect($modal->shouldShowToUser($user, 'home'))->toBe(true);
    });

    it('can increment impressions and clicks correctly', function () {
        $modal = PromotionalModal::factory()->create([
            'impressions_count' => 10,
            'clicks_count' => 2,
        ]);

        $modal->incrementImpressions();
        expect($modal->refresh()->impressions_count)->toBe(11);

        $modal->incrementClicks();
        $modal->refresh();
        expect($modal->clicks_count)->toBe(3);
        expect((float) $modal->conversion_rate)->toBe(27.27); // 3/11 * 100, rounded to 2 decimals
    });
});
