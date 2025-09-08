<?php

use App\Models\User;
use App\Modules\PromotionalModal\Models\PromotionalModal;
use App\Modules\PromotionalModal\Models\PromotionalModalImpression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('PromotionalModalController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('can get promotional modals for a page', function () {
        Sanctum::actingAs($this->user);
        
        $modal1 = PromotionalModal::factory()->active()->forHomePage()->create();
        $modal2 = PromotionalModal::factory()->active()->forAllPages()->create();
        PromotionalModal::factory()->active()->create(['pages' => ['events']]); // Should not appear

        $response = $this->getJson('/api/promotional-modals?page=home');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'content',
                        'type',
                        'button_text',
                        'button_url',
                        'is_dismissible',
                        'banner_image_url',
                        'background_image_url',
                        'display_conditions',
                    ]
                ],
                'meta' => [
                    'count',
                    'page',
                    'type',
                ]
            ])
            ->assertJsonPath('meta.page', 'home');

        $responseData = $response->json('data');
        $returnedIds = collect($responseData)->pluck('id')->toArray();
        
        // Ensure both expected modals are returned
        expect($returnedIds)->toContain($modal1->id, $modal2->id);
        // Ensure the count matches the number of returned modals
        expect($response->json('meta.count'))->toBe(count($returnedIds));
    });

    it('can filter modals by type', function () {
        Sanctum::actingAs($this->user);
        
        PromotionalModal::factory()->active()->modal()->create();
        PromotionalModal::factory()->active()->banner()->create();

        $response = $this->getJson('/api/promotional-modals?page=home&type=banner');

        $response->assertOk();
        $responseData = $response->json('data');
        
        expect(collect($responseData))->toHaveCount(1);
        expect($responseData[0]['type'])->toBe('banner');
    });

    it('can record impression', function () {
        Sanctum::actingAs($this->user);
        
        $modal = PromotionalModal::factory()->active()->create([
            'impressions_count' => 5,
        ]);

        $response = $this->postJson("/api/promotional-modals/{$modal->id}/impression", [
            'action' => 'impression',
            'page_url' => 'https://example.com/home',
            'metadata' => ['source' => 'homepage'],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'action',
                    'created_at',
                ]
            ]);

        $modal->refresh();
        expect($modal->impressions_count)->toBe(6);
        
        $impression = PromotionalModalImpression::latest()->first();
        expect($impression->action)->toBe('impression');
        expect($impression->user_id)->toBe($this->user->id);
        expect($impression->page_url)->toBe('https://example.com/home');
        expect($impression->metadata)->toBe(['source' => 'homepage']);
    });

    it('can record click and update conversion rate', function () {
        Sanctum::actingAs($this->user);
        
        $modal = PromotionalModal::factory()->active()->create([
            'impressions_count' => 100,
            'clicks_count' => 10,
        ]);

        $response = $this->postJson("/api/promotional-modals/{$modal->id}/impression", [
            'action' => 'click',
        ]);

        $response->assertOk();

        $modal->refresh();
        expect($modal->clicks_count)->toBe(11);
        expect($modal->conversion_rate)->toBe(11.0); // 11/100 * 100
    });

    it('can record dismissal', function () {
        Sanctum::actingAs($this->user);
        
        $modal = PromotionalModal::factory()->active()->create();

        $response = $this->postJson("/api/promotional-modals/{$modal->id}/impression", [
            'action' => 'dismiss',
        ]);

        $response->assertOk();
        
        $impression = PromotionalModalImpression::latest()->first();
        expect($impression->action)->toBe('dismiss');
    });

    it('validates impression action', function () {
        Sanctum::actingAs($this->user);
        
        $modal = PromotionalModal::factory()->active()->create();

        $response = $this->postJson("/api/promotional-modals/{$modal->id}/impression", [
            'action' => 'invalid_action',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('action');
    });

    it('can show specific modal if user should see it', function () {
        Sanctum::actingAs($this->user);
        
        $modal = PromotionalModal::factory()->active()->create();

        $response = $this->getJson("/api/promotional-modals/{$modal->id}?page=home");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'content',
                    'type',
                    'button_text',
                    'button_url',
                    'is_dismissible',
                    'banner_image_url',
                    'background_image_url',
                    'display_conditions',
                ]
            ]);
    });

    it('returns 404 for modal user should not see', function () {
        Sanctum::actingAs($this->user);
        
        $modal = PromotionalModal::factory()->inactive()->create();

        $response = $this->getJson("/api/promotional-modals/{$modal->id}?page=home");

        $response->assertNotFound();
    });

    it('can batch record impressions', function () {
        Sanctum::actingAs($this->user);
        
        $modal1 = PromotionalModal::factory()->active()->create(['impressions_count' => 10]);
        $modal2 = PromotionalModal::factory()->active()->create(['clicks_count' => 5]);

        $response = $this->postJson('/api/promotional-modals/batch-impressions', [
            'impressions' => [
                [
                    'modal_id' => $modal1->id,
                    'action' => 'impression',
                    'page_url' => 'https://example.com/home',
                ],
                [
                    'modal_id' => $modal2->id,
                    'action' => 'click',
                    'metadata' => ['source' => 'banner'],
                ]
            ]
        ]);

        $response->assertOk()
            ->assertJsonPath('data.count', 2);

        expect($modal1->refresh()->impressions_count)->toBe(11);
        expect($modal2->refresh()->clicks_count)->toBe(6);
        
        expect(PromotionalModalImpression::count())->toBe(2);
    });

    it('validates batch impressions input', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/promotional-modals/batch-impressions', [
            'impressions' => [
                [
                    'modal_id' => 999999, // non-existent
                    'action' => 'impression',
                ]
            ]
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('impressions.0.modal_id');
    });

    it('limits batch impressions to 50', function () {
        Sanctum::actingAs($this->user);

        $impressions = collect(range(1, 51))->map(function () {
            return [
                'modal_id' => PromotionalModal::factory()->create()->id,
                'action' => 'impression',
            ];
        })->toArray();

        $response = $this->postJson('/api/promotional-modals/batch-impressions', [
            'impressions' => $impressions
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('impressions');
    });

    it('works for anonymous users with session tracking', function () {
        $modal = PromotionalModal::factory()->active()->create(['impressions_count' => 0]);

        $response = $this->postJson("/api/promotional-modals/{$modal->id}/impression", [
            'action' => 'impression',
        ]);

        $response->assertOk();
        
        expect($modal->refresh()->impressions_count)->toBe(1);
        
        $impression = PromotionalModalImpression::latest()->first();
        expect($impression->user_id)->toBeNull();
        expect($impression->session_id)->not->toBeNull();
    });

    it('validates page parameter', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/promotional-modals');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    });

    it('validates type parameter', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/promotional-modals?page=home&type=invalid');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    });

    it('respects limit parameter', function () {
        Sanctum::actingAs($this->user);
        
        PromotionalModal::factory()->active()->count(10)->create();

        $response = $this->getJson('/api/promotional-modals?page=home&limit=3');

        $response->assertOk();
        $responseData = $response->json('data');
        
        expect(collect($responseData))->toHaveCount(3);
    });
});