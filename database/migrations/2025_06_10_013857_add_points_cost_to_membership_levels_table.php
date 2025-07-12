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
            $table->integer('points_cost')->unsigned()->nullable()->after('price');
            $table->integer('kill_points_cost')->unsigned()->nullable()->after('points_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_levels', function (Blueprint $table) {
            $table->dropColumn(['points_cost', 'kill_points_cost']);
        });
    }
};
