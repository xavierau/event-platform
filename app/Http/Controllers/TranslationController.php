<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Allow explicit locale parameter for SPA locale switching, fall back to app locale
        $locale = $request->query('locale', app()->getLocale());

        // Validate locale is in allowed list
        $availableLocales = array_keys(config('app.available_locales', ['en' => 'English']));
        if (! in_array($locale, $availableLocales)) {
            $locale = app()->getLocale();
        }

        $jsonTranslations = file_exists(lang_path("{$locale}.json"))
            ? json_decode(file_get_contents(lang_path("{$locale}.json")), true)
            : [];

        $phpTranslations = file_exists(lang_path("{$locale}/messages.php"))
            ? require lang_path("{$locale}/messages.php")
            : [];

        return response()->json([
            'locale' => $locale,
            'translations' => array_merge($phpTranslations, $jsonTranslations),
        ])->header('Cache-Control', 'public, max-age=3600'); // Cache for 1 hour
    }
}
