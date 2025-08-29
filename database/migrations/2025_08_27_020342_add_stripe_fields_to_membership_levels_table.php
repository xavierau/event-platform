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
        Schema::table('membership_levels', function (Blueprint $table) {
            $table->string('stripe_product_id')->nullable()->after('slug');
            $table->string('stripe_price_id')->nullable()->unique()->after('stripe_product_id');
            $table->index('stripe_price_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_levels', function (Blueprint $table) {
            $table->dropIndex(['stripe_price_id']);
            $table->dropColumn(['stripe_product_id', 'stripe_price_id']);
        });
    }
};
