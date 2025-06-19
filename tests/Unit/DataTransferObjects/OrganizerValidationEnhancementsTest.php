<?php

namespace Tests\Unit\DataTransferObjects;

use App\Casts\SocialMediaLinksCaster;
use App\DataTransferObjects\Organizer\InviteUserData;
use App\DataTransferObjects\Organizer\OrganizerData;
use App\DataTransferObjects\Organizer\OrganizerUserData;
use App\Models\Organizer;
use App\Models\User;
use App\Rules\ValidOrganizerPermissions;
use App\Rules\UniqueOrganizerMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class OrganizerValidationEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_media_links_caster_handles_json_string()
    {
        $caster = new SocialMediaLinksCaster();

        // Create mock objects for the parameters
        $property = $this->createMock(\Spatie\LaravelData\Support\DataProperty::class);
        $context = $this->createMock(\Spatie\LaravelData\Support\Creation\CreationContext::class);

        // Test JSON string input
        $jsonString = '{"facebook":"https://facebook.com/test","twitter":"https://twitter.com/test"}';
        $result = $caster->cast($property, $jsonString, [], $context);

        $this->assertIsArray($result);
        $this->assertEquals('https://facebook.com/test', $result['facebook']);
        $this->assertEquals('https://twitter.com/test', $result['twitter']);
    }

    public function test_social_media_links_caster_handles_invalid_json()
    {
        $caster = new SocialMediaLinksCaster();

        // Create mock objects for the parameters
        $property = $this->createMock(\Spatie\LaravelData\Support\DataProperty::class);
        $context = $this->createMock(\Spatie\LaravelData\Support\Creation\CreationContext::class);

        // Test invalid JSON - should return original value
        $invalidJson = '{"facebook":"https://facebook.com/test"'; // Missing closing brace
        $result = $caster->cast($property, $invalidJson, [], $context);

        $this->assertEquals($invalidJson, $result);
    }

    public function test_social_media_links_caster_handles_array_input()
    {
        $caster = new SocialMediaLinksCaster();

        // Create mock objects for the parameters
        $property = $this->createMock(\Spatie\LaravelData\Support\DataProperty::class);
        $context = $this->createMock(\Spatie\LaravelData\Support\Creation\CreationContext::class);

        // Test array input - should return as-is
        $arrayInput = ['facebook' => 'https://facebook.com/test'];
        $result = $caster->cast($property, $arrayInput, [], $context);

        $this->assertEquals($arrayInput, $result);
    }

    public function test_valid_organizer_permissions_rule_accepts_valid_permissions()
    {
        $rule = new ValidOrganizerPermissions();

        $validPermissions = [
            'create_events',
            'edit_events',
            'delete_events',
            'manage_team',
            'view_analytics',
            'manage_finances'
        ];

        $validator = Validator::make(
            ['permissions' => $validPermissions],
            ['permissions' => $rule]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_valid_organizer_permissions_rule_rejects_invalid_permissions()
    {
        $rule = new ValidOrganizerPermissions();

        $invalidPermissions = [
            'create_events',
            'invalid_permission', // This should be rejected
            'another_invalid_one'
        ];

        $validator = Validator::make(
            ['permissions' => $invalidPermissions],
            ['permissions' => $rule]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('invalid permissions', $validator->errors()->first('permissions'));
    }

    public function test_valid_organizer_permissions_rule_handles_null_permissions()
    {
        $rule = new ValidOrganizerPermissions();

        $validator = Validator::make(
            ['permissions' => null],
            ['permissions' => $rule]
        );

        $this->assertTrue($validator->passes()); // Null should be allowed
    }

    public function test_unique_organizer_membership_rule_rejects_existing_member()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();

        // Add user to organizer
        $organizer->users()->attach($user->id, [
            'role_in_organizer' => 'staff',
            'is_active' => true,
            'invited_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rule = new UniqueOrganizerMembership($organizer->id);

        $validator = Validator::make(
            ['user_id' => $user->id],
            ['user_id' => $rule]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('already a member', $validator->errors()->first('user_id'));
    }

    public function test_unique_organizer_membership_rule_allows_new_member()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();

        // User is not a member
        $rule = new UniqueOrganizerMembership($organizer->id);

        $validator = Validator::make(
            ['user_id' => $user->id],
            ['user_id' => $rule]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_unique_organizer_membership_rule_ignores_inactive_members()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();

        // Add user as inactive member
        $organizer->users()->attach($user->id, [
            'role_in_organizer' => 'staff',
            'is_active' => false, // Inactive member
            'invited_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rule = new UniqueOrganizerMembership($organizer->id);

        $validator = Validator::make(
            ['user_id' => $user->id],
            ['user_id' => $rule]
        );

        $this->assertTrue($validator->passes()); // Should allow re-invitation of inactive member
    }

    public function test_organizer_data_enhanced_validation_with_casters()
    {
        $user = User::factory()->create();

        $data = [
            'name' => ['en' => 'Test Organizer'],
            'slug' => 'test-organizer',
            'description' => null,
            'contact_email' => 'test@example.com',
            'contact_phone' => null,
            'website_url' => null,
            'social_media_links' => '{"facebook":"https://facebook.com/test","twitter":""}', // JSON string
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country_id' => null,
            'state_id' => null,
            'is_active' => true,
            'contract_details' => null,
            'created_by' => $user->id,
            'logo_upload' => null,
            'id' => null,
        ];

        $organizerData = OrganizerData::from($data);

        // Test that social media links were cast from JSON to array
        $this->assertIsArray($organizerData->social_media_links);
        $this->assertEquals('https://facebook.com/test', $organizerData->social_media_links['facebook']);
        $this->assertEquals('', $organizerData->social_media_links['twitter']);
    }

    public function test_organizer_user_data_enhanced_validation()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $inviter = User::factory()->create();

        $data = [
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'manager',
            'permissions' => ['create_events', 'edit_events'], // Valid permissions
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $inviter->id,
            'invitation_accepted_at' => null,
        ];

        $organizerUserData = OrganizerUserData::from($data);

        $this->assertEquals(['create_events', 'edit_events'], $organizerUserData->permissions);
        $this->assertTrue($organizerUserData->hasPermission('create_events'));
        $this->assertFalse($organizerUserData->hasPermission('invalid_permission'));
    }

    public function test_invite_user_data_enhanced_validation()
    {
        $organizer = Organizer::factory()->create();
        $inviter = User::factory()->create();

        $data = [
            'organizer_id' => $organizer->id,
            'email' => 'newuser@example.com',
            'role_in_organizer' => 'staff',
            'invited_by' => $inviter->id,
            'custom_permissions' => ['view_events'], // Valid custom permission
            'invitation_message' => 'Welcome to our team!',
            'existing_user_id' => null,
            'expires_at' => now()->addDays(7),
        ];

        $inviteData = InviteUserData::from($data);

        $this->assertEquals(['view_events'], $inviteData->custom_permissions);
        $this->assertTrue($inviteData->hasCustomPermission('view_events'));
        $this->assertFalse($inviteData->hasCustomPermission('invalid_permission'));
    }

    public function test_enhanced_validation_rules_integration()
    {
        $rules = [
            'permissions' => [new ValidOrganizerPermissions()],
            'social_media_links' => ['nullable', 'array'],
            'social_media_links.facebook' => ['nullable', 'url'],
            'social_media_links.twitter' => ['nullable', 'url'],
            'social_media_links.instagram' => ['nullable', 'url'],
            'social_media_links.linkedin' => ['nullable', 'url'],
        ];

        // Test valid data
        $validData = [
            'permissions' => ['create_events', 'view_analytics'],
            'social_media_links' => [
                'facebook' => 'https://facebook.com/test',
                'twitter' => 'https://twitter.com/test',
            ],
        ];

        $validator = Validator::make($validData, $rules);
        $this->assertTrue($validator->passes());

        // Test invalid data
        $invalidData = [
            'permissions' => ['invalid_permission'],
            'social_media_links' => [
                'facebook' => 'not-a-valid-url',
            ],
        ];

        $validator = Validator::make($invalidData, $rules);
        $this->assertFalse($validator->passes());
    }
}
