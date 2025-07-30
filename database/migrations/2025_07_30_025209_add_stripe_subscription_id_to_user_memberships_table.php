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
        Schema::table('user_memberships', function (Blueprint $table) {
            $table->string('stripe_subscription_id')->nullable()->unique()->after('transaction_reference');
            $table->string('stripe_customer_id')->nullable()->after('stripe_subscription_id');
            $table->json('subscription_metadata')->nullable()->after('stripe_customer_id');
            
            $table->index('stripe_subscription_id');
            $table->index('stripe_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_memberships', function (Blueprint $table) {
            $table->dropIndex(['stripe_subscription_id']);
            $table->dropIndex(['stripe_customer_id']);
            $table->dropColumn(['stripe_subscription_id', 'stripe_customer_id', 'subscription_metadata']);
        });
    }
};