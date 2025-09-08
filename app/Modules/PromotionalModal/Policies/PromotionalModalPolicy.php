<?php

namespace App\Modules\PromotionalModal\Policies;

use App\Models\User;
use App\Modules\PromotionalModal\Models\PromotionalModal;

class PromotionalModalPolicy
{
    /**
     * Determine whether the user can view any promotional modals.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('promotional_modals.view');
    }

    /**
     * Determine whether the user can view the promotional modal.
     */
    public function view(User $user, PromotionalModal $promotionalModal): bool
    {
        return $user->hasPermissionTo('promotional_modals.view');
    }

    /**
     * Determine whether the user can create promotional modals.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('promotional_modals.create');
    }

    /**
     * Determine whether the user can update the promotional modal.
     */
    public function update(User $user, PromotionalModal $promotionalModal): bool
    {
        return $user->hasPermissionTo('promotional_modals.update');
    }

    /**
     * Determine whether the user can delete the promotional modal.
     */
    public function delete(User $user, PromotionalModal $promotionalModal): bool
    {
        return $user->hasPermissionTo('promotional_modals.delete');
    }

    /**
     * Determine whether the user can restore the promotional modal.
     */
    public function restore(User $user, PromotionalModal $promotionalModal): bool
    {
        return $user->hasPermissionTo('promotional_modals.restore');
    }

    /**
     * Determine whether the user can permanently delete the promotional modal.
     */
    public function forceDelete(User $user, PromotionalModal $promotionalModal): bool
    {
        return $user->hasPermissionTo('promotional_modals.force_delete');
    }

    /**
     * Determine whether the user can manage promotional modal settings.
     */
    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('promotional_modals.manage');
    }

    /**
     * Determine whether the user can view promotional modal analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->hasPermissionTo('promotional_modals.view_analytics');
    }

    /**
     * Determine whether the user can bulk update promotional modals.
     */
    public function bulkUpdate(User $user): bool
    {
        return $user->hasPermissionTo('promotional_modals.bulk_update');
    }

    /**
     * Determine whether the user can toggle promotional modal status.
     */
    public function toggleStatus(User $user, PromotionalModal $promotionalModal): bool
    {
        return $user->hasPermissionTo('promotional_modals.toggle_status');
    }

    /**
     * Determine whether the user can export promotional modal data.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('promotional_modals.export');
    }
}