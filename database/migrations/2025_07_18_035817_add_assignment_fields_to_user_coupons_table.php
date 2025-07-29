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
        Schema::table('user_coupons', function (Blueprint $table) {
            // Assignment tracking fields
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('assignment_method', ['auto', 'manual'])->default('auto');
            $table->string('assignment_reason')->nullable();
            $table->text('assignment_notes')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamp('acquired_at')->nullable();
            
            // Update status enum to include 'available' status
            $table->enum('status', ['available', 'active', 'used', 'expired', 'fully_used'])->default('available')->change();
            
            // Add index for assignment queries
            $table->index(['assigned_by', 'assignment_method']);
            $table->index('acquired_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_coupons', function (Blueprint $table) {
            // Remove the new fields
            $table->dropForeign(['assigned_by']);
            $table->dropColumn([
                'assigned_by',
                'assignment_method',
                'assignment_reason',
                'assignment_notes',
                'quantity',
                'acquired_at',
            ]);
            
            // Revert status enum to original values
            $table->enum('status', ['active', 'fully_used', 'expired'])->default('active')->change();
            
            // Remove indexes
            $table->dropIndex(['assigned_by', 'assignment_method']);
            $table->dropIndex(['acquired_at']);
        });
    }
};