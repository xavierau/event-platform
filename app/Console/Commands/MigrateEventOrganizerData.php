<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Organizer;
use App\Models\User;
use App\Models\Event;
use App\Models\Venue;
use App\Enums\RoleNameEnum;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateEventOrganizerData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organizer:migrate-event-data
                            {--dry-run : Show what would be migrated without making changes}
                            {--force : Force migration without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing event data to use organizer entities';

    private $migrationStats = [
        'events_migrated' => 0,
        'venues_updated' => 0,
        'memberships_created' => 0,
        'organizers_created' => 0,
        'errors_found' => 0,
    ];

    private $isDryRun = false;
    private $isVerbose = false;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->isDryRun = $this->option('dry-run');
        $this->isVerbose = $this->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;

        $this->info('Event Organizer Data Migration Tool');
        $this->info('====================================');

        if ($this->isDryRun) {
            $this->warn('DRY RUN MODE: No changes will be made');
        }

        // Confirm before proceeding (unless forced)
        if (!$this->option('force') && !$this->isDryRun) {
            if (!$this->confirm('This will migrate event data to use organizer entities. Continue?')) {
                $this->info('Migration cancelled.');
                return 0;
            }
        }

        try {
            // Step 1: Validate current state
            $this->validateCurrentState();

            // Step 2: Ensure default organizer exists
            $defaultOrganizer = $this->ensureDefaultOrganizerExists();

            // Step 3: Analyze what needs migration
            $this->analyzeDataForMigration();

            // Step 4: Migrate orphaned events
            $this->migrateOrphanedEvents($defaultOrganizer);

            // Step 5: Create organizer memberships
            $this->createOrganizerMemberships($defaultOrganizer);

            // Step 6: Migrate venue ownership
            $this->migrateVenueOwnership();

            // Step 7: Validate results
            $this->validateMigrationResults();

            // Step 8: Show summary
            $this->showMigrationSummary();

            if ($this->isDryRun) {
                $this->info('DRY RUN COMPLETED - No changes were made');
            } else {
                $this->info('Migration completed successfully!');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            Log::error('Event organizer migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Validate the current state before migration.
     */
    private function validateCurrentState(): void
    {
        $this->info('Validating current system state...');

        $issues = [];

        // Check required tables
        $requiredTables = ['events', 'organizers', 'organizer_users', 'users'];
        foreach ($requiredTables as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                $issues[] = "Missing required table: {$table}";
            }
        }

        // Check for basic data integrity
        $eventCount = DB::table('events')->count();
        $userCount = DB::table('users')->count();
        $organizerCount = DB::table('organizers')->count();

        $this->verboseOutput("Current counts - Events: {$eventCount}, Users: {$userCount}, Organizers: {$organizerCount}");

        if ($eventCount === 0) {
            $this->warn('No events found in system');
        }

        if ($userCount === 0) {
            $issues[] = 'No users found in system';
        }

        if (!empty($issues)) {
            throw new \Exception('Validation failed: ' . implode(', ', $issues));
        }

        $this->info('✓ System state validation passed');
    }

    /**
     * Ensure default organizer exists.
     */
    private function ensureDefaultOrganizerExists(): Organizer
    {
        $this->info('Checking for default organizer...');

        $defaultOrganizer = Organizer::where('slug', 'default-organizer')->first();

        if (!$defaultOrganizer) {
            if ($this->isDryRun) {
                $this->info('[DRY RUN] Would create default organizer');
                // Return a mock organizer for dry run
                $defaultOrganizer = new Organizer(['id' => 1, 'slug' => 'default-organizer']);
            } else {
                $defaultOrganizer = $this->createDefaultOrganizer();
                $this->migrationStats['organizers_created']++;
                $this->info('✓ Created default organizer');
            }
        } else {
            $this->info('✓ Default organizer already exists');
        }

        return $defaultOrganizer;
    }

    /**
     * Create a default organizer entity.
     */
    private function createDefaultOrganizer(): Organizer
    {
        $organizerUser = User::role([RoleNameEnum::ADMIN->value])->first()
            ?? User::first();

        return Organizer::create([
            'name' => [
                'en' => 'Default Organizer',
                'zh-TW' => '預設主辦單位',
                'zh-CN' => '默认主办方'
            ],
            'slug' => 'default-organizer',
            'description' => [
                'en' => 'Default organizer entity created during system migration.',
                'zh-TW' => '系統遷移時創建的預設主辦單位。',
                'zh-CN' => '系统迁移时创建的默认主办方。'
            ],
            'contact_email' => $organizerUser?->email ?? 'admin@example.com',
            'is_active' => true,
            'created_by' => $organizerUser?->id,
        ]);
    }

    /**
     * Analyze what data needs migration.
     */
    private function analyzeDataForMigration(): void
    {
        $this->info('Analyzing data for migration...');

        // Check for orphaned events
        $orphanedEvents = DB::table('events as e')
            ->leftJoin('organizers as o', 'e.organizer_id', '=', 'o.id')
            ->whereNull('o.id')
            ->count();

        // Check for events with null organizer_id
        $nullOrganizerEvents = DB::table('events')
            ->whereNull('organizer_id')
            ->count();

        // Check for users without organizer memberships
        $usersWithoutMemberships = 0;
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            // Only check admin users since ORGANIZER role has been removed in favor of organizer entity relationships
            $adminUsers = User::role([RoleNameEnum::ADMIN->value])->get();
            foreach ($adminUsers as $user) {
                if (!DB::table('organizer_users')->where('user_id', $user->id)->exists()) {
                    $usersWithoutMemberships++;
                }
            }
        }

        // Check venue ownership
        $venuesWithOrganizerOwnership = DB::table('venues')->whereNotNull('organizer_id')->count();

        // Display analysis
        $this->table(['Item', 'Count'], [
            ['Orphaned events (invalid organizer_id)', $orphanedEvents],
            ['Events with null organizer_id', $nullOrganizerEvents],
            ['Users needing organizer membership', $usersWithoutMemberships],
            ['Venues with organizer ownership', $venuesWithOrganizerOwnership],
        ]);

        $totalIssues = $orphanedEvents + $nullOrganizerEvents;
        if ($totalIssues > 0) {
            $this->warn("Found {$totalIssues} events that need migration");
        } else {
            $this->info('✓ No orphaned events found');
        }
    }

    /**
     * Migrate orphaned events to default organizer.
     */
    private function migrateOrphanedEvents(Organizer $defaultOrganizer): void
    {
        $this->info('Migrating orphaned events...');

        // Find events with invalid organizer references
        $orphanedEvents = DB::table('events as e')
            ->leftJoin('organizers as o', 'e.organizer_id', '=', 'o.id')
            ->whereNull('o.id')
            ->orWhereNull('e.organizer_id')
            ->select('e.id', 'e.organizer_id', 'e.name')
            ->get();

        if ($orphanedEvents->count() === 0) {
            $this->info('✓ No orphaned events to migrate');
            return;
        }

        $this->info("Found {$orphanedEvents->count()} orphaned events to migrate");

        if ($this->isVerbose) {
            foreach ($orphanedEvents as $event) {
                $eventName = $this->extractEventName($event->name);
                $this->verboseOutput("Event ID {$event->id}: '{$eventName}' (current organizer_id: {$event->organizer_id})");
            }
        }

        if (!$this->isDryRun) {
            $updated = DB::table('events')
                ->whereIn('id', $orphanedEvents->pluck('id'))
                ->update(['organizer_id' => $defaultOrganizer->id]);

            $this->migrationStats['events_migrated'] = $updated;
            $this->info("✓ Migrated {$updated} events to default organizer");
        } else {
            $this->info("[DRY RUN] Would migrate {$orphanedEvents->count()} events to default organizer");
        }
    }

    /**
     * Create organizer memberships for existing users.
     */
    private function createOrganizerMemberships(Organizer $defaultOrganizer): void
    {
        $this->info('Creating organizer memberships...');

        if (!class_exists(\Spatie\Permission\Models\Role::class)) {
            $this->info('Spatie permissions not available, skipping membership creation');
            return;
        }

        // Only check admin users since ORGANIZER role has been removed in favor of organizer entity relationships
        $organizerUsers = User::role([RoleNameEnum::ADMIN->value])->get();
        $newMemberships = 0;

        foreach ($organizerUsers as $user) {
            $existingMembership = DB::table('organizer_users')
                ->where('user_id', $user->id)
                ->exists();

            if (!$existingMembership) {
                $roleInOrganizer = $user->hasRole(RoleNameEnum::ADMIN->value) ? 'owner' : 'manager';

                if ($this->isDryRun) {
                    $this->verboseOutput("[DRY RUN] Would create {$roleInOrganizer} membership for {$user->email}");
                } else {
                    DB::table('organizer_users')->insert([
                        'organizer_id' => $defaultOrganizer->id,
                        'user_id' => $user->id,
                        'role_in_organizer' => $roleInOrganizer,
                        'joined_at' => now(),
                        'is_active' => true,
                        'invitation_accepted_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->verboseOutput("Created {$roleInOrganizer} membership for {$user->email}");
                }
                $newMemberships++;
            }
        }

        $this->migrationStats['memberships_created'] = $newMemberships;

        if ($newMemberships > 0) {
            $this->info("✓ Created {$newMemberships} organizer memberships");
        } else {
            $this->info('✓ No new memberships needed');
        }
    }

    /**
     * Migrate venue ownership to public status.
     */
    private function migrateVenueOwnership(): void
    {
        $this->info('Migrating venue ownership...');

        if (!DB::getSchemaBuilder()->hasTable('venues')) {
            $this->info('Venues table not found, skipping venue migration');
            return;
        }

        $venuesWithOwnership = DB::table('venues')->whereNotNull('organizer_id')->count();

        if ($venuesWithOwnership === 0) {
            $this->info('✓ No venue ownership to migrate');
            return;
        }

        if ($this->isDryRun) {
            $this->info("[DRY RUN] Would set {$venuesWithOwnership} venues to public status");
        } else {
            $updated = DB::table('venues')
                ->whereNotNull('organizer_id')
                ->update(['organizer_id' => null]);

            $this->migrationStats['venues_updated'] = $updated;
            $this->info("✓ Set {$updated} venues to public status");
        }
    }

    /**
     * Validate migration results.
     */
    private function validateMigrationResults(): void
    {
        $this->info('Validating migration results...');

        if ($this->isDryRun) {
            $this->info('[DRY RUN] Skipping validation (no changes made)');
            return;
        }

        // Check for remaining orphaned events
        $remainingOrphans = DB::table('events as e')
            ->leftJoin('organizers as o', 'e.organizer_id', '=', 'o.id')
            ->whereNull('o.id')
            ->count();

        if ($remainingOrphans > 0) {
            $this->migrationStats['errors_found']++;
            throw new \Exception("Validation failed: {$remainingOrphans} events still have invalid organizer references");
        }

        // Check for null organizer_id events
        $nullOrganizerEvents = DB::table('events')->whereNull('organizer_id')->count();
        if ($nullOrganizerEvents > 0) {
            $this->migrationStats['errors_found']++;
            throw new \Exception("Validation failed: {$nullOrganizerEvents} events still have null organizer_id");
        }

        $this->info('✓ Migration validation passed');
    }

    /**
     * Show migration summary.
     */
    private function showMigrationSummary(): void
    {
        $this->info('');
        $this->info('Migration Summary');
        $this->info('================');

        $this->table(['Action', 'Count'], [
            ['Organizers created', $this->migrationStats['organizers_created']],
            ['Events migrated', $this->migrationStats['events_migrated']],
            ['Memberships created', $this->migrationStats['memberships_created']],
            ['Venues updated', $this->migrationStats['venues_updated']],
            ['Errors found', $this->migrationStats['errors_found']],
        ]);

        if ($this->migrationStats['errors_found'] === 0) {
            $this->info('✓ Migration completed without errors');
        } else {
            $this->error('⚠ Migration completed with errors');
        }
    }

    /**
     * Extract event name from JSON field for display.
     */
    private function extractEventName(?string $nameJson): string
    {
        if (!$nameJson) {
            return 'Unknown';
        }

        $nameData = json_decode($nameJson, true);
        if (!is_array($nameData)) {
            return 'Unknown';
        }

        return $nameData['en'] ?? $nameData[array_key_first($nameData)] ?? 'Unknown';
    }

    /**
     * Output verbose information.
     */
    private function verboseOutput(string $message): void
    {
        if ($this->isVerbose) {
            $this->line("  → {$message}");
        }
    }
}
