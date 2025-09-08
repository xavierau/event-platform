<?php

namespace App\Providers;

use App\Modules\PromotionalModal\Models\PromotionalModal;
use App\Modules\PromotionalModal\Policies\PromotionalModalPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PromotionalModalServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind services
        $this->app->bind(
            \App\Modules\PromotionalModal\Actions\UpsertPromotionalModalAction::class
        );
        
        $this->app->bind(
            \App\Modules\PromotionalModal\Actions\RecordImpressionAction::class
        );
        
        $this->app->bind(
            \App\Modules\PromotionalModal\Services\PromotionalModalService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(PromotionalModal::class, PromotionalModalPolicy::class);

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/promotional-modal.php');
        
        // Register permissions
        $this->registerPermissions();
    }

    /**
     * Register promotional modal permissions.
     */
    protected function registerPermissions(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $permissions = [
            'promotional_modals.view',
            'promotional_modals.create',
            'promotional_modals.update',
            'promotional_modals.delete',
            'promotional_modals.restore',
            'promotional_modals.force_delete',
            'promotional_modals.manage',
            'promotional_modals.view_analytics',
            'promotional_modals.bulk_update',
            'promotional_modals.toggle_status',
            'promotional_modals.export',
        ];

        foreach ($permissions as $permission) {
            if (!\Spatie\Permission\Models\Permission::where('name', $permission)->exists()) {
                \Spatie\Permission\Models\Permission::create(['name' => $permission]);
            }
        }
    }
}
