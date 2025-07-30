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
        Schema::create('member_check_ins', function (Blueprint $table) {
            $table->id();
            
            // Core relationships
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade')
                ->comment('Member being scanned');
                
            $table->foreignId('scanned_by_user_id')
                ->constrained('users', 'id')
                ->onDelete('cascade')
                ->comment('Admin/staff performing scan');
            
            // Check-in details
            $table->timestamp('scanned_at')
                ->comment('Check-in timestamp');
                
            $table->string('location', 255)
                ->nullable()
                ->comment('Check-in location');
                
            $table->text('notes')
                ->nullable()
                ->comment('Additional notes');
                
            $table->string('device_identifier', 255)
                ->nullable()
                ->comment('Device/terminal used');
                
            $table->json('membership_data')
                ->nullable()
                ->comment('QR membership data for audit');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'scanned_at'], 'idx_user_scanned_at');
            $table->index('scanned_by_user_id', 'idx_scanned_by');
            $table->index('scanned_at', 'idx_scanned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_check_ins');
    }
};
