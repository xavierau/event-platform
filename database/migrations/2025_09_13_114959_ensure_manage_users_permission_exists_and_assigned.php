<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Enums\RoleNameEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure the manage-users permission exists
        Permission::firstOrCreate([
            'name' => 'manage-users',
            'guard_name' => 'web'
        ]);

        // Assign the permission to platform admin role
        $adminRole = Role::where('name', RoleNameEnum::ADMIN->value)
                        ->where('guard_name', 'web')
                        ->first();

        if ($adminRole) {
            $permission = Permission::where('name', 'manage-users')
                                   ->where('guard_name', 'web')
                                   ->first();
            
            if ($permission && !$adminRole->hasPermissionTo($permission)) {
                $adminRole->givePermissionTo($permission);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the permission from admin role
        $adminRole = Role::where('name', RoleNameEnum::ADMIN->value)
                        ->where('guard_name', 'web')
                        ->first();

        if ($adminRole) {
            $permission = Permission::where('name', 'manage-users')
                                   ->where('guard_name', 'web')
                                   ->first();
            
            if ($permission && $adminRole->hasPermissionTo($permission)) {
                $adminRole->revokePermissionTo($permission);
            }
        }

        // Remove the permission if it exists
        Permission::where('name', 'manage-users')
                 ->where('guard_name', 'web')
                 ->delete();
    }
};
