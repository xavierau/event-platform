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
        Schema::table('check_in_logs', function (Blueprint $table) {
            // Add event_occurrence_id to track which specific occurrence was checked into
            $table->foreignId('event_occurrence_id')->after('booking_id')->constrained('event_occurrences')->cascadeOnDelete()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_in_logs', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['event_occurrence_id']);

            // Drop the column
            $table->dropColumn('event_occurrence_id');
        });
    }
};
