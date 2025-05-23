<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\EditorUploadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DevController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\EventOccurrenceController;
use App\Http\Controllers\Admin\TicketDefinitionController;
use App\Http\Controllers\Public\HomeController;
use App\Services\CategoryService;
use App\Actions\Categories\UpsertCategoryAction;
use App\Services\EventService;
use App\Actions\Events\UpsertEventAction;

use App\Http\Controllers\Public\EventController as PublicEventController;

Route::get('/', HomeController::class)->name('home');
// Route::get('/', function () {

//     $service = new CategoryService(new UpsertCategoryAction());
//     $categories = $service->getPublicCategories();

//     dd($categories);
// })->name('home');

// Route for Public Event Detail Page
Route::get('/events/{event}', [\App\Http\Controllers\Public\EventController::class, 'show'])->name('events.show');

Route::get('/events', [PublicEventController::class, 'index'])->name('events.index');

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

    // Event CRUD
    Route::resource('events', EventController::class)->except(['show']);
    Route::resource('events.occurrences', EventOccurrenceController::class)->shallow();

    // Ticket Definitions CRUD
    Route::resource('ticket-definitions', TicketDefinitionController::class);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
    Route::get('/admin/dev/media-upload-test', [DevController::class, 'mediaUploadTest'])->name('admin.dev.media-upload-test');
    Route::post('/admin/dev/media-upload-test/post', [DevController::class, 'handleMediaPost'])->name('admin.dev.media-upload-test.post');
    Route::put('/admin/dev/media-upload-test/put', [DevController::class, 'handleMediaPut'])->name('admin.dev.media-upload-test.put');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
