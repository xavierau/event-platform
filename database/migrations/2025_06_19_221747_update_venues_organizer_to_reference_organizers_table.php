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
        // First, set all existing venues as public (organizer_id = null)
        // This allows all organizers to use them until specific assignments are made
        DB::table('venues')->update(['organizer_id' => null]);

        // Now update the foreign key constraint
        Schema::table('venues', function (Blueprint $table) {
            // Drop the existing foreign key constraint to users
            $table->dropForeign(['organizer_id']);

            // Add the new foreign key constraint to organizers (nullable for public venues)
            $table->foreign('organizer_id')->references('id')->on('organizers')->onDelete('set null');

            // Add indexes for performance
            $table->index(['organizer_id', 'is_active'], 'venues_organizer_active_index');
            $table->index(['is_active'], 'venues_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            // Drop the performance indexes
            $table->dropIndex('venues_organizer_active_index');
            $table->dropIndex('venues_active_index');

            // Drop the foreign key constraint to organizers
            $table->dropForeign(['organizer_id']);

            // Restore the foreign key constraint to users
            // Note: This will fail if there are venues with organizer_id that don't exist in users table
            $table->foreign('organizer_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};
