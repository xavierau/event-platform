<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class TranslationController extends Controller
{
    public function index(): JsonResponse
    {
        $locale = app()->getLocale();

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
