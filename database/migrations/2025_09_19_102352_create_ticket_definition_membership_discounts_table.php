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
        Schema::create('ticket_definition_membership_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_definition_id');
            $table->unsignedBigInteger('membership_level_id');
            $table->enum('discount_type', ['percentage', 'fixed'])
                ->comment('percentage: discount as percentage (e.g., 10 for 10%), fixed: discount in cents');
            $table->integer('discount_value')
                ->unsigned()
                ->comment('Percentage (0-100) or amount in cents');
            $table->timestamps();

            // Ensure unique combinations
            $table->unique(['ticket_definition_id', 'membership_level_id'], 'ticket_membership_unique');

            // Foreign key constraints with shorter names
            $table->foreign('ticket_definition_id', 'tdmd_ticket_def_fk')
                ->references('id')
                ->on('ticket_definitions')
                ->onDelete('cascade');

            $table->foreign('membership_level_id', 'tdmd_membership_fk')
                ->references('id')
                ->on('membership_levels')
                ->onDelete('cascade');

            // Index for performance with shorter name
            $table->index(['membership_level_id', 'discount_type'], 'tdmd_membership_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_definition_membership_discounts');
    }
};
