<?php

use App\Modules\SocialShare\Http\Controllers\SocialShareController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Social Share API Routes
|--------------------------------------------------------------------------
|
| These routes handle social media sharing functionality including
| share URL generation, analytics tracking, and statistics retrieval.
|
*/

Route::prefix('api/social-share')->middleware('api')->group(function () {

    // Generate share URLs for a shareable model
    Route::get('/urls', [SocialShareController::class, 'urls'])
        ->name('social-share.urls');

    // Track a share action
    Route::post('/track', [SocialShareController::class, 'track'])
        ->name('social-share.track');

    // Get share analytics
    Route::get('/analytics', [SocialShareController::class, 'analytics'])
        ->name('social-share.analytics');

    // Get popular content by share count
    Route::get('/popular', [SocialShareController::class, 'popular'])
        ->name('social-share.popular');

    // Get platform configuration
    Route::get('/platforms', [SocialShareController::class, 'platforms'])
        ->name('social-share.platforms');

    // Clear cache (admin only)
    Route::delete('/cache', [SocialShareController::class, 'clearCache'])
        ->middleware('auth')
        ->name('social-share.clear-cache');
});
