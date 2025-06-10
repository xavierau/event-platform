<?php

use App\Enums\RoleNameEnum;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EditorUploadController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\EventOccurrenceController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\QrScannerController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\TicketDefinitionController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Modules\Membership\MembershipPaymentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Public\EventController as PublicEventController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\MyBookingsController;
use App\Http\Controllers\Public\MyWalletController;
use App\Http\Controllers\Public\MyWishlistController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// --- PUBLIC ROUTES ---
Route::post('/locale/switch', [LocaleController::class, 'switch'])->name('locale.switch');
Route::get('/', HomeController::class)->name('home');
Route::get('/events/{event}', [PublicEventController::class, 'show'])->name('events.show');
Route::get('/events', [PublicEventController::class, 'index'])->name('events.index');

// --- AUTHENTICATED USER ROUTES ---
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Dashboard & Profile
    Route::get('/dashboard', fn() => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // User-specific pages
    Route::get('/my-bookings', [MyBookingsController::class, 'index'])->name('my-bookings');
    Route::get('/my-wishlist', [MyWishlistController::class, 'index'])->name('my-wishlist.index');
    Route::get('/my-wallet', [MyWalletController::class, 'index'])->name('my-wallet');
    Route::get('/my-membership', [ProfileController::class, 'myMembership'])->name('my-membership');

    // Wishlist API-like routes (session-based)
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\WishlistController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\WishlistController::class, 'store']);
        Route::delete('/', [\App\Http\Controllers\Api\WishlistController::class, 'clear']);
        Route::delete('/{event}', [\App\Http\Controllers\Api\WishlistController::class, 'destroy']);
        Route::put('/{event}/toggle', [\App\Http\Controllers\Api\WishlistController::class, 'toggle']);
        Route::get('/{event}/check', [\App\Http\Controllers\Api\WishlistController::class, 'check']);
    });

    // Wallet API-like routes (session-based)
    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [\App\Http\Controllers\Api\WalletController::class, 'balance']);
        Route::get('/transactions', [\App\Http\Controllers\Api\WalletController::class, 'transactions']);
        Route::post('/add-points', [\App\Http\Controllers\Api\WalletController::class, 'addPoints']);
        Route::post('/add-kill-points', [\App\Http\Controllers\Api\WalletController::class, 'addKillPoints']);
        Route::post('/spend-points', [\App\Http\Controllers\Api\WalletController::class, 'spendPoints']);
        Route::post('/spend-kill-points', [\App\Http\Controllers\Api\WalletController::class, 'spendKillPoints']);
        Route::post('/transfer', [\App\Http\Controllers\Api\WalletController::class, 'transfer']);
    });

    // Booking Initiation
    Route::post('/bookings/initiate', [BookingController::class, 'initiateBooking'])->name('bookings.initiate');
});


// --- ADMIN ROUTES ---
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:' . RoleNameEnum::ADMIN->value])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('settings', [SiteSettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SiteSettingController::class, 'update'])->name('settings.update');

    // Explicit POST route for venue update must come before resource controller
    Route::post('venues/{venue}', [VenueController::class, 'update'])->name('venues.update.post');
    Route::resource('venues', VenueController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('tags', TagController::class);
    Route::resource('promotions', PromotionController::class);
    Route::post('editor/image-upload', [EditorUploadController::class, 'uploadImage'])->name('editor.image.upload');
    Route::resource('events', EventController::class)->except(['show']);
    Route::resource('events.occurrences', EventOccurrenceController::class)->shallow();
    Route::resource('ticket-definitions', TicketDefinitionController::class);
    Route::resource('bookings', AdminBookingController::class);
});


// --- ROLE-BASED ROUTES (Admin or Organizer) ---
Route::prefix('admin/qr-scanner')
    ->name('admin.qr-scanner.')
    ->middleware(['auth', 'role:' . RoleNameEnum::ADMIN->value . '|' . RoleNameEnum::ORGANIZER->value])
    ->group(function () {
        Route::get('/', [QrScannerController::class, 'index'])->name('index');
        Route::post('/validate', [QrScannerController::class, 'validateQrCode'])->name('validate');
        Route::post('/check-in', [QrScannerController::class, 'checkIn'])->name('check-in');
    });


// --- AUTH & PAYMENT ROUTES ---
require __DIR__ . '/auth.php';
require __DIR__ . '/settings.php'; // Note: This file seems to be required but its purpose is not clear from the context.

// Payment Gateway Callbacks
Route::get('/payment/success', [PaymentController::class, 'handlePaymentSuccess'])->name('payment.success');
Route::get('/payment/cancel', [PaymentController::class, 'handlePaymentCancel'])->name('payment.cancel');

// Membership Payment Gateway Callbacks
Route::get('/membership/payment/success', [MembershipPaymentController::class, 'handlePaymentSuccess'])->name('membership.payment.success');
Route::get('/membership/payment/cancel', [MembershipPaymentController::class, 'handlePaymentCancel'])->name('membership.payment.cancel');

// Stripe Webhook (must be outside CSRF protection)
Route::post('/webhook/stripe', [PaymentController::class, 'handleWebhook'])->name('webhook.stripe');
