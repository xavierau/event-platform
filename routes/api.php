<?php

use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CommentApiController;
use App\Http\Controllers\Api\FrontendLogController;

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

// Sanctum-protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Event Comments
    Route::get('/events/{event}/comments', [CommentApiController::class, 'index']);
    Route::post('/events/{event}/comments', [CommentApiController::class, 'store']);
    Route::put('/comments/{comment}', [CommentApiController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentApiController::class, 'destroy']);

    // Comment Moderation
    Route::post('/comments/{comment}/approve', [CommentApiController::class, 'approve'])->name('api.comments.approve');
    Route::post('/comments/{comment}/reject', [CommentApiController::class, 'reject'])->name('api.comments.reject');
});


Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.api.')->group(function () {
    // Comment Moderation
    Route::get('/events/{event}/comments', [CommentApiController::class, 'indexForModeration'])->name('events.comments.indexForModeration');
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
