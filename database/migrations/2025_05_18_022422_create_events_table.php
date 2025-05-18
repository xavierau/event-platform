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
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();

            $table->json('name'); // Translatable
            $table->json('slug'); // Translatable
            $table->json('description'); // Translatable
            $table->json('short_summary')->nullable(); // Translatable

            $table->string('event_status')->default('draft')->index();
            $table->string('visibility')->default('private')->index();
            $table->boolean('is_featured')->default(false)->index();

            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('website_url')->nullable();
            $table->json('social_media_links')->nullable();
            $table->string('youtube_video_id')->nullable();

            $table->json('cancellation_policy')->nullable(); // Translatable

            $table->json('meta_title')->nullable(); // Translatable
            $table->json('meta_description')->nullable(); // Translatable
            $table->json('meta_keywords')->nullable(); // Translatable

            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
