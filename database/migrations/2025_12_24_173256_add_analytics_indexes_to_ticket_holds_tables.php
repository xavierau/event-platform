<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds composite indexes to optimize analytics queries in HoldAnalyticsService.
 *
 * Key query patterns optimized:
 * - getTopPerformingLinks(): whereHas('ticketHold', ...) queries by ticket_hold_id
 * - getOrganizerAnalytics(): queries by organizer_id + created_at date range
 * - getRevenueByTicketType(): whereHas on purchase_links by ticket_hold_id
 * - Access patterns: queries by purchase_link_id + accessed_at for time-based analytics
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Note: purchase_links already has index ['ticket_hold_id', 'status'] from original migration.
        // Adding additional index for queries that filter by ticket_hold_id without status.

        // Optimize getOrganizerAnalytics() which queries ticket_holds by organizer_id + created_at
        Schema::table('ticket_holds', function (Blueprint $table) {
            $table->index(
                ['organizer_id', 'created_at'],
                'ticket_holds_organizer_created_idx'
            );
        });

        // Optimize queries on purchase_link_purchases that join back to purchase_links
        // Used by getRevenueByTicketType() and other revenue analytics
        Schema::table('purchase_link_purchases', function (Blueprint $table) {
            $table->index(
                ['purchase_link_id', 'created_at'],
                'link_purchases_link_created_idx'
            );
        });

        // Optimize time-based access queries for analytics (access patterns, daily aggregations)
        Schema::table('purchase_link_accesses', function (Blueprint $table) {
            $table->index(
                ['purchase_link_id', 'accessed_at'],
                'link_accesses_link_accessed_idx'
            );
            // Index for user-based analytics queries
            $table->index(
                ['user_id', 'resulted_in_purchase'],
                'link_accesses_user_purchase_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_holds', function (Blueprint $table) {
            $table->dropIndex('ticket_holds_organizer_created_idx');
        });

        Schema::table('purchase_link_purchases', function (Blueprint $table) {
            $table->dropIndex('link_purchases_link_created_idx');
        });

        Schema::table('purchase_link_accesses', function (Blueprint $table) {
            $table->dropIndex('link_accesses_link_accessed_idx');
            $table->dropIndex('link_accesses_user_purchase_idx');
        });
    }
};
