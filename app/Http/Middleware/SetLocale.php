<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Session::has('locale')) {
            $locale = Session::get('locale');
            // Check if the locale from the session is in the list of available locales
            if (array_key_exists($locale, config('app.available_locales'))) {
                App::setLocale($locale);
            }
        }
        // If no valid locale in session, Laravel will use the default from config('app.locale').

        return $next($request);
    }
}
