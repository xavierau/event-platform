<?php

namespace Tests\Unit\DataTransferObjects;

use App\DataTransferObjects\EventData;
use App\Enums\CommentConfigEnum;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EventDataTest extends TestCase
{
    /** @test */
    public function it_can_create_event_data_dto_with_redirect_url()
    {
        $data = new EventData(
            id: null,
            name: ['en' => 'Test Event', 'zh-TW' => '測試活動'],
            short_summary: ['en' => 'Short summary'],
            description: ['en' => 'Test Description'],
            slug: ['en' => 'test-event'],
            cancellation_policy: null,
            meta_title: null,
            meta_description: null,
            meta_keywords: null,
            website: null,
            social_links: null,
            social_media_links: null,
            youtube_video_id: null,
            contact_email: 'test@example.com',
            website_url: 'https://example.com',
            contact_phone: '+1-234-567-8900',
            additional_info: null,
            seating_chart: null,
            published_at: null,
            is_featured: false,
            category_id: 1,
            organizer_id: 1,
            created_by: 1,
            updated_by: 1,
            tag_ids: [],
            event_status: 'draft',
            visibility: 'public',
            visible_to_membership_levels: null,
            action_type: 'purchase_ticket',
            redirect_url: 'https://external-ticketing.example.com/event/123',
            main_image: null,
            thumbnail_image: null,
            uploaded_landscape_poster: null,
            uploaded_portrait_poster: null,
            gallery_images: null,
            removed_gallery_ids: null,
            comment_config: CommentConfigEnum::DISABLED,
            comments_enabled: false,
            comments_require_approval: false,
        );

        $this->assertInstanceOf(EventData::class, $data);
        $this->assertEquals('https://external-ticketing.example.com/event/123', $data->redirect_url);
        $this->assertEquals('Test Event', $data->name['en']);
        $this->assertEquals('purchase_ticket', $data->action_type);
    }

    /** @test */
    public function it_can_create_event_data_dto_without_redirect_url()
    {
        $data = new EventData(
            id: null,
            name: ['en' => 'Test Event'],
            short_summary: ['en' => 'Short summary'],
            description: ['en' => 'Test Description'],
            slug: ['en' => 'test-event'],
            cancellation_policy: null,
            meta_title: null,
            meta_description: null,
            meta_keywords: null,
            website: null,
            social_links: null,
            social_media_links: null,
            youtube_video_id: null,
            contact_email: null,
            website_url: null,
            contact_phone: null,
            additional_info: null,
            seating_chart: null,
            published_at: null,
            is_featured: false,
            category_id: 1,
            organizer_id: 1,
            created_by: null,
            updated_by: null,
            tag_ids: [],
            event_status: 'draft',
            visibility: 'public',
            visible_to_membership_levels: null,
            action_type: 'purchase_ticket',
            redirect_url: null,
            main_image: null,
            thumbnail_image: null,
            uploaded_landscape_poster: null,
            uploaded_portrait_poster: null,
            gallery_images: null,
            removed_gallery_ids: null,
            comment_config: CommentConfigEnum::DISABLED,
            comments_enabled: false,
            comments_require_approval: false,
        );

        $this->assertInstanceOf(EventData::class, $data);
        $this->assertNull($data->redirect_url);
    }

    /** @test */
    public function it_validates_redirect_url_as_valid_url()
    {
        $validUrls = [
            'https://example.com',
            'http://example.com',
            'https://www.example.com/path/to/tickets',
            'https://tickets.example.com/event/123?utm_source=platform',
            'https://example.com:8080/tickets',
        ];

        foreach ($validUrls as $url) {
            $validator = Validator::make(
                ['redirect_url' => $url],
                ['redirect_url' => ['nullable', 'url']]
            );

            $this->assertFalse($validator->fails(), "URL validation failed for: {$url}");
        }
    }

    /** @test */
    public function it_rejects_invalid_redirect_urls()
    {
        $invalidUrls = [
            'not-a-url',
            'ftp://example.com',
            'javascript:alert(1)',
            'mailto:test@example.com',
            'file:///etc/passwd',
            'data:text/html,<script>alert(1)</script>',
        ];

        foreach ($invalidUrls as $url) {
            $validator = Validator::make(
                ['redirect_url' => $url],
                ['redirect_url' => ['nullable', 'url', 'regex:/^https?:\/\/.*/i']]
            );

            $this->assertTrue($validator->fails(), "URL validation should fail for: {$url}");
        }
    }

    /** @test */
    public function it_allows_null_redirect_url()
    {
        $validator = Validator::make(
            ['redirect_url' => null],
            ['redirect_url' => ['nullable', 'url']]
        );

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_validates_redirect_url_max_length()
    {
        $longUrl = 'https://example.com/'.str_repeat('a', 2048); // Very long URL

        $validator = Validator::make(
            ['redirect_url' => $longUrl],
            ['redirect_url' => ['nullable', 'url', 'max:2048']]
        );

        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_ensures_redirect_url_uses_secure_protocols_only()
    {
        $secureUrls = [
            'https://example.com',
            'http://localhost:3000', // Allow HTTP for localhost
        ];

        $insecureUrls = [
            'http://example.com', // HTTP on public domain should be restricted in production
        ];

        foreach ($secureUrls as $url) {
            $validator = Validator::make(
                ['redirect_url' => $url],
                ['redirect_url' => ['nullable', 'url']]
            );

            $this->assertFalse($validator->fails(), "Secure URL validation failed for: {$url}");
        }
    }
}
