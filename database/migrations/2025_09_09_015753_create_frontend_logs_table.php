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
        if (! Schema::hasTable('frontend_logs')) {
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
                $table->foreignId('user_id')->nullable()->index();
                $table->timestamps();

                // Indexes for better query performance
                $table->index(['level', 'created_at']);
                $table->index(['component', 'created_at']);
                $table->index(['user_id', 'created_at']);

                // Add foreign key constraint with explicit naming
                $table->foreign('user_id', 'frontend_logs_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        } else {
            // Table exists, check if we need to add missing constraints or columns
            Schema::table('frontend_logs', function (Blueprint $table) {
                // Only add foreign key if it doesn't exist
                if (! $this->foreignKeyExists('frontend_logs', 'frontend_logs_user_id_foreign')) {
                    $table->foreign('user_id', 'frontend_logs_user_id_foreign')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            });
        }
    }

    /**
     * Check if a foreign key constraint exists.
     */
    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND CONSTRAINT_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$databaseName, $table, $constraintName]);

        return count($result) > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frontend_logs');
    }
};
