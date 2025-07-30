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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            
            // Stripe webhook event identification
            $table->string('stripe_event_id')->unique()->index();
            $table->string('event_type')->index(); // e.g., 'customer.subscription.created'
            $table->timestamp('stripe_created_at')->index();
            
            // Processing status tracking
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'ignored'])->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            $table->integer('retry_count')->default(0);
            
            // Event data and context
            $table->json('payload'); // Full Stripe event payload
            $table->json('metadata')->nullable(); // Additional processing metadata
            
            // Error tracking
            $table->text('error_message')->nullable();
            $table->text('error_trace')->nullable();
            
            // Processing metrics
            $table->integer('processing_time_ms')->nullable(); // Processing duration
            $table->string('processed_by')->nullable(); // Handler class/method
            
            $table->timestamps();
            
            // Indexes for common queries
            $table->index(['event_type', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['stripe_created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};