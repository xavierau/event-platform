<?php

use App\Enums\RoleNameEnum;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CheckInRecordsController;
use App\Http\Controllers\Admin\CmsPageController;
use App\Http\Controllers\Admin\ContactSubmissionController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\EditorUploadController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\EventOccurrenceController;
use App\Http\Controllers\Admin\EventSeoController;
use App\Http\Controllers\Admin\MemberScannerController;
use App\Http\Controllers\Admin\MembershipLevelController;
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
Route::get('/organizers/{organizer}', [\App\Http\Controllers\Public\OrganizerController::class, 'show'])->name('organizers.show');

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

// --- AUTHENTICATED USER ROUTES (Basic Features) ---
Route::middleware(['auth'])->group(function () {
    // Wishlist features - allow without verification
    Route::get('/my-wishlist', [MyWishlistController::class, 'index'])->name('my-wishlist');

    // Wishlist API-like routes (session-based)
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\WishlistController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\WishlistController::class, 'store']);
        Route::delete('/', [\App\Http\Controllers\Api\WishlistController::class, 'clear']);
        Route::delete('/{event}', [\App\Http\Controllers\Api\WishlistController::class, 'destroy']);
        Route::put('/{event}/toggle', [\App\Http\Controllers\Api\WishlistController::class, 'toggle']);
        Route::get('/{event}/check', [\App\Http\Controllers\Api\WishlistController::class, 'check']);
    });

    // Profile management - requires verification
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Financial and booking features - require verification
    Route::get('/my-bookings', [MyBookingsController::class, 'index'])->name('my-bookings');
    Route::get('/my-wallet', [MyWalletController::class, 'index'])->name('my-wallet');
    Route::get('/my-coupons', [MyCouponsController::class, 'index'])->name('my-coupons');
    Route::get('/my-membership', [ProfileController::class, 'myMembership'])->name('my-membership');

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
});

// --- VERIFIED USER ROUTES (Sensitive Features) ---
Route::middleware(['auth', 'verified'])->group(function () {

    // Comments
    Route::post('/events/{event}/comments', [CommentController::class, 'store'])->name('events.comments.store');
});

// --- SHARED ADMIN ROUTES (Platform Admins OR Organizer Members) ---
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth'])
    ->group(function () {
        // Dashboard and core functionality
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Events and related resources
        Route::post('editor/image-upload', [EditorUploadController::class, 'uploadImage'])->name('editor.image.upload');
        Route::resource('events', EventController::class)->except(['show']);
        Route::resource('events.occurrences', EventOccurrenceController::class)->shallow();

        // Event SEO routes
        Route::get('events/{event}/seo', [EventSeoController::class, 'show'])->name('events.seo.show');
        Route::get('events/{event}/seo/edit', [EventSeoController::class, 'edit'])->name('events.seo.edit');
        Route::post('events/{event}/seo', [EventSeoController::class, 'store'])->name('events.seo.store');
        Route::put('events/{event}/seo', [EventSeoController::class, 'update'])->name('events.seo.update');
        Route::delete('events/{event}/seo', [EventSeoController::class, 'destroy'])->name('events.seo.destroy');
        Route::get('events/{event}/seo/data', [EventSeoController::class, 'getSeoData'])->name('events.seo.data');
        Route::get('events/{event}/seo/preview', [EventSeoController::class, 'preview'])->name('events.seo.preview');
        Route::post('events/seo/validate', [EventSeoController::class, 'validateLimits'])->name('events.seo.validate');
        Route::resource('ticket-definitions', TicketDefinitionController::class);
        Route::resource('bookings', AdminBookingController::class);

        // Manual booking endpoints
        Route::get('events/{event}/ticket-definitions', [AdminBookingController::class, 'getTicketDefinitions'])
            ->name('events.ticket-definitions');
        Route::post('bookings/search-users', [AdminBookingController::class, 'searchUsers'])
            ->name('bookings.search-users');

        // Organizer management
        Route::resource('organizers', OrganizerController::class);
        Route::post('organizers/{organizer}/invite', [OrganizerController::class, 'inviteUser'])->name('organizers.invite');

        // Venues
        Route::post('venues/{venue}', [VenueController::class, 'update'])->name('venues.update.post');
        Route::resource('venues', VenueController::class);

        // Coupons
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

        // Comments moderation (for events)
        Route::get('/events/{event}/comments/moderation', [CommentController::class, 'indexForModeration']);
        Route::post('/comments/{comment}/approve', [CommentController::class, 'approve']);
        Route::put('/comments/{comment}/reject', [CommentController::class, 'reject']);
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

        // Scanner tools
        Route::prefix('qr-scanner')->name('qr-scanner.')->group(function () {
            Route::get('/', [QrScannerController::class, 'index'])->name('index');
            Route::post('/validate', [QrScannerController::class, 'validateQrCode'])->name('validate');
            Route::post('/check-in', [QrScannerController::class, 'checkIn'])->name('check-in');
        });

        Route::prefix('member-scanner')->name('member-scanner.')->group(function () {
            Route::get('/', [MemberScannerController::class, 'index'])->name('index');
            Route::post('/validate', [MemberScannerController::class, 'validateMember'])->name('validate');
            Route::post('/check-in', [MemberScannerController::class, 'checkIn'])->name('check-in');
            Route::get('/history/{member}', [MemberScannerController::class, 'getCheckInHistory'])->name('history');
        });

        // Check-in records management
        Route::prefix('check-in-records')->name('check-in-records.')->group(function () {
            Route::get('/', [CheckInRecordsController::class, 'index'])->name('index');
            Route::get('/export', [CheckInRecordsController::class, 'export'])->name('export');
        });
    });

// --- PLATFORM ADMIN ONLY ROUTES ---
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:' . RoleNameEnum::ADMIN->value])
    ->group(function () {
        // Site-wide settings and management
        Route::get('settings', [SiteSettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SiteSettingController::class, 'update'])->name('settings.update');

        // Platform-wide taxonomies
        Route::resource('categories', CategoryController::class);
        Route::resource('tags', TagController::class);
        Route::resource('promotions', PromotionController::class);

        // CMS Routes
        Route::resource('cms-pages', CmsPageController::class);
        Route::patch('cms-pages/{cmsPage}/toggle-publish', [CmsPageController::class, 'togglePublish'])->name('cms-pages.toggle-publish');
        Route::patch('cms-pages/sort-order', [CmsPageController::class, 'updateSortOrder'])->name('cms-pages.sort-order');

        // Contact Submissions
        Route::resource('contact-submissions', ContactSubmissionController::class)->only(['index', 'show', 'destroy']);
        Route::patch('contact-submissions/{submission}/toggle-read', [ContactSubmissionController::class, 'toggleRead'])->name('contact-submissions.toggle-read');

        // User Management
        Route::get('users/metrics', [UserController::class, 'metrics'])->name('users.metrics')->middleware('permission:manage-users');
        Route::resource('users', UserController::class)->middleware('permission:manage-users');
        Route::post('users/{user}/change-membership', [UserController::class, 'changeMembership'])->name('users.change-membership')->middleware('permission:manage-users');

        // Membership Levels Management
        Route::resource('membership-levels', MembershipLevelController::class);
        Route::get('membership-levels/{membershipLevel}/users', [MembershipLevelController::class, 'users'])->name('membership-levels.users');
        Route::post('membership-levels/{membershipLevel}/sync-stripe', [MembershipLevelController::class, 'syncWithStripe'])->name('membership-levels.sync-stripe');
        Route::post('membership-levels/sync-all-stripe', [MembershipLevelController::class, 'syncWithStripe'])->name('membership-levels.sync-all-stripe');
        Route::post('users/{user}/change-plan', [MembershipLevelController::class, 'changeUserPlan'])->name('admin.users.change-plan');
        Route::post('membership-levels/bulk-change-plan', [MembershipLevelController::class, 'bulkChangePlan'])->name('membership-levels.bulk-change-plan');
    });

// --- AUTH & PAYMENT ROUTES ---
require __DIR__ . '/auth.php';
require __DIR__ . '/settings.php'; // Note: This file seems to be required but its purpose is not clear from the context.
require __DIR__ . '/promotional-modal.php';

// Payment Gateway Callbacks
Route::get('/payment/success', [PaymentController::class, 'handlePaymentSuccess'])->name('payment.success');
Route::get('/payment/cancel', [PaymentController::class, 'handlePaymentCancel'])->name('payment.cancel');

// Membership Payment Gateway Callbacks
Route::get('/membership/payment/success', [MembershipPaymentController::class, 'handlePaymentSuccess'])->name('membership.payment.success');
Route::get('/membership/payment/cancel', [MembershipPaymentController::class, 'handlePaymentCancel'])->name('membership.payment.cancel');

// Stripe Webhook (must be outside CSRF protection)
Route::post('/webhook/stripe', [PaymentController::class, 'handleWebhook'])->name('webhook.stripe');
Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook']);
