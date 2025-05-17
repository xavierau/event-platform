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
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // Translatable
            $table->json('description')->nullable(); // Translatable
            $table->string('slug')->unique();

            $table->foreignId('organizer_id')->nullable()->constrained('users')->onDelete('set null'); // Assuming organizers are users

            $table->json('address_line_1'); // Translatable
            $table->json('address_line_2')->nullable(); // Translatable
            $table->json('city'); // Translatable
            $table->string('postal_code')->nullable();
            $table->foreignId('state_id')->nullable()->constrained('states')->onDelete('set null');
            $table->foreignId('country_id')->constrained('countries')->onDelete('cascade');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('website_url')->nullable();
            $table->integer('seating_capacity')->unsigned()->nullable();

            $table->json('images')->nullable(); // For storing array of image paths/details
            $table->string('thumbnail_image_path')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
