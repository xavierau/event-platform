<?php

namespace App\Models;

use App\Enums\OrganizerRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizerUser extends Model
{
    use HasFactory;

    protected $table = 'organizer_users';

    protected $fillable = [
        'organizer_id',
        'user_id',
        'role_in_organizer',
        'permissions',
        'joined_at',
        'is_active',
        'invited_by',
        'invitation_accepted_at',
    ];

    protected $casts = [
        'permissions' => 'json',
        'joined_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Relationship with the organizer.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    /**
     * Relationship with the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with the user who invited this member.
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Get the role as an enum.
     */
    public function getRoleAttribute(): ?OrganizerRoleEnum
    {
        return OrganizerRoleEnum::tryFrom($this->role_in_organizer);
    }

    /**
     * Check if the invitation is pending.
     */
    public function isPendingInvitation(): bool
    {
        return $this->joined_at !== null && $this->invitation_accepted_at === null;
    }

    /**
     * Check if the invitation has been accepted.
     */
    public function isInvitationAccepted(): bool
    {
        return $this->invitation_accepted_at !== null;
    }

    /**
     * Check if the user can manage other users.
     */
    public function canManageUsers(): bool
    {
        $role = $this->getRoleAttribute();
        return $role && $role->canManageUsers();
    }

    /**
     * Check if the user can manage the organizer.
     */
    public function canManageOrganizer(): bool
    {
        $role = $this->getRoleAttribute();
        return $role && $role->canManageOrganizer();
    }

    /**
     * Check if the user can manage events.
     */
    public function canManageEvents(): bool
    {
        $role = $this->getRoleAttribute();
        return $role && $role->canManageEvents();
    }

    /**
     * Check if the user is view-only.
     */
    public function isViewOnly(): bool
    {
        $role = $this->getRoleAttribute();
        return $role && $role->isViewOnly();
    }

    /**
     * Scope to get only active memberships.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get memberships by role.
     */
    public function scopeByRole($query, OrganizerRoleEnum $role)
    {
        return $query->where('role_in_organizer', $role->value);
    }

    /**
     * Scope to get pending invitations.
     */
    public function scopePendingInvitations($query)
    {
        return $query->whereNotNull('joined_at')
            ->whereNull('invitation_accepted_at');
    }

    /**
     * Scope to get accepted invitations.
     */
    public function scopeAcceptedInvitations($query)
    {
        return $query->whereNotNull('invitation_accepted_at');
    }
}
