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
        Schema::create('event_occurrence_ticket_definition', function (Blueprint $table) {
            $table->foreignId('event_occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignId('ticket_definition_id')->constrained('ticket_definitions')->cascadeOnDelete();

            $table->integer('quantity_for_occurrence')->unsigned()->nullable();
            $table->integer('price_override')->unsigned()->nullable(); // Price in smallest currency unit (e.g., cents)
            $table->string('availability_status')->nullable()->default('available'); // e.g., available, sold_out, coming_soon, off_sale

            $table->timestamps();

            // Composite primary key
            $table->primary(['event_occurrence_id', 'ticket_definition_id'], 'event_occurrence_ticket_definition_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_occurrence_ticket_definition');
    }
};
