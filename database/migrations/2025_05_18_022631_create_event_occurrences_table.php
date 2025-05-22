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
        Schema::create('event_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();

            $table->json('name')->nullable(); // Translatable
            $table->json('description')->nullable(); // Translatable

            $table->string('start_at')->nullable(); // Stores raw user input e.g., YYYY-MM-DDTHH:mm
            $table->string('end_at')->nullable();   // Stores raw user input e.g., YYYY-MM-DDTHH:mm

            $table->timestamp('start_at_utc')->nullable()->index(); // For UTC storage and comparison
            $table->timestamp('end_at_utc')->nullable()->index();   // For UTC storage and comparison

            $table->string('timezone')->default('Asia/Hong_Kong');

            $table->boolean('is_online')->default(false);
            $table->string('online_meeting_link')->nullable();

            $table->string('status')->default('scheduled');
            $table->integer('capacity')->nullable();
            $table->integer('max_tickets_per_user')->nullable()->default(10);
            $table->foreignId('parent_occurrence_id')->nullable()->constrained('event_occurrences')->nullOnDelete();

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
        Schema::dropIfExists('event_occurrences');
    }
};
