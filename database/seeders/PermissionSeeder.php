<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define permissions for each model
        $models = [
            'Booking',
            'User',
            'TicketDefinition',
            'Transaction',
            'Tag',
            'Venue',
            'Category',
            'Event',
            'EventOccurrence',
            'State',
            'Country',
            'SiteSetting',
        ];

        $actions = ['viewAny', 'view', 'create', 'update', 'delete'];

        // Reset cached permissions
        app()['cache']->forget('spatie.permission.cache');

        foreach ($models as $model) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => $action . ' ' . $model, 'guard_name' => 'web']);
            }
        }
        $this->command->info('All granular permissions created successfully.');
    }
}
