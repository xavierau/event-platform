<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    protected $fillable = [
        'admin_user_id',
        'target_user_id',
        'action_type',
        'action_details',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'action_details' => 'array',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('target_user_id', $user->id);
    }

    public function scopeByAdmin($query, User $admin)
    {
        return $query->where('admin_user_id', $admin->id);
    }

    public function scopeOfType($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }
}
