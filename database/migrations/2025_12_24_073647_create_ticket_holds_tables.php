<?php

use App\Modules\TicketHold\Enums\HoldStatusEnum;
use App\Modules\TicketHold\Enums\LinkStatusEnum;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
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
        // 1. ticket_holds - Main table for ticket holds
        Schema::create('ticket_holds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignId('organizer_id')->nullable()->constrained('organizers')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('status')->default(HoldStatusEnum::ACTIVE->value);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['event_occurrence_id', 'status']);
            $table->index(['organizer_id', 'status']);
            $table->index('status');
            $table->index('expires_at');
        });

        // 2. hold_ticket_allocations - Ticket allocations within a hold
        Schema::create('hold_ticket_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_hold_id')->constrained('ticket_holds')->cascadeOnDelete();
            $table->foreignId('ticket_definition_id')->constrained('ticket_definitions')->cascadeOnDelete();
            $table->unsignedInteger('allocated_quantity');
            $table->unsignedInteger('purchased_quantity')->default(0);
            $table->string('pricing_mode')->default(PricingModeEnum::ORIGINAL->value);
            $table->unsignedInteger('custom_price')->nullable(); // Price in cents
            $table->unsignedTinyInteger('discount_percentage')->nullable(); // 0-100
            $table->timestamps();

            // Unique constraint: one allocation per ticket definition per hold
            $table->unique(['ticket_hold_id', 'ticket_definition_id'], 'hold_allocation_unique');

            // Indexes
            $table->index('ticket_hold_id');
            $table->index('ticket_definition_id');
        });

        // 3. purchase_links - Purchase links for ticket holds
        Schema::create('purchase_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 32)->unique();
            $table->foreignId('ticket_hold_id')->constrained('ticket_holds')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('quantity_mode')->default(QuantityModeEnum::MAXIMUM->value);
            $table->unsignedInteger('quantity_limit')->nullable();
            $table->unsignedInteger('quantity_purchased')->default(0);
            $table->string('status')->default(LinkStatusEnum::ACTIVE->value);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code');
            $table->index(['ticket_hold_id', 'status']);
            $table->index('assigned_user_id');
            $table->index('status');
            $table->index('expires_at');
        });

        // 4. purchase_link_accesses - Analytics for link access tracking
        Schema::create('purchase_link_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_link_id')->constrained('purchase_links')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable(); // IPv6 compatible
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->string('session_id')->nullable();
            $table->boolean('resulted_in_purchase')->default(false);
            $table->timestamp('accessed_at');
            $table->timestamps();

            // Indexes
            $table->index('purchase_link_id');
            $table->index('user_id');
            $table->index('accessed_at');
            $table->index(['purchase_link_id', 'resulted_in_purchase'], 'link_access_purchase_idx');
        });

        // 5. purchase_link_purchases - Records of purchases made through links
        Schema::create('purchase_link_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_link_id')->constrained('purchase_links')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price'); // Price paid per ticket in cents
            $table->unsignedInteger('original_price'); // Original ticket price in cents
            $table->string('currency', 3)->default('hkd');
            $table->foreignId('access_id')->nullable()->constrained('purchase_link_accesses')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('purchase_link_id');
            $table->index('booking_id');
            $table->index('transaction_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_link_purchases');
        Schema::dropIfExists('purchase_link_accesses');
        Schema::dropIfExists('purchase_links');
        Schema::dropIfExists('hold_ticket_allocations');
        Schema::dropIfExists('ticket_holds');
    }
};
