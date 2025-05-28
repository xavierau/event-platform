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
        Schema::create('membership_levels', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // Translatable field
            $table->string('slug')->unique();
            $table->json('description')->nullable(); // Translatable field
            $table->integer('price'); // In smallest currency unit (e.g., cents)
            $table->integer('duration_months');
            $table->json('benefits')->nullable(); // Array of benefits
            $table->integer('max_users')->nullable(); // Null means unlimited
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_levels');
    }
};
