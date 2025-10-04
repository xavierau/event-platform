<?php

use App\Http\Controllers\Api\BookingSeatController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\FrontendLogController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\CommentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Frontend logging (public for production tracking)
Route::post('/frontend-logs', [FrontendLogController::class, 'store'])->name('api.frontend-logs');

// Chatbot API (public with CSRF protection)
Route::post('/chatbot', [ChatbotController::class, 'store'])->name('api.chatbot.store');
Route::post('/chatbot/messages', [ChatbotController::class, 'messages'])->name('api.chatbot.messages');

// Public Comments API (for viewing)
Route::get('/comments', [CommentController::class, 'index'])->name('api.comments.index');

// Sanctum-protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Protected Comments API (for posting)
    Route::post('/comments', [CommentController::class, 'store'])->name('api.comments.store');

    // Comments for Events
    Route::get('/events/{event}/comments', [CommentController::class, 'indexForEvent'])->name('api.events.comments.index');
    Route::post('/events/{event}/comments', [CommentController::class, 'storeForEvent'])->name('api.events.comments.store');

    // Comments for Organizers
    Route::get('/organizers/{organizer}/comments', [CommentController::class, 'indexForOrganizer'])->name('api.organizers.comments.index');
    Route::post('/organizers/{organizer}/comments', [CommentController::class, 'storeForOrganizer'])->name('api.organizers.comments.store');

    // Comment management (applies to any comment regardless of parent)
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('api.comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('api.comments.destroy');

    // Comment Voting
    Route::post('/comments/{comment}/vote', [CommentController::class, 'vote'])->name('api.comments.vote');

    // Comment Moderation
    Route::post('/comments/{comment}/approve', [CommentController::class, 'approve'])->name('api.comments.approve');
    Route::post('/comments/{comment}/reject', [CommentController::class, 'reject'])->name('api.comments.reject');
    Route::post('/comments/{comment}/flag', [CommentController::class, 'flag'])->name('api.comments.flag');

    // Booking Seat Assignment
    Route::post('/bookings/{booking}/seat', [BookingSeatController::class, 'assign'])->name('api.bookings.seat.assign');
    Route::delete('/bookings/{booking}/seat', [BookingSeatController::class, 'remove'])->name('api.bookings.seat.remove');
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.api.')->group(function () {
    // Comment Moderation
    Route::get('/comments/pending', [CommentController::class, 'pending'])->name('comments.pending'); // ?commentable_type=App\Models\Event&commentable_id=1
});

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Membership routes
    Route::get('/memberships/levels', [MembershipController::class, 'getMembershipLevels']);
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/memberships/my-membership', [MembershipController::class, 'getMyMembership']);
        Route::post('/memberships/purchase', [MembershipController::class, 'purchaseMembership']);
        Route::post('/memberships/renew', [MembershipController::class, 'renewMembership']);
        Route::delete('/memberships/cancel', [MembershipController::class, 'cancelMembership']);
    });

    // Coupon Scanner
    Route::get('/coupon-scanner/{uniqueCode}', [\App\Http\Controllers\Api\V1\CouponScannerController::class, 'show'])->name('coupon-scanner.show');
    Route::post('/coupon-scanner/{uniqueCode}/redeem', [\App\Http\Controllers\Api\V1\CouponScannerController::class, 'store'])->name('coupon-scanner.redeem');

    // PIN-based Coupon Redemption
    Route::post('/coupons/validate-pin', [\App\Http\Controllers\Api\V1\CouponScannerController::class, 'validatePin'])->name('coupons.validate-pin');
    Route::post('/coupons/redeem-by-pin', [\App\Http\Controllers\Api\V1\CouponScannerController::class, 'redeemByPin'])->name('coupons.redeem-by-pin');
});

// Note: Wishlist routes moved to web.php for session-based authentication
