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
        Schema::create('user_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('membership_level_id')->constrained()->onDelete('cascade');
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active'); // active, expired, cancelled, pending, suspended
            $table->string('payment_method'); // points, kill_points, stripe, admin_grant, promotional
            $table->string('transaction_reference')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'expires_at']);
            $table->index('membership_level_id');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_memberships');
    }
};
