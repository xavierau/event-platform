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
        Schema::create('social_share_analytics', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship to shareable models (Event, etc.)
            $table->string('shareable_type');
            $table->unsignedBigInteger('shareable_id');
            $table->index(['shareable_type', 'shareable_id']);

            // Social platform (facebook, twitter, etc.)
            $table->enum('platform', ['facebook', 'twitter', 'linkedin', 'whatsapp', 'telegram', 'wechat', 'weibo', 'email']);

            // User information (nullable for anonymous shares)
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // Request information
            $table->ipAddress('ip_address');
            $table->text('user_agent')->nullable();
            $table->string('referrer', 2048)->nullable();

            // Additional metadata (JSON for flexibility)
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('platform');
            $table->index('created_at');
            $table->index(['platform', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_share_analytics');
    }
};
