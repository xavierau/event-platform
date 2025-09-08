<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Membership\Enums\MembershipStatus;
use Illuminate\Console\Command;

class MigrateUsersToFreeTier extends Command
{
    protected $signature = 'membership:migrate-free-tier
                            {--dry-run : Show what would be done without making changes}
                            {--force : Force the operation without confirmation}';

    protected $description = 'Migrate existing users without memberships to free tier';

    public function handle(): int
    {
        $freeTier = MembershipLevel::where('slug', 'free')->first();
        
        if (!$freeTier) {
            $this->error('Free tier not found. Please run the StripeMembershipLevelSeeder first.');
            return self::FAILURE;
        }

        $usersWithoutMemberships = User::doesntHave('memberships')->get();
        
        if ($usersWithoutMemberships->isEmpty()) {
            $this->info('No users found without memberships.');
            return self::SUCCESS;
        }

        $this->info("Found {$usersWithoutMemberships->count()} users without memberships.");
        
        if ($this->option('dry-run')) {
            $this->info('DRY RUN - Would migrate the following users:');
            $usersWithoutMemberships->each(function ($user) {
                $this->line("- {$user->name} ({$user->email}) - Created: {$user->created_at}");
            });
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Do you want to proceed with migrating these users to the free tier?')) {
            $this->info('Migration cancelled.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($usersWithoutMemberships->count());
        $bar->start();

        $migrated = 0;
        $errors = 0;

        foreach ($usersWithoutMemberships as $user) {
            try {
                UserMembership::create([
                    'user_id' => $user->id,
                    'membership_level_id' => $freeTier->id,
                    'started_at' => $user->created_at,
                    'expires_at' => null, // Free tier doesn't expire
                    'status' => MembershipStatus::ACTIVE,
                    'payment_method' => 'migrated',
                    'auto_renew' => false,
                    'subscription_metadata' => [
                        'migrated_at' => now()->toISOString(),
                        'migration_type' => 'free_tier_migration',
                    ],
                ]);
                $migrated++;
            } catch (\Exception $e) {
                $this->error("\nFailed to migrate user {$user->email}: {$e->getMessage()}");
                $errors++;
            }
            
            $bar->advance();
        }

        $bar->finish();
        
        $this->newLine(2);
        $this->info("Migration completed!");
        $this->info("Successfully migrated: {$migrated} users");
        
        if ($errors > 0) {
            $this->warn("Errors encountered: {$errors} users");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
