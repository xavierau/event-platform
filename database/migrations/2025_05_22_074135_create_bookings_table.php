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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('booking_number')->unique();
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_definition_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->integer('price_at_booking'); // Price per ticket at the time of booking, in cents
            $table->string('currency_at_booking', 3);
            $table->string('status')->default('confirmed'); // e.g., confirmed, cancelled, used
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
