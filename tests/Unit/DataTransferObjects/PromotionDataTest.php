<?php

namespace Tests\Unit\DataTransferObjects;

use App\DataTransferObjects\PromotionData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromotionDataTest extends TestCase
{
    #[Test]
    public function it_can_create_promotion_data_from_array()
    {
        $data = [
            'title' => ['en' => 'Summer Sale', 'zh-TW' => '夏季特賣'],
            'subtitle' => ['en' => 'Up to 50% off', 'zh-TW' => '最高5折優惠'],
            'url' => 'https://example.com/summer-sale',
            'is_active' => true,
            'starts_at' => '2024-01-01 00:00:00',
            'ends_at' => '2024-12-31 23:59:59',
            'sort_order' => 1,
        ];

        $promotionData = PromotionData::from($data);

        $this->assertInstanceOf(PromotionData::class, $promotionData);
        $this->assertEquals(['en' => 'Summer Sale', 'zh-TW' => '夏季特賣'], $promotionData->title);
        $this->assertEquals(['en' => 'Up to 50% off', 'zh-TW' => '最高5折優惠'], $promotionData->subtitle);
        $this->assertEquals('https://example.com/summer-sale', $promotionData->url);
        $this->assertTrue($promotionData->is_active);
        $this->assertEquals('2024-01-01 00:00:00', $promotionData->starts_at);
        $this->assertEquals('2024-12-31 23:59:59', $promotionData->ends_at);
        $this->assertEquals(1, $promotionData->sort_order);
    }

    #[Test]
    public function it_validates_required_title_in_english()
    {
        $this->expectException(ValidationException::class);

        PromotionData::validateAndCreate([
            'title' => ['zh-TW' => '夏季特賣'], // Missing 'en'
            'subtitle' => ['en' => 'Up to 50% off'],
            'url' => 'https://example.com',
        ]);
    }

    #[Test]
    public function it_validates_required_subtitle_in_english()
    {
        $this->expectException(ValidationException::class);

        PromotionData::validateAndCreate([
            'title' => ['en' => 'Summer Sale'],
            'subtitle' => ['zh-TW' => '最高5折優惠'], // Missing 'en'
            'url' => 'https://example.com',
        ]);
    }

    #[Test]
    public function it_validates_url_format()
    {
        $this->expectException(ValidationException::class);

        PromotionData::validateAndCreate([
            'title' => ['en' => 'Summer Sale'],
            'subtitle' => ['en' => 'Up to 50% off'],
            'url' => 'not-a-valid-url',
        ]);
    }

    #[Test]
    public function it_validates_date_format()
    {
        $this->expectException(ValidationException::class);

        PromotionData::validateAndCreate([
            'title' => ['en' => 'Summer Sale'],
            'subtitle' => ['en' => 'Up to 50% off'],
            'url' => 'https://example.com',
            'starts_at' => 'invalid-date',
        ]);
    }

    #[Test]
    public function it_validates_ends_at_after_starts_at()
    {
        $this->expectException(ValidationException::class);

        PromotionData::validateAndCreate([
            'title' => ['en' => 'Summer Sale'],
            'subtitle' => ['en' => 'Up to 50% off'],
            'url' => 'https://example.com',
            'starts_at' => '2024-12-31',
            'ends_at' => '2024-01-01', // Before starts_at
        ]);
    }

    #[Test]
    public function it_can_handle_banner_image_upload()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('banner.jpg');

        $data = [
            'title' => ['en' => 'Summer Sale'],
            'subtitle' => ['en' => 'Up to 50% off'],
            'url' => 'https://example.com',
            'uploaded_banner_image' => $file,
        ];

        $promotionData = PromotionData::from($data);

        $this->assertInstanceOf(UploadedFile::class, $promotionData->uploaded_banner_image);
        $this->assertEquals('banner.jpg', $promotionData->uploaded_banner_image->getClientOriginalName());
    }

    #[Test]
    public function it_allows_optional_fields_to_be_null()
    {
        $data = [
            'title' => ['en' => 'Summer Sale'],
            'subtitle' => ['en' => 'Up to 50% off'],
            'url' => 'https://example.com',
        ];

        $promotionData = PromotionData::from($data);

        $this->assertNull($promotionData->uploaded_banner_image);
        $this->assertNull($promotionData->starts_at);
        $this->assertNull($promotionData->ends_at);
        $this->assertFalse($promotionData->is_active);
        $this->assertEquals(0, $promotionData->sort_order);
    }
}
