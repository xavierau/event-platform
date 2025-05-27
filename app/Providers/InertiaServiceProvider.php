<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class InertiaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Inertia::share([
            'locale' => function () {
                return app()->getLocale();
            },
            'availableLocales' => function () {
                return config('app.available_locales', ['en' => 'English']);
            },
        ]);
    }
}
