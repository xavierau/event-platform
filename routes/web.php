<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\EditorUploadController;
use App\Http\Controllers\Admin\DashboardController;
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

    // Venues (Resourceful Route)
    Route::resource('venues', VenueController::class);

    // Categories
    Route::resource('categories', CategoryController::class);

    // Editor Image Upload
    Route::post('editor/image-upload', [EditorUploadController::class, 'uploadImage'])->name('editor.image.upload');

    // });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
