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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('qr_code_identifier')->unique()->nullable()->index()->after('metadata');
            $table->integer('max_allowed_check_ins')->default(1)->after('qr_code_identifier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['qr_code_identifier']);
            $table->dropColumn(['qr_code_identifier', 'max_allowed_check_ins']);
        });
    }
};
