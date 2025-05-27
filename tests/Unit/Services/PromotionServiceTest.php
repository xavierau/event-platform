<?php

namespace Tests\Unit\Services;

use App\Actions\Promotion\UpsertPromotionAction;
use App\DataTransferObjects\PromotionData;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromotionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PromotionService(new UpsertPromotionAction());
    }

    #[Test]
    public function it_can_create_a_promotion()
    {
        $data = PromotionData::from([
            'title' => ['en' => 'Summer Sale', 'zh-TW' => '夏季特賣'],
            'subtitle' => ['en' => 'Up to 50% off', 'zh-TW' => '最高5折優惠'],
            'url' => 'https://example.com/summer-sale',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $promotion = $this->service->createPromotion($data);

        $this->assertInstanceOf(Promotion::class, $promotion);
        $this->assertEquals('Summer Sale', $promotion->getTranslation('title', 'en'));
        $this->assertTrue($promotion->is_active);
    }

    #[Test]
    public function it_can_update_a_promotion()
    {
        $promotion = Promotion::create([
            'title' => ['en' => 'Old Title'],
            'subtitle' => ['en' => 'Old Subtitle'],
            'url' => 'https://example.com/old',
        ]);

        $data = PromotionData::from([
            'title' => ['en' => 'New Title'],
            'subtitle' => ['en' => 'New Subtitle'],
            'url' => 'https://example.com/new',
        ]);

        $updatedPromotion = $this->service->updatePromotion($promotion, $data);

        $this->assertEquals('New Title', $updatedPromotion->getTranslation('title', 'en'));
        $this->assertEquals('https://example.com/new', $updatedPromotion->url);
    }

    #[Test]
    public function it_can_delete_a_promotion()
    {
        Storage::fake('public');

        // Create promotion with banner
        $file = UploadedFile::fake()->image('banner.jpg');
        $promotion = Promotion::create([
            'title' => ['en' => 'To be deleted'],
            'subtitle' => ['en' => 'Will be removed'],
            'url' => 'https://example.com',
        ]);
        $promotion->addMedia($file)->toMediaCollection('banner');

        $promotionId = $promotion->id;

        $result = $this->service->deletePromotion($promotion);

        $this->assertTrue($result);
        $this->assertNull(Promotion::find($promotionId));
        // Media should also be deleted
        $this->assertCount(0, $promotion->getMedia('banner'));
    }

    #[Test]
    public function it_can_find_a_promotion_by_id()
    {
        $promotion = Promotion::create([
            'title' => ['en' => 'Test Promotion'],
            'subtitle' => ['en' => 'Test Subtitle'],
            'url' => 'https://example.com',
        ]);

        $foundPromotion = $this->service->findPromotion($promotion->id);

        $this->assertInstanceOf(Promotion::class, $foundPromotion);
        $this->assertEquals($promotion->id, $foundPromotion->id);
    }

    #[Test]
    public function it_returns_null_when_promotion_not_found()
    {
        $foundPromotion = $this->service->findPromotion(999);

        $this->assertNull($foundPromotion);
    }

    #[Test]
    public function it_can_get_all_promotions()
    {
        Promotion::create([
            'title' => ['en' => 'Promotion 1'],
            'subtitle' => ['en' => 'Subtitle 1'],
            'url' => 'https://example.com/1',
            'sort_order' => 2,
        ]);

        Promotion::create([
            'title' => ['en' => 'Promotion 2'],
            'subtitle' => ['en' => 'Subtitle 2'],
            'url' => 'https://example.com/2',
            'sort_order' => 1,
        ]);

        $promotions = $this->service->getAllPromotions();

        $this->assertCount(2, $promotions);
        // Should be ordered by sort_order
        $this->assertEquals('Promotion 2', $promotions->first()->getTranslation('title', 'en'));
    }

    #[Test]
    public function it_can_get_active_promotions()
    {
        // Active promotion
        Promotion::create([
            'title' => ['en' => 'Active Promotion'],
            'subtitle' => ['en' => 'Active'],
            'url' => 'https://example.com',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        // Inactive promotion
        Promotion::create([
            'title' => ['en' => 'Inactive Promotion'],
            'subtitle' => ['en' => 'Inactive'],
            'url' => 'https://example.com',
            'is_active' => false,
        ]);

        // Expired promotion
        Promotion::create([
            'title' => ['en' => 'Expired Promotion'],
            'subtitle' => ['en' => 'Expired'],
            'url' => 'https://example.com',
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(5),
        ]);

        $activePromotions = $this->service->getActivePromotions();

        $this->assertCount(1, $activePromotions);
        $this->assertEquals('Active Promotion', $activePromotions->first()->getTranslation('title', 'en'));
    }
}
