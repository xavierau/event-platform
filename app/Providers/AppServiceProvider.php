<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Rate limiter for viewing purchase links (prevent link code enumeration attacks)
        // 60 requests per minute, keyed by user ID or IP address
        RateLimiter::for('purchase-link-show', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many requests. Please wait before trying again.',
                    ], 429, $headers);
                });
        });

        // Rate limiter for purchase attempts (prevent abuse)
        // 10 requests per minute, keyed by user ID (authenticated) or IP address
        RateLimiter::for('purchase-link-purchase', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many purchase attempts. Please wait before trying again.',
                    ], 429, $headers);
                });
        });
    }
}
