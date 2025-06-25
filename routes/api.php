<?php

use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
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

// Sanctum-protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
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
});

// Note: Wishlist routes moved to web.php for session-based authentication
