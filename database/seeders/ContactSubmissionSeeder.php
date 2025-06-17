<?php

namespace Database\Seeders;

use App\Modules\CMS\Models\ContactSubmission;
use Illuminate\Database\Seeder;

class ContactSubmissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if the factory exists to prevent errors
        if (class_exists(ContactSubmission::class) && method_exists(ContactSubmission::class, 'factory')) {
            ContactSubmission::factory()->count(25)->create();
            $this->command->info('Contact submissions seeded successfully.');
        } else {
            $this->command->error('ContactSubmission model or factory not found. Skipping seeding.');
        }
    }
}
