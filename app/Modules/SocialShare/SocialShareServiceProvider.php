<?php

namespace App\Modules\SocialShare;

use App\Modules\SocialShare\Actions\GenerateShareUrlAction;
use App\Modules\SocialShare\Actions\TrackShareAction;
use App\Modules\SocialShare\Services\SocialShareService;
use Illuminate\Support\ServiceProvider;

class SocialShareServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../../config/social-share.php',
            'social-share'
        );

        // Register services
        $this->app->singleton(SocialShareService::class, function ($app) {
            return new SocialShareService(
                $app->make(GenerateShareUrlAction::class),
                $app->make(TrackShareAction::class)
            );
        });

        // Register actions
        $this->app->singleton(GenerateShareUrlAction::class);
        $this->app->singleton(TrackShareAction::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../../config/social-share.php' => config_path('social-share.php'),
            ], 'social-share-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../../../database/migrations/' => database_path('migrations'),
            ], 'social-share-migrations');
        }

        // Load routes if they exist
        if (file_exists(__DIR__.'/routes/api.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        }

        // Load views if they exist
        if (is_dir(__DIR__.'/resources/views')) {
            $this->loadViewsFrom(__DIR__.'/resources/views', 'social-share');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            SocialShareService::class,
            GenerateShareUrlAction::class,
            TrackShareAction::class,
        ];
    }
}
