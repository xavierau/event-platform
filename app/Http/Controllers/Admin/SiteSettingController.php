<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SiteSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing site settings.
     */
    public function edit(): InertiaResponse
    {
        $allDbSettings = SiteSetting::all();
        $initialFormValues = [];
        $locales = ['en', 'zh-TW', 'zh-CN']; // Supported locales
        $fallbackLocale = config('app.fallback_locale', 'en');

        // Define which keys are translatable and which are boolean
        // Add more keys here as you implement all settings from screenshots
        $translatableKeys = [
            'site_name',
            'site_slogan',
            'extra_footer_credits',
            'meta_title',
            'mail_sender_name',
            'admin_title',
            'admin_description',
            // 'meta_description' (SEO), 'address' (Contact)
        ];
        $booleanKeys = [
            'hide_expired_events',
            'timezone_conversion',
            'multi_organisation_mode',
            'verify_email_before_login',
            'publish_event_after_admin_approval',
            'manually_approve_organizer',
            'allow_offline_payment_organizer',
            'allow_offline_payment_customer',
            'disable_booking_cancellation',
            'disable_ticket_download',
            'disable_google_calendar',
            'enable_chatbot',
        ];

        $settingValuesFromDb = [];
        foreach ($allDbSettings as $s) {
            $settingValuesFromDb[$s->key] = $s->getTranslations('value');
        }

        // Define all known setting keys based on the group definitions to initialize form structure
        $allKnownKeys = [];
        foreach ($this->getSettingGroupsDefinition() as $group => $keysInGroup) {
            $allKnownKeys = array_merge($allKnownKeys, $keysInGroup);
        }
        $allKnownKeys = array_unique($allKnownKeys);

        foreach ($allKnownKeys as $key) {
            $dbValueArray = $settingValuesFromDb[$key] ?? []; // e.g. {en: val, fr: val} or {} if not in DB

            if (in_array($key, $translatableKeys)) {
                $translations = [];
                foreach ($locales as $locale) {
                    $translations[$locale] = $dbValueArray[$locale] ?? '';
                }
                $initialFormValues[$key] = $translations;
            } elseif (in_array($key, $booleanKeys)) {
                $initialFormValues[$key] = isset($dbValueArray[$fallbackLocale]) ? (bool) $dbValueArray[$fallbackLocale] : false;
            } else { // Assumed non-translatable single string/number (e.g. API keys, simple text, numbers)
                $initialFormValues[$key] = $dbValueArray[$fallbackLocale] ?? '';
            }
        }

        return Inertia::render('Admin/Settings/Edit', [
            'pageTitle' => 'Site Settings',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')], // Assuming you have an admin.dashboard route
                ['text' => 'Site Settings'],
            ],
            'initialSettings' => $initialFormValues,
            'locales' => $locales,
            'settingGroups' => $this->getSettingGroupsDefinition(),
        ]);
    }

    /**
     * Update the site settings in storage.
     */
    public function update(Request $request)
    {
        // Basic validation example (expand as needed)
        // $request->validate([
        //     'site_name.en' => 'required|string|max:255',
        //     // ... other rules
        // ]);

        $allSettingsData = $request->all();
        $fallbackLocale = config('app.fallback_locale', 'en');
        $translatableKeys = [
            'site_name',
            'site_slogan',
            'extra_footer_credits',
            'meta_title',
            'mail_sender_name',
            'admin_title',
            'admin_description',
        ]; // Keep this in sync or derive from a single source

        foreach ($allSettingsData as $key => $value) {
            if (in_array($key, $translatableKeys) && is_array($value)) {
                SiteSetting::updateOrCreate(['key' => $key], ['value' => $value]);
            } else {
                // For non-translatable or single-value fields, booleans, numbers, etc.
                // Store them under the fallback locale key in the JSON structure.
                SiteSetting::updateOrCreate(['key' => $key], ['value' => [$fallbackLocale => $value]]);
            }
        }

        // Consider clearing config cache if any of these settings are used in config files
        // Artisan::call('config:clear');

        return redirect()->route('admin.settings.edit')->with('success', 'Settings updated successfully.');
    }

    /**
     * Helper function to define the structure of settings for UI tabs.
     * Keys should match the keys used in initialSettings and form submission.
     */
    private function getSettingGroupsDefinition(): array
    {
        return [
            'Site' => [
                'site_name',
                'site_slogan',
                'extra_footer_credits',
                'enable_chatbot',
                // 'site_logo_path', 'site_favicon_path', // File uploads handled separately
            ],
            'SEO' => ['meta_title'/* 'meta_description', 'meta_keywords' */],
            'Regional' => [
                'timezone',
                'currency_code',
                'date_format',
                'time_format',
                'timezone_conversion',
            ],
            'Booking' => [
                /* 'pre_booking_time', 'pre_cancellation_time', 'max_ticket_qty_limit', */
                'hide_expired_events',
                'allow_offline_payment_organizer',
                'allow_offline_payment_customer',
                'disable_booking_cancellation',
                'disable_ticket_download',
                'disable_google_calendar',
            ],
            'MultiVendor' => [
                'multi_organisation_mode', /* 'admin_commission_percentage', */
                'verify_email_before_login',
                'publish_event_after_admin_approval',
                'manually_approve_organizer',
            ],
            'Mail' => [
                'mail_sender_email',
                'mail_sender_name',
                /* 'mail_driver', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', */
            ],
            'AdminUI' => [
                'admin_title',
                'admin_description',
                /* 'admin_background_image_path', 'admin_loader_path', 'admin_icon_image_path' */
            ],
            // 'Social' => [/* 'facebook_page_username', ... */],
            // 'Contact' => [/* 'address', 'phone', ... */],
            // 'Apps' => [/* 'google_client_id', ... */],
        ];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
