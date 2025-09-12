<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find the admin role
        $adminRole = Role::where('name', 'admin')->first();
        
        if (!$adminRole) {
            throw new Exception('Admin role not found. Please ensure the admin role exists before running this migration.');
        }
        
        // Get all permissions
        $allPermissions = Permission::all();
        
        // Assign all permissions to admin role
        // This uses syncPermissions which will add any missing permissions
        // and keep existing ones, effectively ensuring all permissions are assigned
        $adminRole->syncPermissions($allPermissions);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // In down method, we could optionally remove some permissions
        // but since we want admin to have all permissions in production,
        // we'll leave this empty to avoid accidentally removing needed permissions
        
        // If you need to reverse this migration, you would need to manually
        // specify which permissions to remove or restore from a backup
    }
};
