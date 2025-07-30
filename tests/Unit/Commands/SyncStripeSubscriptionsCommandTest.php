<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\SyncStripeSubscriptionsCommand;
use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Services\StripeSubscriptionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class SyncStripeSubscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_requires_stripe_secret_key(): void
    {
        // Arrange - temporarily remove Stripe secret
        config(['services.stripe.secret' => null]);

        // Act
        $result = $this->artisan('stripe:sync-subscriptions', ['--dry-run' => true]);

        // Assert
        $result->assertExitCode(1);
        $result->expectsOutput('❌ Stripe secret key not configured. Please set STRIPE_SECRET in your .env file.');
    }

    public function test_command_shows_dry_run_warning(): void
    {
        // Act
        $result = $this->artisan('stripe:sync-subscriptions', ['--dry-run' => true, '--limit' => 1]);

        // Assert
        $result->assertExitCode(0);
        $result->expectsOutput('🔍 DRY RUN MODE - No records will be created/updated');
    }

    public function test_command_processes_customers_successfully(): void
    {
        // This test would require mocking Stripe API calls
        // For now, we'll test the command structure and basic functionality
        
        // Act
        $result = $this->artisan('stripe:sync-subscriptions', [
            '--dry-run' => true, 
            '--limit' => 1
        ]);

        // Assert
        $result->assertExitCode(0);
        $result->expectsOutput('🚀 Starting Stripe subscription synchronization...');
        $result->expectsOutput('👥 Fetching Stripe customers...');
        $result->expectsOutput('📊 Synchronization Results:');
    }

    public function test_command_shows_export_option(): void
    {
        // Act - just test that the command accepts the export option without error
        $result = $this->artisan('stripe:sync-subscriptions', [
            '--dry-run' => true,
            '--limit' => 1,
            '--export-report' => true
        ]);

        // Assert - just ensure command runs successfully with export option
        $result->assertExitCode(0);
        $result->expectsOutput('📊 Synchronization Results:');
    }

    public function test_find_local_user_matches_by_email(): void
    {
        // This would test the private method findLocalUser
        // In a real implementation, we might extract this to a service class for easier testing
        $this->markTestIncomplete('Private method testing requires refactoring or reflection');
    }

    public function test_process_customer_creates_user_link(): void
    {
        // This would test the private method processCustomer
        // In a real implementation, we might extract this to a service class for easier testing
        $this->markTestIncomplete('Private method testing requires refactoring or reflection');
    }

    public function test_process_subscription_uses_sync_service(): void
    {
        // This would test that the command properly delegates to StripeSubscriptionSyncService
        $this->markTestIncomplete('Private method testing requires refactoring or reflection');
    }
}