<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCheckIn extends Model
{
    use HasFactory;

    protected $table = 'member_check_ins';

    protected $fillable = [
        'user_id',
        'scanned_by_user_id',
        'scanned_at',
        'location',
        'notes',
        'device_identifier',
        'membership_data',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'membership_data' => 'array',
    ];

    /**
     * Get the member who was checked in.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who performed the scan.
     */
    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }

    /**
     * Scope for check-ins by a specific member.
     */
    public function scopeForMember($query, User $member)
    {
        return $query->where('user_id', $member->id);
    }

    /**
     * Scope for check-ins performed by a specific scanner.
     */
    public function scopeByScanner($query, User $scanner)
    {
        return $query->where('scanned_by_user_id', $scanner->id);
    }

    /**
     * Scope for check-ins within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('scanned_at', [$startDate, $endDate]);
    }

    /**
     * Scope for recent check-ins.
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('scanned_at', '>=', now()->subHours($hours));
    }
}
