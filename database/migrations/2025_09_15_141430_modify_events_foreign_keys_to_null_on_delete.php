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
        Schema::table('events', function (Blueprint $table) {
            // Drop existing foreign key constraints
            $table->dropForeign(['organizer_id']);
            $table->dropForeign(['category_id']);

            // Recreate foreign key constraints with nullOnDelete
            $table->foreignId('organizer_id')->nullable()->change();
            $table->foreignId('category_id')->nullable()->change();

            $table->foreign('organizer_id')->references('id')->on('organizers')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Drop current foreign key constraints
            $table->dropForeign(['organizer_id']);
            $table->dropForeign(['category_id']);

            // Recreate original foreign key constraints with cascadeOnDelete
            $table->foreignId('organizer_id')->nullable(false)->change();
            $table->foreignId('category_id')->nullable(false)->change();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }
};