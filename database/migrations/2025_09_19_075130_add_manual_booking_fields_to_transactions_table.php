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
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_manual_booking')->default(false);
            $table->text('admin_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['created_by_admin_id']);
            $table->dropColumn(['created_by_admin_id', 'is_manual_booking', 'admin_notes']);
        });
    }
};
