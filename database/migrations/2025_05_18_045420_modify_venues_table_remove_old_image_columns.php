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
        Schema::table('venues', function (Blueprint $table) {
            if (Schema::hasColumn('venues', 'images')) {
                $table->dropColumn('images');
            }
            if (Schema::hasColumn('venues', 'thumbnail_image_path')) {
                $table->dropColumn('thumbnail_image_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->json('images')->nullable()->after('seating_capacity'); // Approximate original position
            $table->string('thumbnail_image_path')->nullable()->after('images');
        });
    }
};
