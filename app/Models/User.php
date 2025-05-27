<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Cashier\Billable;

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
    public function wishlistedEvents()
    {
        return $this->belongsToMany(Event::class, 'user_event_wishlists')
            ->withTimestamps();
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
}
