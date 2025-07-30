<?php

namespace App\Console\Commands;

use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Product;
use Stripe\Price;
use Stripe\Exception\ApiErrorException;

class SyncStripeProductsCommand extends Command
{
    protected $signature = 'stripe:sync-products 
                            {--dry-run : Show what would be created without actually creating records}
                            {--update-existing : Update existing MembershipLevel records if they have matching stripe_product_id}';

    protected $description = 'Sync Stripe products and prices to create corresponding MembershipLevel records';

    private array $stats = [
        'products_processed' => 0,
        'prices_processed' => 0,
        'membership_levels_created' => 0,
        'membership_levels_updated' => 0,
        'membership_levels_skipped' => 0,
        'errors' => 0,
    ];

    public function handle(): int
    {
        $this->info('🚀 Starting Stripe Products/Prices synchronization...');
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

        try {
            $this->syncProducts();
            $this->displayResults();
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            Log::error('[SyncStripeProductsCommand] Sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function syncProducts(): void
    {
        $this->info('📦 Fetching Stripe products...');
        
        try {
            // Fetch all active products from Stripe
            $products = Product::all([
                'active' => true,
                'limit' => 100, // Adjust if you have more products
            ]);

            $this->info("Found {$products->count()} active products in Stripe");
            $this->newLine();

            foreach ($products->data as $product) {
                $this->processProduct($product);
            }

        } catch (ApiErrorException $e) {
            throw new \Exception("Stripe API error: {$e->getMessage()}");
        }
    }

    private function processProduct(Product $product): void
    {
        $this->stats['products_processed']++;
        
        $this->info("🔄 Processing product: {$product->name} ({$product->id})");

        try {
            // Fetch all active prices for this product
            $prices = Price::all([
                'product' => $product->id,
                'active' => true,
                'limit' => 50,
            ]);

            if ($prices->count() === 0) {
                $this->warn("  ⚠️  No active prices found for product {$product->name}");
                return;
            }

            foreach ($prices->data as $price) {
                $this->processPrice($product, $price);
            }

        } catch (ApiErrorException $e) {
            $this->error("  ❌ Error fetching prices for product {$product->id}: {$e->getMessage()}");
            $this->stats['errors']++;
            Log::error('[SyncStripeProductsCommand] Error fetching prices', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function processPrice(Product $product, Price $price): void
    {
        $this->stats['prices_processed']++;
        
        // Skip one-time prices, we only want recurring subscriptions
        if (!$price->recurring) {
            $this->warn("  ⏭️  Skipping one-time price {$price->id}");
            return;
        }

        $priceAmount = $price->unit_amount; // In cents
        $currency = strtoupper($price->currency);
        $interval = $price->recurring->interval; // 'month' or 'year'
        $intervalCount = $price->recurring->interval_count;

        $this->info("  💰 Processing price: {$currency} {$priceAmount} every {$intervalCount} {$interval}(s)");

        // Check if MembershipLevel already exists
        $existingLevel = MembershipLevel::whereJsonContains('metadata->stripe_price_id', $price->id)->first();
        
        if ($existingLevel) {
            if ($this->option('update-existing')) {
                $this->updateMembershipLevel($existingLevel, $product, $price);
            } else {
                $this->warn("    ⏭️  MembershipLevel already exists for price {$price->id} (use --update-existing to update)");
                $this->stats['membership_levels_skipped']++;
            }
            return;
        }

        // Create new MembershipLevel
        $this->createMembershipLevel($product, $price);
    }

    private function createMembershipLevel(Product $product, Price $price): void
    {
        $name = $this->generateMembershipName($product, $price);
        $slug = Str::slug($name);
        $durationMonths = $this->calculateDurationMonths($price->recurring);
        
        $membershipData = [
            'name' => ['en' => $name],
            'slug' => $this->ensureUniqueSlug($slug),
            'description' => ['en' => $product->description ?: "Membership plan: {$name}"],
            'price' => $price->unit_amount, // Store in smallest currency unit
            'duration_months' => $durationMonths,
            'is_active' => true,
            'sort_order' => 0,
            'metadata' => [
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
                'stripe_currency' => $price->currency,
                'stripe_interval' => $price->recurring->interval,
                'stripe_interval_count' => $price->recurring->interval_count,
                'created_by_sync' => true,
                'sync_date' => now()->toISOString(),
            ],
        ];

        if ($this->option('dry-run')) {
            $this->info("    🔍 [DRY RUN] Would create MembershipLevel:");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Name', $name],
                    ['Slug', $membershipData['slug']],
                    ['Price', number_format($price->unit_amount / 100, 2) . ' ' . strtoupper($price->currency)],
                    ['Duration', "{$durationMonths} months"],
                    ['Stripe Price ID', $price->id],
                ]
            );
            return;
        }

        try {
            $membershipLevel = MembershipLevel::create($membershipData);
            $this->stats['membership_levels_created']++;
            
            $this->info("    ✅ Created MembershipLevel: {$name} (ID: {$membershipLevel->id})");
            
            Log::info('[SyncStripeProductsCommand] Created MembershipLevel', [
                'membership_level_id' => $membershipLevel->id,
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
                'name' => $name
            ]);
            
        } catch (\Exception $e) {
            $this->error("    ❌ Error creating MembershipLevel: {$e->getMessage()}");
            $this->stats['errors']++;
            
            Log::error('[SyncStripeProductsCommand] Error creating MembershipLevel', [
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
                'error' => $e->getMessage(),
                'data' => $membershipData
            ]);
        }
    }

    private function updateMembershipLevel(MembershipLevel $existingLevel, Product $product, Price $price): void
    {
        $updateData = [
            'price' => $price->unit_amount,
            'metadata' => array_merge($existingLevel->metadata ?? [], [
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
                'stripe_currency' => $price->currency,
                'stripe_interval' => $price->recurring->interval,
                'stripe_interval_count' => $price->recurring->interval_count,
                'last_sync_date' => now()->toISOString(),
            ]),
        ];

        if ($this->option('dry-run')) {
            $this->info("    🔍 [DRY RUN] Would update MembershipLevel ID {$existingLevel->id}");
            return;
        }

        try {
            $existingLevel->update($updateData);
            $this->stats['membership_levels_updated']++;
            
            $this->info("    ✅ Updated MembershipLevel: {$existingLevel->name['en']} (ID: {$existingLevel->id})");
            
            Log::info('[SyncStripeProductsCommand] Updated MembershipLevel', [
                'membership_level_id' => $existingLevel->id,
                'stripe_price_id' => $price->id
            ]);
            
        } catch (\Exception $e) {
            $this->error("    ❌ Error updating MembershipLevel: {$e->getMessage()}");
            $this->stats['errors']++;
        }
    }

    private function generateMembershipName(Product $product, Price $price): string
    {
        $amount = number_format($price->unit_amount / 100, 2);
        $currency = strtoupper($price->currency);
        $interval = $price->recurring->interval;
        $intervalCount = $price->recurring->interval_count;
        
        $periodText = $intervalCount > 1 ? "{$intervalCount} {$interval}s" : $interval;
        
        return "{$product->name} - {$currency} {$amount}/{$periodText}";
    }

    private function calculateDurationMonths(object $recurring): int
    {
        $interval = $recurring->interval;
        $intervalCount = $recurring->interval_count;
        
        return match ($interval) {
            'month' => $intervalCount,
            'year' => $intervalCount * 12,
            'week' => max(1, intval($intervalCount / 4)), // Approximate weeks to months
            'day' => max(1, intval($intervalCount / 30)), // Approximate days to months
            default => 1,
        };
    }

    private function ensureUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 1;
        
        while (MembershipLevel::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }
        
        return $slug;
    }

    private function displayResults(): void
    {
        $this->newLine();
        $this->info('📊 Synchronization Results:');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Products Processed', $this->stats['products_processed']],
                ['Prices Processed', $this->stats['prices_processed']],
                ['MembershipLevels Created', $this->stats['membership_levels_created']],
                ['MembershipLevels Updated', $this->stats['membership_levels_updated']],
                ['MembershipLevels Skipped', $this->stats['membership_levels_skipped']],
                ['Errors', $this->stats['errors']],
            ]
        );

        if ($this->stats['errors'] > 0) {
            $this->newLine();
            $this->warn('⚠️  Some errors occurred during sync. Check the logs for details.');
        }

        if (!$this->option('dry-run') && $this->stats['membership_levels_created'] > 0) {
            $this->newLine();
            $this->info('🎉 Success! Your webhook handlers should now work properly with the new MembershipLevel records.');
            $this->info('💡 Tip: Run your webhooks again for any failed subscription events.');
        }

        Log::info('[SyncStripeProductsCommand] Sync completed', $this->stats);
    }
}