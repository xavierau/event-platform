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

        // fix following error
        //           SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'event_o
        //   ccurrence_id' (Connection: mysql, SQL: alter table `check_in_logs` add `eve
        //   nt_occurrence_id` bigint unsigned not null after `booking_id`)

        // Check if column exists and drop it if it does
        if (Schema::hasColumn('check_in_logs', 'event_occurrence_id')) {
            Schema::table('check_in_logs', function (Blueprint $table) {
                $table->dropColumn('event_occurrence_id');
            });
        }

        Schema::table('check_in_logs', function (Blueprint $table) {
            // Add event_occurrence_id to track which specific occurrence was checked into
            $table->foreignId('event_occurrence_id')->after('booking_id')->index();
            $table->foreign('event_occurrence_id', 'fk_check_in_logs_event_occurrence_id')
                ->references('id')->on('event_occurrences')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_in_logs', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign('fk_check_in_logs_event_occurrence_id');

            // Drop the column
            $table->dropColumn('event_occurrence_id');
        });
    }
};
