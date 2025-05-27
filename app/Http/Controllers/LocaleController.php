<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    /**
     * Switch the application locale
     */
    public function switch(Request $request): RedirectResponse
    {
        $locale = $request->input('locale');
        $availableLocales = array_keys(config('app.available_locales', ['en' => 'English']));

        // Validate that the requested locale is available
        if (in_array($locale, $availableLocales)) {
            // Store the locale in session
            Session::put('locale', $locale);

            // Set the application locale for this request
            app()->setLocale($locale);
        }

        return redirect()->back();
    }
}
