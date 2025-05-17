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
            App::setLocale(Session::get('locale'));
        }
        // Optionally, you could add more logic here:
        // 1. Check for locale in URL parameter
        // 2. Check for locale in user profile settings
        // 3. Check 'Accept-Language' header
        // For now, it defaults to config('app.locale') if no session is set.

        return $next($request);
    }
}
