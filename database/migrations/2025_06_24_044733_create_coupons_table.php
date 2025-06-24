<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Organizer;
use App\Modules\Coupon\Enums\CouponTypeEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Organizer::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('code')->unique();
            $table->string('type')->default(CouponTypeEnum::SINGLE_USE->value);
            $table->unsignedInteger('discount_value');
            $table->string('discount_type')->default('fixed'); // 'fixed' or 'percentage'
            $table->unsignedInteger('max_issuance')->nullable()->comment('How many times this coupon can be issued to users in total');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
