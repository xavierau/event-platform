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
        Schema::table('events', function (Blueprint $table) {
            $table->json('visible_to_membership_levels')->nullable()->after('visibility');
            $table->string('action_type')->default('purchase_ticket')->after('visible_to_membership_levels');
            
            $table->index('action_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['action_type']);
            $table->dropColumn(['visible_to_membership_levels', 'action_type']);
        });
    }
};