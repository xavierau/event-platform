<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\EditorUploadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DevController;
use App\Http\Controllers\Admin\TagController;
use Illuminate\Foundation\Application;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Admin Routes for Site Settings
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    // Example: Route::middleware(['auth', 'role:platform-admin'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('settings', [SiteSettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SiteSettingController::class, 'update'])->name('settings.update');

    // Venues
    // Explicitly define POST route for update to handle multipart/form-data with _method spoofing
    // This MUST come BEFORE the Route::resource for venues to take precedence for POST requests to this URI pattern.
    Route::post('venues/{venue}', [VenueController::class, 'update'])->name('admin.venues.update.post'); // Ensure it uses the admin. prefix from the group if that's how routes are named
    Route::resource('venues', VenueController::class);

    // Categories
    Route::resource('categories', CategoryController::class);

    // Tags
    Route::resource('tags', TagController::class);

    // Editor Image Upload
    Route::post('editor/image-upload', [EditorUploadController::class, 'uploadImage'])->name('editor.image.upload');

    // });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/dev/media-upload-test', [DevController::class, 'mediaUploadTest'])->name('admin.dev.media-upload-test');
    Route::post('/admin/dev/media-upload-test/post', [DevController::class, 'handleMediaPost'])->name('admin.dev.media-upload-test.post');
    Route::put('/admin/dev/media-upload-test/put', [DevController::class, 'handleMediaPut'])->name('admin.dev.media-upload-test.put');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
