<?php

use App\Enums\RoleNameEnum;
use App\Models\SiteSetting;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create admin role if it doesn't exist
    Role::firstOrCreate(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleNameEnum::ADMIN->value);
    $this->actingAs($this->admin);
});

describe('SiteSettingController enable_chatbot', function () {
    describe('update enable_chatbot setting', function () {
        it('allows admin to enable chatbot', function () {
            $response = $this->put(route('admin.settings.update'), [
                'enable_chatbot' => true,
            ]);

            $response->assertRedirect(route('admin.settings.edit'));

            $setting = SiteSetting::where('key', 'enable_chatbot')->first();
            expect($setting)->not->toBeNull();
            expect($setting->value)->toBe(true);
        });

        it('allows admin to disable chatbot', function () {
            $response = $this->put(route('admin.settings.update'), [
                'enable_chatbot' => false,
            ]);

            $response->assertRedirect(route('admin.settings.edit'));

            $setting = SiteSetting::where('key', 'enable_chatbot')->first();
            expect($setting)->not->toBeNull();
            expect($setting->value)->toBe(false);
        });

        it('persists setting in database correctly', function () {
            // Enable first
            $this->put(route('admin.settings.update'), [
                'enable_chatbot' => true,
            ]);

            $setting = SiteSetting::where('key', 'enable_chatbot')->first();
            expect($setting->value)->toBe(true);

            // Then disable
            $this->put(route('admin.settings.update'), [
                'enable_chatbot' => false,
            ]);

            $setting->refresh();
            expect($setting->value)->toBe(false);
        });

        it('defaults to true when not set', function () {
            $value = SiteSetting::get('enable_chatbot', true);
            expect($value)->toBe(true);
        });
    });

    describe('chatbot_enabled prop sharing', function () {
        it('shares true when enabled', function () {
            SiteSetting::updateOrCreate(['key' => 'enable_chatbot'], [
                'value' => ['en' => true],
            ]);

            $response = $this->get(route('home'));

            expect($response->viewData('page')['props']['chatbot_enabled'])->toBe(true);
        });

        it('shares false when disabled', function () {
            SiteSetting::updateOrCreate(['key' => 'enable_chatbot'], [
                'value' => ['en' => false],
            ]);

            $response = $this->get(route('home'));

            expect($response->viewData('page')['props']['chatbot_enabled'])->toBe(false);
        });

        it('shares true by default when setting does not exist', function () {
            SiteSetting::where('key', 'enable_chatbot')->delete();

            $response = $this->get(route('home'));

            expect($response->viewData('page')['props']['chatbot_enabled'])->toBe(true);
        });
    });
});
