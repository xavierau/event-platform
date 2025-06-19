<?php

namespace Tests\Unit\DataTransferObjects;

use Tests\TestCase;
use App\DataTransferObjects\Organizer\OrganizerData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OrganizerDataTest extends TestCase
{
    /** @test */
    public function it_can_create_organizer_data_dto()
    {
        $data = new OrganizerData(
            name: ['en' => 'Test Organizer', 'zh-TW' => '測試主辦方'],
            slug: 'test-organizer',
            description: ['en' => 'Test Description'],
            contact_email: 'test@example.com',
            contact_phone: '+1-234-567-8900',
            website_url: 'https://example.com',
            social_media_links: [
                'facebook' => 'https://facebook.com/test',
                'twitter' => 'https://twitter.com/test'
            ],
            address_line_1: '123 Main St',
            address_line_2: 'Suite 100',
            city: 'Test City',
            state: 'Test State',
            postal_code: '12345',
            country_id: 1,
            state_id: 1,
            is_active: true,
            contract_details: [
                'terms' => 'Test terms',
                'rate_structure' => '10% commission'
            ],
            created_by: 1,
            logo_upload: null,
            id: null,
        );

        $this->assertInstanceOf(OrganizerData::class, $data);
        $this->assertEquals('Test Organizer', $data->name['en']);
        $this->assertEquals('test-organizer', $data->slug);
        $this->assertEquals('test@example.com', $data->contact_email);
        $this->assertTrue($data->is_active);
    }

    /** @test */
    public function it_can_check_if_organizer_has_complete_address()
    {
        // Complete address
        $dataWithCompleteAddress = new OrganizerData(
            name: ['en' => 'Test Organizer'],
            slug: 'test-organizer',
            description: null,
            contact_email: null,
            contact_phone: null,
            website_url: null,
            social_media_links: null,
            address_line_1: '123 Main St',
            address_line_2: null,
            city: 'Test City',
            state: null,
            postal_code: null,
            country_id: 1,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: 1,
            logo_upload: null,
            id: null,
        );

        $this->assertTrue($dataWithCompleteAddress->hasCompleteAddress());

        // Incomplete address
        $dataWithIncompleteAddress = new OrganizerData(
            name: ['en' => 'Test Organizer'],
            slug: 'test-organizer',
            description: null,
            contact_email: null,
            contact_phone: null,
            website_url: null,
            social_media_links: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: 1,
            logo_upload: null,
            id: null,
        );

        $this->assertFalse($dataWithIncompleteAddress->hasCompleteAddress());
    }

    /** @test */
    public function it_can_check_if_organizer_has_contact_info()
    {
        // Has email
        $dataWithEmail = new OrganizerData(
            name: ['en' => 'Test Organizer'],
            slug: 'test-organizer',
            description: null,
            contact_email: 'test@example.com',
            contact_phone: null,
            website_url: null,
            social_media_links: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: 1,
            logo_upload: null,
            id: null,
        );

        $this->assertTrue($dataWithEmail->hasContactInfo());
        $this->assertEquals('test@example.com', $dataWithEmail->getPrimaryContact());

        // Has phone only
        $dataWithPhone = new OrganizerData(
            name: ['en' => 'Test Organizer'],
            slug: 'test-organizer',
            description: null,
            contact_email: null,
            contact_phone: '+1-234-567-8900',
            website_url: null,
            social_media_links: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: 1,
            logo_upload: null,
            id: null,
        );

        $this->assertTrue($dataWithPhone->hasContactInfo());
        $this->assertEquals('+1-234-567-8900', $dataWithPhone->getPrimaryContact());

        // No contact info
        $dataWithoutContact = new OrganizerData(
            name: ['en' => 'Test Organizer'],
            slug: 'test-organizer',
            description: null,
            contact_email: null,
            contact_phone: null,
            website_url: null,
            social_media_links: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: 1,
            logo_upload: null,
            id: null,
        );

        $this->assertFalse($dataWithoutContact->hasContactInfo());
        $this->assertNull($dataWithoutContact->getPrimaryContact());
    }

    /** @test */
    public function it_can_check_if_organizer_has_social_media_links()
    {
        // Has social media
        $dataWithSocial = new OrganizerData(
            name: ['en' => 'Test Organizer'],
            slug: 'test-organizer',
            description: null,
            contact_email: null,
            contact_phone: null,
            website_url: null,
            social_media_links: [
                'facebook' => 'https://facebook.com/test',
                'twitter' => ''
            ],
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: 1,
            logo_upload: null,
            id: null,
        );

        $this->assertTrue($dataWithSocial->hasSocialMediaLinks());

        // No social media
        $dataWithoutSocial = new OrganizerData(
            name: ['en' => 'Test Organizer'],
            slug: 'test-organizer',
            description: null,
            contact_email: null,
            contact_phone: null,
            website_url: null,
            social_media_links: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            postal_code: null,
            country_id: null,
            state_id: null,
            is_active: true,
            contract_details: null,
            created_by: 1,
            logo_upload: null,
            id: null,
        );

        $this->assertFalse($dataWithoutSocial->hasSocialMediaLinks());
    }

    /** @test */
    public function it_provides_validation_rules()
    {
        $rules = OrganizerData::rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('slug', $rules);
        $this->assertArrayHasKey('contact_email', $rules);
        $this->assertArrayHasKey('is_active', $rules);

        // Check that required fields are marked as required
        $this->assertContains('required', $rules['name']);
        $this->assertContains('required', $rules['slug']);
        $this->assertContains('required', $rules['is_active']);

        // Check that nullable fields are marked as nullable
        $this->assertContains('nullable', $rules['contact_email']);
        $this->assertContains('nullable', $rules['description']);
    }

    /** @test */
    public function it_can_generate_update_rules_with_unique_slug_exception()
    {
        $organizerId = 1;
        $updateRules = OrganizerData::updateRules($organizerId);

        $this->assertIsArray($updateRules);
        $this->assertArrayHasKey('slug', $updateRules);

        // Check that the slug validation includes the unique rule with exception
        $slugRules = $updateRules['slug'];
        $this->assertContains('required', $slugRules);
        $this->assertContains("unique:organizers,slug,$organizerId", $slugRules);
    }
}
