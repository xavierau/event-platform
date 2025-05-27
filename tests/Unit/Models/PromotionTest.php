<?php

namespace Tests\Unit\Models;

use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_promotion()
    {
        $promotionData = [
            'title' => ['en' => 'Summer Sale', 'zh-TW' => '夏季特賣'],
            'subtitle' => ['en' => 'Up to 50% off', 'zh-TW' => '最高5折優惠'],
            'url' => 'https://example.com/summer-sale',
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'sort_order' => 1,
        ];

        $promotion = Promotion::create($promotionData);

        $this->assertInstanceOf(Promotion::class, $promotion);
        $this->assertEquals('Summer Sale', $promotion->getTranslation('title', 'en'));
        $this->assertEquals('夏季特賣', $promotion->getTranslation('title', 'zh-TW'));
        $this->assertEquals('Up to 50% off', $promotion->getTranslation('subtitle', 'en'));
        $this->assertEquals('最高5折優惠', $promotion->getTranslation('subtitle', 'zh-TW'));
        $this->assertEquals('https://example.com/summer-sale', $promotion->url);
        $this->assertTrue($promotion->is_active);
        $this->assertNotNull($promotion->starts_at);
        $this->assertNotNull($promotion->ends_at);
        $this->assertEquals(1, $promotion->sort_order);
    }

    #[Test]
    public function it_has_translatable_fields()
    {
        $promotion = new Promotion();

        $this->assertContains('title', $promotion->translatable);
        $this->assertContains('subtitle', $promotion->translatable);
    }

    #[Test]
    public function it_casts_dates_correctly()
    {
        $promotion = Promotion::create([
            'title' => ['en' => 'Test Promotion'],
            'subtitle' => ['en' => 'Test Subtitle'],
            'url' => 'https://example.com',
            'starts_at' => '2024-01-01 00:00:00',
            'ends_at' => '2024-12-31 23:59:59',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $promotion->starts_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $promotion->ends_at);
    }

    #[Test]
    public function it_implements_has_media_interface()
    {
        $promotion = new Promotion();

        $this->assertContains('Spatie\MediaLibrary\HasMedia', class_implements($promotion));
        $this->assertContains('Spatie\MediaLibrary\InteractsWithMedia', class_uses_recursive($promotion));
    }

    #[Test]
    public function it_has_active_scope()
    {
        // Create active promotion
        $activePromotion = Promotion::create([
            'title' => ['en' => 'Active Promotion'],
            'subtitle' => ['en' => 'Active'],
            'url' => 'https://example.com',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        // Create inactive promotion
        $inactivePromotion = Promotion::create([
            'title' => ['en' => 'Inactive Promotion'],
            'subtitle' => ['en' => 'Inactive'],
            'url' => 'https://example.com',
            'is_active' => false,
        ]);

        // Create expired promotion
        $expiredPromotion = Promotion::create([
            'title' => ['en' => 'Expired Promotion'],
            'subtitle' => ['en' => 'Expired'],
            'url' => 'https://example.com',
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(5),
        ]);

        $activePromotions = Promotion::active()->get();

        $this->assertCount(1, $activePromotions);
        $this->assertTrue($activePromotions->contains($activePromotion));
        $this->assertFalse($activePromotions->contains($inactivePromotion));
        $this->assertFalse($activePromotions->contains($expiredPromotion));
    }

    #[Test]
    public function it_orders_by_sort_order_by_default()
    {
        $promotion3 = Promotion::create([
            'title' => ['en' => 'Third'],
            'subtitle' => ['en' => 'Third'],
            'url' => 'https://example.com',
            'sort_order' => 3,
        ]);

        $promotion1 = Promotion::create([
            'title' => ['en' => 'First'],
            'subtitle' => ['en' => 'First'],
            'url' => 'https://example.com',
            'sort_order' => 1,
        ]);

        $promotion2 = Promotion::create([
            'title' => ['en' => 'Second'],
            'subtitle' => ['en' => 'Second'],
            'url' => 'https://example.com',
            'sort_order' => 2,
        ]);

        $promotions = Promotion::ordered()->get();

        $this->assertEquals($promotion1->id, $promotions[0]->id);
        $this->assertEquals($promotion2->id, $promotions[1]->id);
        $this->assertEquals($promotion3->id, $promotions[2]->id);
    }
}
