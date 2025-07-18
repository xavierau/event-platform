<?php

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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('organizers')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('code')->unique();
            $table->enum('type', ['single_use', 'multi_use']);
            $table->integer('discount_value'); // in cents for fixed, or percentage value
            $table->enum('discount_type', ['fixed', 'percentage']);
            $table->integer('max_issuance')->nullable(); // null = unlimited
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('redemption_methods'); // Default to QR
            $table->char('merchant_pin', 6)->nullable()->comment('6-digit PIN for PIN-based redemption');
            $table->timestamps();

            $table->index(['organizer_id', 'code']);
            $table->index('expires_at');
            $table->index('valid_from');
        });

        Schema::create('user_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('coupon_id')->constrained('coupons')->onDelete('cascade');
            $table->string('unique_code', 12)->unique();
            $table->enum('status', ['active', 'fully_used', 'expired'])->default('active');
            $table->integer('times_can_be_used')->default(1);
            $table->integer('times_used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('unique_code');
            $table->index('expires_at');
        });

        Schema::create('coupon_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_coupon_id')->constrained('user_coupons')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('used_at');
            $table->string('location')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['user_coupon_id', 'used_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usage_logs');
        Schema::dropIfExists('user_coupons');
        Schema::dropIfExists('coupons');
    }
};
