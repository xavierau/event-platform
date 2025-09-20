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
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            // Drop the index first to avoid SQLite issues
            $table->dropIndex(['action_type', 'created_at']);
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            // Drop the enum column
            $table->dropColumn('action_type');
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            // Recreate with additional enum value
            $table->enum('action_type', [
                'create_user',
                'change_membership',
                'block_commenting',
                'unblock_commenting',
                'email_verification_change'
            ])->after('target_user_id');
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            // Recreate the index
            $table->index(['action_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            // Drop the index first
            $table->dropIndex(['action_type', 'created_at']);
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->dropColumn('action_type');
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->enum('action_type', [
                'create_user',
                'change_membership',
                'block_commenting',
                'unblock_commenting'
            ])->after('target_user_id');
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            // Recreate the index
            $table->index(['action_type', 'created_at']);
        });
    }
};
