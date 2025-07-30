<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Services\StripeSubscriptionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\Exception\ApiErrorException;

class SyncStripeSubscriptionsCommand extends Command
{
    protected $signature = 'stripe:sync-subscriptions 
                            {--dry-run : Show what would be synced without actually creating records}
                            {--limit= : Limit number of customers to process (default: all)}
                            {--starting-after= : Stripe customer ID to start after (for pagination)}
                            {--export-report : Export detailed reconciliation report to CSV}';

    protected $description = 'Sync existing Stripe customers and subscriptions with local users and memberships';

    private StripeSubscriptionSyncService $syncService;
    
    private array $stats = [
        'customers_processed' => 0,
        'customers_matched' => 0,
        'customers_unmatched' => 0,
        'subscriptions_processed' => 0,
        'subscriptions_synced' => 0,
        'subscriptions_skipped' => 0,
        'memberships_created' => 0,
        'memberships_updated' => 0,
        'errors' => 0,
    ];

    private array $report = [
        'matched_customers' => [],
        'unmatched_customers' => [],
        'synced_subscriptions' => [],
        'skipped_subscriptions' => [],
        'errors' => [],
    ];

    public function handle(): int
    {
        $this->info('🚀 Starting Stripe subscription synchronization...');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No records will be created/updated');
            $this->newLine();
        }

        // Initialize Stripe API key
        $stripeSecret = config('services.stripe.secret');
        if (!$stripeSecret) {
            $this->error('❌ Stripe secret key not configured. Please set STRIPE_SECRET in your .env file.');
            return Command::FAILURE;
        }
        
        \Stripe\Stripe::setApiKey($stripeSecret);
        $this->syncService = app(StripeSubscriptionSyncService::class);

        try {
            $this->syncCustomersAndSubscriptions();
            $this->displayResults();
            
            if ($this->option('export-report')) {
                $this->exportReport();
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            Log::error('[SyncStripeSubscriptionsCommand] Sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function syncCustomersAndSubscriptions(): void
    {
        $this->info('👥 Fetching Stripe customers...');
        
        $limit = $this->option('limit') ? (int) $this->option('limit') : 100;
        $startingAfter = $this->option('starting-after');
        $hasMore = true;
        $processedCount = 0;
        $maxLimit = $this->option('limit') ? (int) $this->option('limit') : PHP_INT_MAX;

        while ($hasMore && $processedCount < $maxLimit) {
            try {
                $params = [
                    'limit' => min($limit, $maxLimit - $processedCount),
                    'expand' => ['data.subscriptions'],
                ];
                
                if ($startingAfter) {
                    $params['starting_after'] = $startingAfter;
                }

                $customers = Customer::all($params);
                
                $this->info("Processing batch of {$customers->count()} customers...");
                
                foreach ($customers->data as $customer) {
                    $this->processCustomer($customer);
                    $processedCount++;
                    
                    if ($processedCount >= $maxLimit) {
                        break;
                    }
                }

                $hasMore = $customers->has_more;
                if ($hasMore && !empty($customers->data)) {
                    $startingAfter = end($customers->data)->id;
                }

                // Add small delay to respect rate limits
                if ($hasMore) {
                    usleep(100000); // 100ms delay
                }

            } catch (ApiErrorException $e) {
                $this->handleApiError($e, 'fetching customers');
                break;
            }
        }

        $this->info("✅ Processed {$this->stats['customers_processed']} customers total");
    }

    private function processCustomer(Customer $customer): void
    {
        $this->stats['customers_processed']++;
        
        $this->info("🔄 Processing customer: {$customer->email} ({$customer->id})");

        // Try to match customer to local user
        $user = $this->findLocalUser($customer);
        
        if (!$user) {
            $this->stats['customers_unmatched']++;
            $this->report['unmatched_customers'][] = [
                'stripe_customer_id' => $customer->id,
                'email' => $customer->email,
                'created' => $customer->created,
                'reason' => 'No local user found with matching email',
            ];
            
            $this->warn("  ⚠️  No local user found for {$customer->email}");
            return;
        }

        $this->stats['customers_matched']++;
        $this->report['matched_customers'][] = [
            'stripe_customer_id' => $customer->id,
            'user_id' => $user->id,
            'email' => $customer->email,
            'stripe_id_before' => $user->stripe_id,
        ];

        // Link customer to user if not already linked
        if (!$user->stripe_id) {
            if (!$this->option('dry-run')) {
                $user->stripe_id = $customer->id;
                $user->save();
            }
            $this->info("  🔗 Linked user {$user->id} to Stripe customer {$customer->id}");
        }

        // Process customer's subscriptions
        if ($customer->subscriptions && $customer->subscriptions->data) {
            foreach ($customer->subscriptions->data as $subscription) {
                $this->processSubscription($user, $subscription);
            }
        } else {
            $this->warn("  📭 No subscriptions found for customer {$customer->email}");
        }
    }

    private function processSubscription(User $user, object $subscription): void
    {
        $this->stats['subscriptions_processed']++;
        
        $this->info("  💳 Processing subscription: {$subscription->id} (status: {$subscription->status})");

        // Check if subscription already exists locally
        $existingMembership = UserMembership::findByStripeSubscription($subscription->id);
        
        if ($existingMembership) {
            $this->stats['subscriptions_skipped']++;
            $this->report['skipped_subscriptions'][] = [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'existing_membership_id' => $existingMembership->id,
                'reason' => 'Membership already exists',
            ];
            
            $this->warn("    ⏭️  Subscription already synced (membership ID: {$existingMembership->id})");
            return;
        }

        // Find membership level for this subscription
        $membershipLevel = $this->findMembershipLevelForSubscription($subscription);
        
        if (!$membershipLevel) {
            $this->stats['errors']++;
            $priceId = $subscription->items->data[0]->price->id ?? 'unknown';
            $this->report['errors'][] = [
                'type' => 'missing_membership_level',
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'price_id' => $priceId,
                'message' => "No MembershipLevel found for price ID: {$priceId}",
            ];
            
            $this->error("    ❌ No MembershipLevel found for price {$priceId}");
            return;
        }

        // Only sync active subscriptions (avoid creating memberships for cancelled ones)
        if (!in_array($subscription->status, ['active', 'past_due', 'trialing'])) {
            $this->stats['subscriptions_skipped']++;
            $this->report['skipped_subscriptions'][] = [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'status' => $subscription->status,
                'reason' => 'Subscription not in active state',
            ];
            
            $this->warn("    ⏭️  Skipping subscription with status: {$subscription->status}");
            return;
        }

        // Create membership using the sync service
        try {
            if (!$this->option('dry-run')) {
                $membership = $this->syncService->handleSubscriptionCreated($subscription);
                
                if ($membership) {
                    $this->stats['memberships_created']++;
                    $this->stats['subscriptions_synced']++;
                    
                    $this->report['synced_subscriptions'][] = [
                        'subscription_id' => $subscription->id,
                        'user_id' => $user->id,
                        'membership_id' => $membership->id,
                        'membership_level_id' => $membership->membership_level_id,
                        'status' => $membership->status,
                        'expires_at' => $membership->expires_at,
                    ];
                    
                    $this->info("    ✅ Created membership {$membership->id} for subscription {$subscription->id}");
                } else {
                    $this->stats['errors']++;
                    $this->report['errors'][] = [
                        'type' => 'sync_service_failed',
                        'subscription_id' => $subscription->id,
                        'user_id' => $user->id,
                        'message' => 'StripeSubscriptionSyncService returned null',
                    ];
                    
                    $this->error("    ❌ Failed to create membership via sync service");
                }
            } else {
                $this->info("    🔍 [DRY RUN] Would create membership for subscription {$subscription->id}");
                $this->stats['subscriptions_synced']++;
            }
            
        } catch (\Exception $e) {
            $this->stats['errors']++;
            $this->report['errors'][] = [
                'type' => 'exception',
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
            
            $this->error("    ❌ Error creating membership: {$e->getMessage()}");
            
            Log::error('[SyncStripeSubscriptionsCommand] Error processing subscription', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function findLocalUser(Customer $customer): ?User
    {
        if (!$customer->email) {
            return null;
        }

        // First try by stripe_id if customer has one set
        if ($customer->id) {
            $user = User::where('stripe_id', $customer->id)->first();
            if ($user) {
                return $user;
            }
        }

        // Then try by email
        return User::where('email', $customer->email)->first();
    }

    private function findMembershipLevelForSubscription(object $subscription): ?MembershipLevel
    {
        if (empty($subscription->items->data)) {
            return null;
        }

        $priceId = $subscription->items->data[0]->price->id;
        
        return MembershipLevel::whereJsonContains('metadata->stripe_price_id', $priceId)->first();
    }

    private function handleApiError(ApiErrorException $e, string $context): void
    {
        $this->stats['errors']++;
        $this->report['errors'][] = [
            'type' => 'stripe_api_error',
            'context' => $context,
            'error_type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getStripeCode(),
        ];

        $this->error("❌ Stripe API error while {$context}: {$e->getMessage()}");
        
        Log::error('[SyncStripeSubscriptionsCommand] Stripe API error', [
            'context' => $context,
            'error_type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getStripeCode(),
        ]);
    }

    private function displayResults(): void
    {
        $this->newLine();
        $this->info('📊 Synchronization Results:');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Customers Processed', $this->stats['customers_processed']],
                ['Customers Matched', $this->stats['customers_matched']],
                ['Customers Unmatched', $this->stats['customers_unmatched']],
                ['Subscriptions Processed', $this->stats['subscriptions_processed']],
                ['Subscriptions Synced', $this->stats['subscriptions_synced']],
                ['Subscriptions Skipped', $this->stats['subscriptions_skipped']],
                ['Memberships Created', $this->stats['memberships_created']],
                ['Errors', $this->stats['errors']],
            ]
        );

        if ($this->stats['errors'] > 0) {
            $this->newLine();
            $this->warn('⚠️  Some errors occurred during sync. Use --export-report to get detailed error information.');
        }

        if ($this->stats['customers_unmatched'] > 0) {
            $this->newLine();
            $this->warn("⚠️  {$this->stats['customers_unmatched']} customers could not be matched to local users.");
            $this->info('💡 These customers may need to be manually reviewed or may be test/deleted accounts.');
        }

        if (!$this->option('dry-run') && $this->stats['memberships_created'] > 0) {
            $this->newLine();
            $this->info('🎉 Success! Historical subscriptions have been synced with local memberships.');
            $this->info('💡 Future webhook events will automatically maintain these memberships.');
        }

        Log::info('[SyncStripeSubscriptionsCommand] Sync completed', $this->stats);
    }

    private function exportReport(): void
    {
        $filename = 'stripe_sync_report_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $filepath = storage_path('app/reports/' . $filename);
        
        // Ensure reports directory exists
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $csvData = [];
        
        // Add summary
        $csvData[] = ['STRIPE SUBSCRIPTION SYNC REPORT'];
        $csvData[] = ['Generated', now()->toDateTimeString()];
        $csvData[] = ['Dry Run', $this->option('dry-run') ? 'Yes' : 'No'];
        $csvData[] = [];
        
        // Add statistics
        $csvData[] = ['STATISTICS'];
        foreach ($this->stats as $key => $value) {
            $csvData[] = [ucwords(str_replace('_', ' ', $key)), $value];
        }
        $csvData[] = [];

        // Add matched customers
        if (!empty($this->report['matched_customers'])) {
            $csvData[] = ['MATCHED CUSTOMERS'];
            $csvData[] = ['Stripe Customer ID', 'User ID', 'Email', 'Previous Stripe ID'];
            foreach ($this->report['matched_customers'] as $customer) {
                $csvData[] = [
                    $customer['stripe_customer_id'],
                    $customer['user_id'],
                    $customer['email'],
                    $customer['stripe_id_before'] ?? 'None',
                ];
            }
            $csvData[] = [];
        }

        // Add unmatched customers
        if (!empty($this->report['unmatched_customers'])) {
            $csvData[] = ['UNMATCHED CUSTOMERS'];
            $csvData[] = ['Stripe Customer ID', 'Email', 'Created', 'Reason'];
            foreach ($this->report['unmatched_customers'] as $customer) {
                $csvData[] = [
                    $customer['stripe_customer_id'],
                    $customer['email'],
                    date('Y-m-d H:i:s', $customer['created']),
                    $customer['reason'],
                ];
            }
            $csvData[] = [];
        }

        // Add synced subscriptions
        if (!empty($this->report['synced_subscriptions'])) {
            $csvData[] = ['SYNCED SUBSCRIPTIONS'];
            $csvData[] = ['Subscription ID', 'User ID', 'Membership ID', 'Level ID', 'Status', 'Expires At'];
            foreach ($this->report['synced_subscriptions'] as $subscription) {
                $csvData[] = [
                    $subscription['subscription_id'],
                    $subscription['user_id'],
                    $subscription['membership_id'],
                    $subscription['membership_level_id'],
                    $subscription['status'],
                    $subscription['expires_at'],
                ];
            }
            $csvData[] = [];
        }

        // Add errors
        if (!empty($this->report['errors'])) {
            $csvData[] = ['ERRORS'];
            $csvData[] = ['Type', 'Subscription ID', 'User ID', 'Message'];
            foreach ($this->report['errors'] as $error) {
                $csvData[] = [
                    $error['type'],
                    $error['subscription_id'] ?? 'N/A',
                    $error['user_id'] ?? 'N/A',
                    $error['message'],
                ];
            }
        }

        // Write CSV
        $file = fopen($filepath, 'w');
        foreach ($csvData as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        $this->newLine();
        $this->info("📄 Detailed report exported to: {$filepath}");
        $this->info("💡 Use this report to review sync results and manually handle any issues.");
    }
}