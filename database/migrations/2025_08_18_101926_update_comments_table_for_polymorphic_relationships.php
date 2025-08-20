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
        Schema::table('comments', function (Blueprint $table) {
            // Drop the existing event_id foreign key constraint first
            $table->dropForeign(['event_id']);
            
            // Remove the event_id column
            $table->dropColumn('event_id');
            
            // Add polymorphic relationship columns
            $table->string('commentable_type')->after('user_id');
            $table->unsignedBigInteger('commentable_id')->after('commentable_type');
            
            // Add new columns for enhanced functionality
            $table->enum('content_type', ['plain', 'rich'])->default('plain')->after('content');
            $table->boolean('votes_enabled')->default(false)->after('content_type');
            $table->unsignedInteger('votes_up_count')->default(0)->after('votes_enabled');
            $table->unsignedInteger('votes_down_count')->default(0)->after('votes_up_count');
            
            // Add indexes for performance
            $table->index(['commentable_type', 'commentable_id']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            // Remove the new columns
            $table->dropIndex(['commentable_type', 'commentable_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'commentable_type',
                'commentable_id',
                'content_type',
                'votes_enabled',
                'votes_up_count',
                'votes_down_count'
            ]);
            
            // Restore the event_id column
            $table->unsignedBigInteger('event_id')->after('user_id');
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
        });
    }
};
