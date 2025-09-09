<?php

use App\Modules\PromotionalModal\Controllers\AdminPromotionalModalController;
use App\Modules\PromotionalModal\Controllers\PromotionalModalController;
use App\Modules\PromotionalModal\Controllers\WebPromotionalModalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Promotional Modal Routes
|--------------------------------------------------------------------------
|
| Here are the routes for the promotional modal system. These include
| both public API routes for frontend consumption and admin routes
| for managing promotional modals.
|
*/

// Public API routes for promotional modals
Route::middleware(['web'])->prefix('api')->group(function () {
    // Get promotional modals for current user/page
    Route::get('/promotional-modals', [PromotionalModalController::class, 'index'])
        ->name('promotional-modals.index');

    // Get specific modal
    Route::get('/promotional-modals/{promotional_modal}', [PromotionalModalController::class, 'show'])
        ->name('promotional-modals.show');

    // Record impression, click, or dismissal
    Route::post('/promotional-modals/{promotional_modal}/impression', [PromotionalModalController::class, 'recordImpression'])
        ->name('promotional-modals.impression');

    // Batch record impressions for performance
    Route::post('/promotional-modals/batch-impressions', [PromotionalModalController::class, 'batchImpressions'])
        ->name('promotional-modals.batch-impressions');
});

// Admin routes for managing promotional modals
Route::middleware(['web', 'auth'])->prefix('admin/api')->group(function () {
    // CRUD operations
    Route::apiResource('promotional-modals', AdminPromotionalModalController::class)
        ->names([
            'index' => 'admin.api.promotional-modals.index',
            'store' => 'admin.api.promotional-modals.store', 
            'show' => 'admin.api.promotional-modals.show',
            'update' => 'admin.api.promotional-modals.update',
            'destroy' => 'admin.api.promotional-modals.destroy'
        ]);

    // Additional admin endpoints
    Route::post('/promotional-modals/{promotional_modal}/toggle', [AdminPromotionalModalController::class, 'toggleStatus'])
        ->name('admin.promotional-modals.toggle');

    Route::post('/promotional-modals/{promotional_modal}/duplicate', [AdminPromotionalModalController::class, 'duplicate'])
        ->name('admin.promotional-modals.duplicate');

    Route::patch('/promotional-modals/sort-order', [AdminPromotionalModalController::class, 'updateSortOrder'])
        ->name('admin.promotional-modals.sort-order');

    Route::patch('/promotional-modals/priorities', [AdminPromotionalModalController::class, 'updatePriorities'])
        ->name('admin.promotional-modals.priorities');

    // Analytics endpoints
    Route::get('/promotional-modals/{promotional_modal}/analytics', [AdminPromotionalModalController::class, 'analytics'])
        ->name('admin.promotional-modals.analytics');

    Route::get('/promotional-modals/system/analytics', [AdminPromotionalModalController::class, 'systemAnalytics'])
        ->name('admin.promotional-modals.system-analytics');
});

// Admin web routes for promotional modal management pages
Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
    Route::get('/promotional-modals', [WebPromotionalModalController::class, 'index'])
        ->name('admin.promotional-modals.index');

    Route::get('/promotional-modals/create', [WebPromotionalModalController::class, 'create'])
        ->name('admin.promotional-modals.create');

    Route::post('/promotional-modals', [WebPromotionalModalController::class, 'store'])
        ->name('admin.promotional-modals.store');

    Route::get('/promotional-modals/{promotional_modal}/edit', [WebPromotionalModalController::class, 'edit'])
        ->name('admin.promotional-modals.edit');

    Route::put('/promotional-modals/{promotional_modal}', [WebPromotionalModalController::class, 'update'])
        ->name('admin.promotional-modals.update');

    Route::delete('/promotional-modals/{promotional_modal}', [WebPromotionalModalController::class, 'destroy'])
        ->name('admin.promotional-modals.destroy');

    Route::get('/promotional-modals/analytics', [WebPromotionalModalController::class, 'analytics'])
        ->name('admin.promotional-modals.analytics');
});