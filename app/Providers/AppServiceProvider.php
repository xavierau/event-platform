<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        // Bind member check-in services
        $this->app->bind(
            \App\Contracts\MemberCheckInServiceInterface::class,
            \App\Services\MemberCheckInService::class
        );
        
        $this->app->bind(
            \App\Contracts\MemberQrValidatorInterface::class,
            \App\Services\MemberQrValidator::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
