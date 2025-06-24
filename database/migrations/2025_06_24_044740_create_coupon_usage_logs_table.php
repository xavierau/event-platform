<?php

use App\Models\User;
use App\Modules\Coupon\Models\UserCoupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupon_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(UserCoupon::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'redeemed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('redeemed_at')->useCurrent();
            $table->text('context')->nullable()->comment('JSON context, e.g., device info from QR scanner');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usage_logs');
    }
};
