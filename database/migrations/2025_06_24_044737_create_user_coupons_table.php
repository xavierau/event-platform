<?php

use App\Models\User;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use App\Modules\Coupon\Models\Coupon;
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
        Schema::create('user_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Coupon::class)->constrained()->cascadeOnDelete();
            $table->string('unique_code')->unique();
            $table->string('status')->default(UserCouponStatusEnum::ACTIVE->value);
            $table->unsignedInteger('times_can_be_used')->default(1)->comment('How many times this specific coupon can be redeemed by the user');
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_coupons');
    }
};
