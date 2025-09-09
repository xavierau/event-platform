<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FrontendLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'component',
        'message',
        'data',
        'client_timestamp',
        'url',
        'user_agent',
        'ip_address',
        'session_id',
        'user_id',
    ];

    protected $casts = [
        'data' => 'array',
        'client_timestamp' => 'datetime',
    ];

    /**
     * Get the user associated with this log entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by log level.
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope to filter by component.
     */
    public function scopeByComponent($query, string $component)
    {
        return $query->where('component', $component);
    }

    /**
     * Scope to get error logs.
     */
    public function scopeErrors($query)
    {
        return $query->where('level', 'error');
    }

    /**
     * Scope to get recent logs within specified hours.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
