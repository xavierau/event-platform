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
        Schema::create('organizer_users', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Role and permissions
            $table->string('role_in_organizer'); // owner, manager, staff, viewer
            $table->json('permissions')->nullable(); // Custom permissions beyond role

            // Team membership details
            $table->timestamp('joined_at')->nullable();
            $table->boolean('is_active')->default(true);

            // Invitation system
            $table->foreignId('invited_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('invitation_accepted_at')->nullable();

            $table->timestamps();

            // Unique constraint to prevent duplicate memberships
            $table->unique(['organizer_id', 'user_id']);

            // Indexes
            $table->index(['organizer_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
            $table->index(['role_in_organizer']);
            $table->index(['invited_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizer_users');
    }
};
