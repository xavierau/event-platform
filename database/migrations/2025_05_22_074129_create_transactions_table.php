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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('total_amount'); // In cents
            $table->string('currency', 3);
            $table->string('status')->default('pending'); // e.g., pending, completed, failed, refunded
            $table->string('payment_gateway')->nullable();
            $table->string('payment_gateway_transaction_id')->nullable()->unique(); // This will store the Stripe Session ID or other gateway's transaction ID
            $table->string('payment_intent_id')->nullable()->after('payment_gateway_transaction_id')->index(); // This can remain for Stripe specific payment intent, or be generalized if other gateways have a similar concept
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
