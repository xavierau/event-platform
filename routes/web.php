<?php

use App\Enums\RoleNameEnum;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CmsPageController;
use App\Http\Controllers\Admin\ContactSubmissionController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\EditorUploadController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\EventOccurrenceController;
use App\Http\Controllers\Admin\MemberScannerController;
use App\Http\Controllers\Admin\OrganizerController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\QrScannerController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\TicketDefinitionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Modules\Membership\MembershipPaymentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Public\CmsPageController as PublicCmsPageController;
use App\Http\Controllers\Public\ContactUsController;
use App\Http\Controllers\Public\EventController as PublicEventController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\MyBookingsController;
use App\Http\Controllers\Public\MyCouponsController;
use App\Http\Controllers\Public\MyWalletController;
use App\Http\Controllers\Public\MyWishlistController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

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

// CMS Pages
Route::get('/pages/{slug}', [PublicCmsPageController::class, 'show'])->name('cms.pages.show');
Route::get('/pages', [PublicCmsPageController::class, 'index'])->name('cms.pages.index');

// Contact Form
Route::post('/contact-us', [ContactUsController::class, 'store'])->name('contact.store');

// Invitation Acceptance
Route::get('/invitation/accept', [\App\Http\Controllers\InvitationController::class, 'accept'])
    ->middleware('signed')
    ->name('invitation.accept');
Route::post('/invitation/complete-registration', [\App\Http\Controllers\InvitationController::class, 'completeRegistration'])
    ->name('invitation.complete-registration');

// --- AUTHENTICATED USER ROUTES ---
Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard & Profile
//    Route::get('/dashboard', fn() => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // User-specific pages
    Route::get('/my-bookings', [MyBookingsController::class, 'index'])->name('my-bookings');
    Route::get('/my-wishlist', [MyWishlistController::class, 'index'])->name('my-wishlist');
    Route::get('/my-wallet', [MyWalletController::class, 'index'])->name('my-wallet');
    Route::get('/my-coupons', [MyCouponsController::class, 'index'])->name('my-coupons');
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
        Route::post('/decode-qr', [\App\Http\Controllers\Api\WalletController::class, 'decodeQrCode']);
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

    // Comments
    Route::post('/events/{event}/comments', [CommentController::class, 'store'])->name('events.comments.store');
});


// --- ADMIN ROUTES ---
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:' . RoleNameEnum::ADMIN->value])
    ->group(function () {


        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
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
        Route::resource('organizers', OrganizerController::class);
        Route::post('organizers/{organizer}/invite', [OrganizerController::class, 'inviteUser'])->name('organizers.invite');

        // CMS Routes
        Route::resource('cms-pages', CmsPageController::class);
        Route::patch('cms-pages/{cmsPage}/toggle-publish', [CmsPageController::class, 'togglePublish'])->name('cms-pages.toggle-publish');
        Route::patch('cms-pages/sort-order', [CmsPageController::class, 'updateSortOrder'])->name('cms-pages.sort-order');

        // Contact Submissions
        Route::resource('contact-submissions', ContactSubmissionController::class)->only(['index', 'show', 'destroy']);
        Route::patch('contact-submissions/{submission}/toggle-read', [ContactSubmissionController::class, 'toggleRead'])->name('contact-submissions.toggle-read');

        // Comments
        Route::get('/events/{event}/comments/moderation', [CommentController::class, 'indexForModeration']);
        Route::post('/comments/{comment}/approve', [CommentController::class, 'approve']);
        Route::put('/comments/{comment}/reject', [CommentController::class, 'reject']);
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

        // User Management
        Route::resource('users', UserController::class)->middleware('permission:manage-users');

        Route::resource('coupons', CouponController::class);
        Route::get('coupon-scanner', [CouponController::class, 'scanner'])->name('coupons.scanner');

        // Mass Coupon Assignment routes
        Route::prefix('coupon-assignment')
            ->name('coupon-assignment.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\CouponAssignmentController::class, 'index'])->name('index');
            Route::post('/search-users', [App\Http\Controllers\Admin\CouponAssignmentController::class, 'searchUsers'])->name('search-users');
            Route::post('/user-stats', [App\Http\Controllers\Admin\CouponAssignmentController::class, 'getUserStats'])->name('user-stats');
            Route::post('/assign', [App\Http\Controllers\Admin\CouponAssignmentController::class, 'assign'])->name('assign');
            Route::get('/history', [App\Http\Controllers\Admin\CouponAssignmentController::class, 'history'])->name('history');
        });

    });


// --- ROLE-BASED ROUTES (Admin or Users with Organizer Entity Membership) ---
Route::prefix('admin/qr-scanner')
    ->name('admin.qr-scanner.')
    ->middleware(['auth'])
    ->group(function () {
        Route::get('/', [QrScannerController::class, 'index'])->name('index');
        Route::post('/validate', [QrScannerController::class, 'validateQrCode'])->name('validate');
        Route::post('/check-in', [QrScannerController::class, 'checkIn'])->name('check-in');
    });

Route::prefix('admin/member-scanner')
    ->name('admin.member-scanner.')
    ->middleware(['auth'])
    ->group(function () {
        Route::get('/', [MemberScannerController::class, 'index'])->name('index');
        Route::post('/validate', [MemberScannerController::class, 'validateMember'])->name('validate');
        Route::post('/check-in', [MemberScannerController::class, 'checkIn'])->name('check-in');
        Route::get('/history/{member}', [MemberScannerController::class, 'getCheckInHistory'])->name('history');
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
Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook']);
