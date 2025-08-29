<?php

namespace App\Actions\Admin;

use App\Modules\Membership\Models\MembershipLevel;
use Laravel\Cashier\Cashier;
use Stripe\Product;
use Stripe\Price;

class SyncMembershipWithStripeAction
{
    public function execute(MembershipLevel $membershipLevel): array
    {
        $results = [
            'success' => false,
            'message' => '',
            'product_synced' => false,
            'price_synced' => false,
            'stripe_product_id' => $membershipLevel->stripe_product_id,
            'stripe_price_id' => $membershipLevel->stripe_price_id,
        ];

        try {
            $stripe = Cashier::stripe();
            
            // 1. Sync or Create Product
            $product = $this->syncProduct($stripe, $membershipLevel);
            $results['product_synced'] = true;
            $results['stripe_product_id'] = $product->id;
            
            // 2. Sync or Create Price
            $price = $this->syncPrice($stripe, $membershipLevel, $product->id);
            $results['price_synced'] = true;
            $results['stripe_price_id'] = $price->id;
            
            // 3. Update membership level with Stripe IDs
            $membershipLevel->update([
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
            ]);
            
            $results['success'] = true;
            $results['message'] = 'Successfully synced with Stripe';
            
        } catch (\Exception $e) {
            $results['message'] = 'Failed to sync with Stripe: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    private function syncProduct($stripe, MembershipLevel $membershipLevel): Product
    {
        if ($membershipLevel->stripe_product_id) {
            try {
                // Try to retrieve existing product
                $product = $stripe->products->retrieve($membershipLevel->stripe_product_id);
                
                // Update product if needed
                $updateData = [];
                $expectedName = $membershipLevel->name['en'] ?? $membershipLevel->slug;
                $expectedDescription = $membershipLevel->description['en'] ?? null;
                
                if ($product->name !== $expectedName) {
                    $updateData['name'] = $expectedName;
                }
                
                if ($expectedDescription && $product->description !== $expectedDescription) {
                    $updateData['description'] = $expectedDescription;
                }
                
                if (!empty($updateData)) {
                    $product = $stripe->products->update($product->id, $updateData);
                }
                
                return $product;
                
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Product not found, create new one
            }
        }
        
        // Create new product
        return $stripe->products->create([
            'name' => $membershipLevel->name['en'] ?? $membershipLevel->slug,
            'description' => $membershipLevel->description['en'] ?? null,
            'metadata' => [
                'membership_level_id' => $membershipLevel->id,
                'membership_level_slug' => $membershipLevel->slug,
            ],
        ]);
    }
    
    private function syncPrice($stripe, MembershipLevel $membershipLevel, string $productId): Price
    {
        if ($membershipLevel->stripe_price_id) {
            try {
                // Try to retrieve existing price
                return $stripe->prices->retrieve($membershipLevel->stripe_price_id);
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Price not found, create new one
            }
        }
        
        // Create new price
        $priceData = [
            'product' => $productId,
            'currency' => config('cashier.currency', 'usd'),
            'metadata' => [
                'membership_level_id' => $membershipLevel->id,
                'membership_level_slug' => $membershipLevel->slug,
            ],
        ];
        
        if ($membershipLevel->price === 0) {
            // Free plan
            $priceData['unit_amount'] = 0;
        } else {
            $priceData['unit_amount'] = $membershipLevel->price;
            
            if ($membershipLevel->duration_months) {
                // Recurring subscription
                $priceData['recurring'] = [
                    'interval' => $membershipLevel->duration_months === 1 ? 'month' : 'month',
                    'interval_count' => $membershipLevel->duration_months,
                ];
            }
        }
        
        return $stripe->prices->create($priceData);
    }
    
    public function syncAllMembershipLevels(): array
    {
        $results = [
            'total' => 0,
            'synced' => 0,
            'failed' => 0,
            'details' => [],
        ];
        
        $membershipLevels = MembershipLevel::all();
        $results['total'] = $membershipLevels->count();
        
        foreach ($membershipLevels as $level) {
            $syncResult = $this->execute($level);
            
            if ($syncResult['success']) {
                $results['synced']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][$level->id] = $syncResult;
        }
        
        return $results;
    }
}