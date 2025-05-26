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
        Schema::create('check_in_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete()->index();
            $table->timestamp('check_in_timestamp')->useCurrent();
            $table->string('method')->default('QR_SCAN'); // QR_SCAN, MANUAL_ENTRY, API_INTEGRATION
            $table->string('device_identifier')->nullable(); // UUID or serial number of scanning device
            $table->string('location_description')->nullable(); // e.g., "Main Entrance - Gate A"
            $table->foreignId('operator_user_id')->nullable()->constrained('users')->nullOnDelete(); // Staff member who processed check-in
            $table->string('status'); // SUCCESSFUL, FAILED_ALREADY_USED, FAILED_MAX_USES_REACHED, etc.
            $table->text('notes')->nullable(); // Additional remarks, manual override reasons
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_in_logs');
    }
};
