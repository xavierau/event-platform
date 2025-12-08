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
        Schema::create('temporary_registration_pages', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('slug')->unique()->nullable();
            $table->string('token', 64)->unique();
            $table->foreignId('membership_level_id')
                ->constrained('membership_levels')
                ->onDelete('restrict');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_registrations')->nullable();
            $table->unsignedInteger('registrations_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('use_slug')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['slug', 'is_active']);
            $table->index(['token', 'is_active']);
            $table->index('expires_at');
            $table->index('membership_level_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_registration_pages');
    }
};
