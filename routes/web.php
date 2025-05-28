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
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\QrScannerController;

use App\Http\Controllers\Public\EventController as PublicEventController;
use App\Http\Controllers\Public\MyBookingsController;
use App\Http\Controllers\LocaleController;

// Locale switching route
Route::post('/locale/switch', [LocaleController::class, 'switch'])->name('locale.switch');

Route::get('/', HomeController::class)->name('home');
// Route::get('/', function () {

//     $service = new CategoryService(new UpsertCategoryAction());
//     $categories = $service->getPublicCategories();

//     dd($categories);
// })->name('home');

// Test route for wishlist authentication
Route::get('/test/wishlist-auth', function () {
    return Inertia::render('Test/WishlistAuth');
})->name('test.wishlist-auth');

// Route for Public Event Detail Page
Route::get('/events/{event}', [\App\Http\Controllers\Public\EventController::class, 'show'])->name('events.show');

Route::get('/events', [PublicEventController::class, 'index'])->name('events.index');

// My Bookings route (requires authentication)
Route::middleware(['auth'])->group(function () {
    Route::get('/my-bookings', [MyBookingsController::class, 'index'])->name('my-bookings');
    Route::get('/my-wishlist', [\App\Http\Controllers\Public\MyWishlistController::class, 'index'])->name('my-wishlist');

    // Wishlist Routes - Session-based Authentication
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\WishlistController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\WishlistController::class, 'store']);
        Route::delete('/', [\App\Http\Controllers\Api\WishlistController::class, 'clear']);
        Route::delete('/{event}', [\App\Http\Controllers\Api\WishlistController::class, 'destroy']);
        Route::put('/{event}/toggle', [\App\Http\Controllers\Api\WishlistController::class, 'toggle']);
        Route::get('/{event}/check', [\App\Http\Controllers\Api\WishlistController::class, 'check']);
    });
});

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

    // Promotions
    Route::resource('promotions', \App\Http\Controllers\Admin\PromotionController::class);

    // Editor Image Upload
    Route::post('editor/image-upload', [EditorUploadController::class, 'uploadImage'])->name('editor.image.upload');

    // Event CRUD
    Route::resource('events', EventController::class)->except(['show']);
    Route::resource('events.occurrences', EventOccurrenceController::class)->shallow();

    // Ticket Definitions CRUD
    Route::resource('ticket-definitions', TicketDefinitionController::class);

    // Bookings CRUD (Admin)
    Route::resource('bookings', AdminBookingController::class);

    // QR Scanner routes
    Route::prefix('qr-scanner')->name('qr-scanner.')->group(function () {
        Route::get('/', [QrScannerController::class, 'index'])->name('index');
        Route::post('/validate', [QrScannerController::class, 'validateQrCode'])->name('validate');
        Route::post('/check-in', [QrScannerController::class, 'checkIn'])->name('check-in');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::post('/bookings/initiate', [BookingController::class, 'initiateBooking'])->name('bookings.initiate');


    Route::get('/admin/dev/media-upload-test', [DevController::class, 'mediaUploadTest'])->name('admin.dev.media-upload-test');
    Route::post('/admin/dev/media-upload-test/post', [DevController::class, 'handleMediaPost'])->name('admin.dev.media-upload-test.post');
    Route::put('/admin/dev/media-upload-test/put', [DevController::class, 'handleMediaPut'])->name('admin.dev.media-upload-test.put');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');
    // Add other admin routes here
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

// Payment Routes
Route::get('/payment/success', [PaymentController::class, 'handlePaymentSuccess'])->name('payment.success');
Route::get('/payment/cancel', [PaymentController::class, 'handlePaymentCancel'])->name('payment.cancel');

// Stripe Webhook Route (must be outside middleware groups)
Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook'])->name('stripe.webhook');
