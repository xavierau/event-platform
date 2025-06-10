<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Cashier\Billable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the events that this user has wishlisted.
     */
    public function wishlistedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'user_event_wishlists')->withTimestamps();
    }

    /**
     * Add an event to the user's wishlist.
     */
    public function addToWishlist(Event $event): void
    {
        if (!$this->hasInWishlist($event)) {
            $this->wishlistedEvents()->attach($event->id);
        }
    }

    /**
     * Remove an event from the user's wishlist.
     */
    public function removeFromWishlist(Event $event): void
    {
        $this->wishlistedEvents()->detach($event->id);
    }

    /**
     * Check if the user has the event in their wishlist.
     */
    public function hasInWishlist(Event $event): bool
    {
        return $this->wishlistedEvents()->where('event_id', $event->id)->exists();
    }

    /**
     * Get the user's wallet.
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get the user's current membership.
     */
    public function currentMembership()
    {
        return $this->hasOne(UserMembership::class)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest();
    }

    /**
     * Get all user memberships.
     */
    public function memberships()
    {
        return $this->hasMany(UserMembership::class);
    }

    /**
     * Check if the user has an active membership.
     */
    public function hasMembership(): bool
    {
        return $this->currentMembership()->exists();
    }

    /**
     * Get the user's points balance.
     */
    public function getPointsBalance(): int
    {
        return $this->wallet?->points_balance ?? 0;
    }

    /**
     * Get the user's kill points balance.
     */
    public function getKillPointsBalance(): int
    {
        return $this->wallet?->kill_points_balance ?? 0;
    }

    /**
     * Check if the user has enough points.
     */
    public function hasEnoughPoints(int $amount): bool
    {
        return $this->getPointsBalance() >= $amount;
    }

    /**
     * Check if the user has enough kill points.
     */
    public function hasEnoughKillPoints(int $amount): bool
    {
        return $this->getKillPointsBalance() >= $amount;
    }

    public function membership(): HasOne
    {
        return $this->hasOne(UserMembership::class)->ofMany('started_at', 'max');
    }
}
