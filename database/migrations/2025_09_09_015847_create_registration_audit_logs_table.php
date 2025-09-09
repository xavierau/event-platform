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
        Schema::create('registration_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('flow_id')->index(); // UUID to track entire registration flow
            $table->string('step', 50)->index(); // step in registration process
            $table->string('action', 100)->index(); // action taken (e.g., form_submitted, validation_failed, stripe_checkout_created)
            $table->string('status', 50)->index(); // success, failed, pending
            $table->text('message'); // human readable description
            $table->json('request_data')->nullable(); // sanitized request data
            $table->json('response_data')->nullable(); // sanitized response data
            $table->json('metadata')->nullable(); // additional context (user_agent, ip, etc.)
            $table->bigInteger('user_id')->unsigned()->nullable()->index();
            $table->foreign('user_id', 'fk_reg_audit_logs_user')->references('id')->on('users')->nullOnDelete();
            $table->string('email', 255)->nullable()->index(); // track by email even before user creation
            $table->string('selected_plan', 255)->nullable()->index(); // membership level chosen
            $table->text('error_message')->nullable(); // detailed error for debugging
            $table->string('stripe_session_id')->nullable()->index(); // link to stripe session
            $table->timestamps();

            // Indexes for performance
            $table->index(['flow_id', 'created_at']);
            $table->index(['step', 'status']);
            $table->index(['action', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['email', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_audit_logs');
    }
};
