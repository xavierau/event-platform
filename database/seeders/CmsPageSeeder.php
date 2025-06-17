<?php

namespace Database\Seeders;

use App\Modules\CMS\Models\CmsPage;
use App\Models\User;
use Illuminate\Database\Seeder;

class CmsPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first admin user to be the author
        $author = User::whereHas('roles', fn($query) => $query->where('name', 'admin'))->first();

        if (!$author) {
            // If no admin, create one or use the first user as a fallback.
            $author = User::first();
        }

        // Create 5 CMS Pages
        CmsPage::factory()->count(5)->create([
            'author_id' => $author->id,
        ]);
    }
}
