<?php

namespace App\Modules\PromotionalModal\Models;

use App\Models\User;
use Database\Factories\PromotionalModalImpressionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionalModalImpression extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return PromotionalModalImpressionFactory::new();
    }

    public $timestamps = false;

    protected $fillable = [
        'promotional_modal_id',
        'user_id',
        'session_id',
        'action',
        'page_url',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function promotionalModal(): BelongsTo
    {
        return $this->belongsTo(PromotionalModal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeImpressions($query)
    {
        return $query->where('action', 'impression');
    }

    public function scopeClicks($query)
    {
        return $query->where('action', 'click');
    }

    public function scopeDismissals($query)
    {
        return $query->where('action', 'dismiss');
    }

    public function scopeForUser($query, ?int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession($query, ?string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeInDateRange($query, \DateTime $startDate, \DateTime $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeForModal($query, int $promotionalModalId)
    {
        return $query->where('promotional_modal_id', $promotionalModalId);
    }
}