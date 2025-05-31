<?php

namespace Tests\Unit\Domains\EventManagement\Actions;

use App\Actions\Promotion\UpsertPromotionAction;
use App\DataTransferObjects\PromotionData;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpsertPromotionActionTest extends TestCase
{
    use RefreshDatabase;

    private UpsertPromotionAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpsertPromotionAction();
    }

    #[Test]
    public function it_can_create_a_new_promotion()
    {
        $data = PromotionData::from([
            'title' => ['en' => 'Summer Sale', 'zh-TW' => '夏季特賣'],
            'subtitle' => ['en' => 'Up to 50% off', 'zh-TW' => '最高5折優惠'],
            'url' => 'https://example.com/summer-sale',
            'is_active' => true,
            'starts_at' => '2024-01-01 00:00:00',
            'ends_at' => '2024-12-31 23:59:59',
            'sort_order' => 1,
        ]);

        $promotion = $this->action->execute($data);

        $this->assertInstanceOf(Promotion::class, $promotion);
        $this->assertEquals('Summer Sale', $promotion->getTranslation('title', 'en'));
        $this->assertEquals('夏季特賣', $promotion->getTranslation('title', 'zh-TW'));
        $this->assertEquals('Up to 50% off', $promotion->getTranslation('subtitle', 'en'));
        $this->assertEquals('最高5折優惠', $promotion->getTranslation('subtitle', 'zh-TW'));
        $this->assertEquals('https://example.com/summer-sale', $promotion->url);
        $this->assertTrue($promotion->is_active);
        $this->assertEquals(1, $promotion->sort_order);
    }

    #[Test]
    public function it_can_update_an_existing_promotion()
    {
        $promotion = Promotion::create([
            'title' => ['en' => 'Old Title'],
            'subtitle' => ['en' => 'Old Subtitle'],
            'url' => 'https://example.com/old',
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $data = PromotionData::from([
            'title' => ['en' => 'New Title', 'zh-TW' => '新標題'],
            'subtitle' => ['en' => 'New Subtitle', 'zh-TW' => '新副標題'],
            'url' => 'https://example.com/new',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $updatedPromotion = $this->action->execute($data, $promotion);

        $this->assertEquals($promotion->id, $updatedPromotion->id);
        $this->assertEquals('New Title', $updatedPromotion->getTranslation('title', 'en'));
        $this->assertEquals('新標題', $updatedPromotion->getTranslation('title', 'zh-TW'));
        $this->assertEquals('New Subtitle', $updatedPromotion->getTranslation('subtitle', 'en'));
        $this->assertEquals('新副標題', $updatedPromotion->getTranslation('subtitle', 'zh-TW'));
        $this->assertEquals('https://example.com/new', $updatedPromotion->url);
        $this->assertTrue($updatedPromotion->is_active);
        $this->assertEquals(2, $updatedPromotion->sort_order);
    }

    #[Test]
    public function it_can_handle_banner_image_upload_on_create()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('banner.jpg');

        $data = PromotionData::from([
            'title' => ['en' => 'Promotion with Banner'],
            'subtitle' => ['en' => 'Has a banner image'],
            'url' => 'https://example.com',
            'uploaded_banner_image' => $file,
        ]);

        $promotion = $this->action->execute($data);

        $this->assertCount(1, $promotion->getMedia('banner'));
        $media = $promotion->getFirstMedia('banner');
        $this->assertEquals('banner.jpg', $media->file_name);
    }

    #[Test]
    public function it_can_replace_banner_image_on_update()
    {
        Storage::fake('public');

        // Create promotion with initial banner
        $initialFile = UploadedFile::fake()->image('initial-banner.jpg');
        $promotion = Promotion::create([
            'title' => ['en' => 'Promotion'],
            'subtitle' => ['en' => 'Subtitle'],
            'url' => 'https://example.com',
        ]);
        $promotion->addMedia($initialFile)->toMediaCollection('banner');

        // Update with new banner
        $newFile = UploadedFile::fake()->image('new-banner.jpg');
        $data = PromotionData::from([
            'title' => ['en' => 'Promotion'],
            'subtitle' => ['en' => 'Subtitle'],
            'url' => 'https://example.com',
            'uploaded_banner_image' => $newFile,
        ]);

        $updatedPromotion = $this->action->execute($data, $promotion);

        $this->assertCount(1, $updatedPromotion->getMedia('banner'));
        $media = $updatedPromotion->getFirstMedia('banner');
        $this->assertEquals('new-banner.jpg', $media->file_name);
    }

    #[Test]
    public function it_preserves_banner_when_no_new_image_uploaded()
    {
        Storage::fake('public');

        // Create promotion with banner
        $file = UploadedFile::fake()->image('banner.jpg');
        $promotion = Promotion::create([
            'title' => ['en' => 'Promotion'],
            'subtitle' => ['en' => 'Subtitle'],
            'url' => 'https://example.com',
        ]);
        $promotion->addMedia($file)->toMediaCollection('banner');

        // Update without new banner
        $data = PromotionData::from([
            'title' => ['en' => 'Updated Promotion'],
            'subtitle' => ['en' => 'Updated Subtitle'],
            'url' => 'https://example.com',
        ]);

        $updatedPromotion = $this->action->execute($data, $promotion);

        $this->assertCount(1, $updatedPromotion->getMedia('banner'));
        $media = $updatedPromotion->getFirstMedia('banner');
        $this->assertEquals('banner.jpg', $media->file_name);
    }
}
