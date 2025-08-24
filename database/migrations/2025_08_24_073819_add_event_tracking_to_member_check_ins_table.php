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
        Schema::table('member_check_ins', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('event_occurrence_id')->nullable()->after('event_id');
            
            $table->foreign('event_id')->references('id')->on('events')->onDelete('set null');
            $table->foreign('event_occurrence_id')->references('id')->on('event_occurrences')->onDelete('set null');
            
            $table->index(['event_id', 'scanned_at']);
            $table->index(['event_occurrence_id', 'scanned_at']);
            $table->index(['user_id', 'event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_check_ins', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropForeign(['event_occurrence_id']);
            
            $table->dropIndex(['event_id', 'scanned_at']);
            $table->dropIndex(['event_occurrence_id', 'scanned_at']);
            $table->dropIndex(['user_id', 'event_id']);
            
            $table->dropColumn(['event_id', 'event_occurrence_id']);
        });
    }
};