<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->json('redemption_methods')->nullable()->comment('Available redemption methods: qr, pin');
            $table->char('merchant_pin', 6)->nullable()->comment('6-digit PIN for PIN-based redemption');
        });

        // Set default value using raw SQL after column creation
        DB::table('coupons')->whereNull('redemption_methods')->update([
            'redemption_methods' => json_encode(['qr'])
        ]);

        // Make the column non-nullable after setting defaults
        Schema::table('coupons', function (Blueprint $table) {
            $table->json('redemption_methods')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['redemption_methods', 'merchant_pin']);
        });
    }
};
