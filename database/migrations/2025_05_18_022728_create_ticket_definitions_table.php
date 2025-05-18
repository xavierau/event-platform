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
        Schema::create('ticket_definitions', function (Blueprint $table) {
            $table->id();

            $table->json('name'); // Translatable
            $table->json('description')->nullable(); // Translatable
            $table->integer('price')->unsigned(); // In cents
            $table->integer('total_quantity')->unsigned()->nullable();
            $table->string('availability_window_start')->nullable(); // Raw user input
            $table->string('availability_window_end')->nullable();   // Raw user input
            $table->timestamp('availability_window_start_utc')->nullable()->index();
            $table->timestamp('availability_window_end_utc')->nullable()->index();
            $table->integer('min_per_order')->unsigned()->default(1);
            $table->integer('max_per_order')->unsigned()->nullable();
            $table->string('status')->default('active'); // e.g., active, inactive, archived
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes(); // Added soft deletes as it's common
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_definitions');
    }
};
