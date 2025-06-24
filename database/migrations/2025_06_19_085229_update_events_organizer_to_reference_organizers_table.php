<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Organizer;
use App\Models\User;
use App\Enums\RoleNameEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, create a default organizer entity if none exists
        $this->createDefaultOrganizerIfNeeded();

        // Get the default organizer for data migration
        $defaultOrganizer = Organizer::first();

        if (!$defaultOrganizer) {
            throw new \Exception('No default organizer found. Cannot proceed with migration.');
        }

        // Update all existing events to use the default organizer
        DB::table('events')->update(['organizer_id' => $defaultOrganizer->id]);

        // Now update the foreign key constraint
        Schema::table('events', function (Blueprint $table) {
            // Drop the existing foreign key constraint to users
            $table->dropForeign(['organizer_id']);

            // Add the new foreign key constraint to organizers
            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This reversal is destructive and will lose organizer entity data
        // We cannot perfectly reverse this migration since we've moved from users to organizers

        Schema::table('events', function (Blueprint $table) {
            // Drop the foreign key constraint to organizers
            $table->dropForeign(['organizer_id']);

            // Restore the foreign key constraint to users
            // Note: This will fail if there are events with organizer_id that don't exist in users table
            $table->foreign('organizer_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Create a default organizer entity if none exists.
     */
    private function createDefaultOrganizerIfNeeded(): void
    {
        if (Organizer::count() > 0) {
            return; // Organizer already exists
        }

        // Try to get a user with organizer role as the creator
        $organizerUser = null;

        // Check if Spatie permission roles exist before trying to use them
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            try {
                // Try to get an admin user as the creator
                $organizerUser = User::role(RoleNameEnum::ADMIN->value)->first();
            } catch (\Exception $e) {
                // Role doesn't exist, continue without it
            }
        }

        // If still no user, get the first user
        if (!$organizerUser) {
            $organizerUser = User::first();
        }

        // Create default organizer
        $organizer = Organizer::create([
            'name' => [
                'en' => 'Default Organizer',
                'zh-TW' => '預設主辦單位',
                'zh-CN' => '默认主办方'
            ],
            'slug' => 'default-organizer',
            'description' => [
                'en' => 'Default organizer entity for migrating existing events',
                'zh-TW' => '用於遷移現有活動的預設主辦單位',
                'zh-CN' => '用于迁移现有活动的默认主办方'
            ],
            'contact_email' => $organizerUser?->email ?? 'admin@example.com',
            'is_active' => true,
            'created_by' => $organizerUser?->id,
        ]);

        // If we have an organizer user, add them as owner of the default organizer
        if ($organizerUser) {
            $organizer->users()->attach($organizerUser->id, [
                'role_in_organizer' => 'owner',
                'joined_at' => now(),
                'is_active' => true,
                'invitation_accepted_at' => now(),
            ]);
        }
    }
};
