<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Venue;
use App\Modules\Coupon\Models\Coupon;
use App\Policies\CommentPolicy;
use App\Policies\CouponPolicy;
use App\Policies\EventPolicy;
use App\Policies\OrganizerPolicy;
use App\Policies\VenuePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Organizer::class => OrganizerPolicy::class,
        Event::class => EventPolicy::class,
        Venue::class => VenuePolicy::class,
        Coupon::class => CouponPolicy::class,
        Comment::class => CommentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('moderate-comments', [CommentPolicy::class, 'moderate']);
    }
}
