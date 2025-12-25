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
        Schema::create('temporary_registration_page_users', function (Blueprint $table) {
            $table->id();
            // Nullable to allow SET NULL on delete (preserves historical record)
            $table->unsignedBigInteger('temporary_registration_page_id')->nullable();
            $table->foreign('temporary_registration_page_id', 'temp_reg_page_fk')
                ->references('id')
                ->on('temporary_registration_pages')
                ->onDelete('set null');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id', 'temp_reg_user_fk')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Can't use unique constraint with nullable columns that may become NULL
            // Using index instead for query performance
            $table->index(['temporary_registration_page_id', 'user_id'], 'temp_reg_page_user_idx');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_registration_page_users');
    }
};
