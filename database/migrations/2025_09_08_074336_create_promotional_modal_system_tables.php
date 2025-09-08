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
        // Create promotional_modals table first
        Schema::create('promotional_modals', function (Blueprint $table) {
            $table->id();
            
            // Basic fields
            $table->json('title'); // translatable
            $table->json('content'); // translatable
            $table->enum('type', ['modal', 'banner'])->default('modal');
            
            // Display rules
            $table->json('pages')->nullable(); // which pages to show on
            $table->json('membership_levels')->nullable(); // which membership levels
            $table->json('user_segments')->nullable(); // user segmentation rules
            
            // Timing controls
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->enum('display_frequency', ['once', 'daily', 'weekly', 'always'])->default('once');
            $table->integer('cooldown_hours')->default(24); // hours between displays
            
            // Analytics
            $table->unsignedInteger('impressions_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0.00);
            
            // Status and ordering
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // higher numbers = higher priority
            $table->integer('sort_order')->default(0);
            
            // Additional fields
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();
            $table->boolean('is_dismissible')->default(true);
            $table->json('display_conditions')->nullable(); // additional conditions
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['is_active', 'start_at', 'end_at']);
            $table->index(['priority', 'sort_order']);
            $table->index('type');
        });

        // Create promotional_modal_impressions table
        Schema::create('promotional_modal_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotional_modal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable(); // for anonymous users
            $table->string('action')->default('impression'); // impression, click, dismiss
            $table->string('page_url')->nullable();
            $table->json('metadata')->nullable(); // additional tracking data
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at');
            
            // Indexes for analytics with shorter names
            $table->index(['promotional_modal_id', 'action', 'created_at'], 'promo_modal_action_time_idx');
            $table->index(['user_id', 'promotional_modal_id'], 'user_promo_modal_idx');
            $table->index(['session_id', 'promotional_modal_id'], 'session_promo_modal_idx');
            $table->index('created_at', 'impressions_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotional_modal_impressions');
        Schema::dropIfExists('promotional_modals');
    }
};
