<?php

namespace App\Modules\TicketHold;

use App\Modules\TicketHold\Actions\Holds\CreateTicketHoldAction;
use App\Modules\TicketHold\Actions\Holds\ReleaseTicketHoldAction;
use App\Modules\TicketHold\Actions\Holds\UpdateTicketHoldAction;
use App\Modules\TicketHold\Actions\Holds\ValidateHoldAvailabilityAction;
use App\Modules\TicketHold\Actions\Links\CreatePurchaseLinkAction;
use App\Modules\TicketHold\Actions\Links\RecordLinkAccessAction;
use App\Modules\TicketHold\Actions\Links\RevokePurchaseLinkAction;
use App\Modules\TicketHold\Actions\Links\UpdatePurchaseLinkAction;
use App\Modules\TicketHold\Actions\Purchases\CalculateHoldPriceAction;
use App\Modules\TicketHold\Actions\Purchases\ProcessHoldPurchaseAction;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;
use App\Modules\TicketHold\Policies\PurchaseLinkPolicy;
use App\Modules\TicketHold\Policies\TicketHoldPolicy;
use App\Modules\TicketHold\Services\HoldAnalyticsService;
use App\Modules\TicketHold\Services\TicketHoldService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TicketHoldServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Hold Actions
        $this->app->bind(ValidateHoldAvailabilityAction::class);
        $this->app->bind(CreateTicketHoldAction::class);
        $this->app->bind(UpdateTicketHoldAction::class);
        $this->app->bind(ReleaseTicketHoldAction::class);

        // Register Link Actions
        $this->app->bind(CreatePurchaseLinkAction::class);
        $this->app->bind(UpdatePurchaseLinkAction::class);
        $this->app->bind(RevokePurchaseLinkAction::class);
        $this->app->bind(RecordLinkAccessAction::class);

        // Register Purchase Actions
        $this->app->bind(CalculateHoldPriceAction::class);
        $this->app->bind(ProcessHoldPurchaseAction::class);

        // Register Services
        $this->app->singleton(TicketHoldService::class, function ($app) {
            return new TicketHoldService(
                $app->make(CreateTicketHoldAction::class),
                $app->make(UpdateTicketHoldAction::class),
                $app->make(ReleaseTicketHoldAction::class),
                $app->make(CreatePurchaseLinkAction::class),
                $app->make(UpdatePurchaseLinkAction::class),
                $app->make(RevokePurchaseLinkAction::class),
                $app->make(RecordLinkAccessAction::class),
                $app->make(CalculateHoldPriceAction::class),
                $app->make(ProcessHoldPurchaseAction::class),
            );
        });

        $this->app->singleton(HoldAnalyticsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(TicketHold::class, TicketHoldPolicy::class);
        Gate::policy(PurchaseLink::class, PurchaseLinkPolicy::class);

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');

        // Load routes if file exists
        if (file_exists(__DIR__.'/../../../routes/ticket-hold.php')) {
            $this->loadRoutesFrom(__DIR__.'/../../../routes/ticket-hold.php');
        }

        // Register scheduled tasks for expiration checks
        $this->registerScheduledTasks();
    }

    /**
     * Register scheduled tasks for the module.
     */
    protected function registerScheduledTasks(): void
    {
        // This can be called from the Console Kernel to schedule expiration checks
        // Example usage in App\Console\Kernel:
        // $schedule->call(function () {
        //     app(TicketHoldService::class)->updateExpiredHolds();
        //     app(TicketHoldService::class)->updateExpiredLinks();
        // })->hourly();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            TicketHoldService::class,
            HoldAnalyticsService::class,
            ValidateHoldAvailabilityAction::class,
            CreateTicketHoldAction::class,
            UpdateTicketHoldAction::class,
            ReleaseTicketHoldAction::class,
            CreatePurchaseLinkAction::class,
            UpdatePurchaseLinkAction::class,
            RevokePurchaseLinkAction::class,
            RecordLinkAccessAction::class,
            CalculateHoldPriceAction::class,
            ProcessHoldPurchaseAction::class,
        ];
    }
}
