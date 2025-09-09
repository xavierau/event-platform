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
        Schema::create('frontend_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 10)->index(); // debug, info, warn, error
            $table->string('component', 100)->index();
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('client_timestamp');
            $table->text('url');
            $table->text('user_agent');
            $table->string('ip_address', 45)->nullable();
            $table->string('session_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['level', 'created_at']);
            $table->index(['component', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frontend_logs');
    }
};
