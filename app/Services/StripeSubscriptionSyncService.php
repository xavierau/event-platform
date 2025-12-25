<?php

namespace App\Services;

use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class StripeSubscriptionSyncService
{
    /**
     * Create or update UserMembership from Stripe subscription created event.
     */
    public function handleSubscriptionCreated(object $subscription): ?UserMembership
    {
        Log::info('[StripeSubscriptionSyncService] ════════════════════════════════════════════════════════');
        Log::info('[StripeSubscriptionSyncService] 🎯 STEP 1: Webhook Received - Processing subscription.created', [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer,
            'status' => $subscription->status,
            'items_count' => count($subscription->items->data ?? []),
            'price_id' => $subscription->items->data[0]->price->id ?? 'unknown',
            'product_id' => $subscription->items->data[0]->price->product ?? 'unknown',
        ]);

        return DB::transaction(function () use ($subscription) {
            // STEP 2: Find User
            Log::info('[StripeSubscriptionSyncService] 👤 STEP 2: Finding user by Stripe customer ID', [
                'customer_id' => $subscription->customer,
            ]);

            $user = $this->findUserByStripeCustomer($subscription->customer);
            if (!$user) {
                Log::error('[StripeSubscriptionSyncService] ❌ STEP 2 FAILED: User not found for subscription.created', [
                    'subscription_id' => $subscription->id,
                    'customer_id' => $subscription->customer,
                    'flow_status' => 'ABORTED - No user found',
                ]);
                Log::info('[StripeSubscriptionSyncService] ════════════════════════════════════════════════════════');
                return null;
            }

            Log::info('[StripeSubscriptionSyncService] ✅ STEP 2 SUCCESS: User found', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_stripe_id' => $user->stripe_id,
            ]);

            // STEP 3: Find Membership Level
            Log::info('[StripeSubscriptionSyncService] 🏷️ STEP 3: Finding membership level by Stripe price', [
                'subscription_id' => $subscription->id,
                'price_id' => $subscription->items->data[0]->price->id ?? 'unknown',
            ]);

            $membershipLevel = $this->findMembershipLevelByStripePrice($subscription);
            if (!$membershipLevel) {
                Log::error('[StripeSubscriptionSyncService] ❌ STEP 3 FAILED: Membership level not found for subscription', [
                    'subscription_id' => $subscription->id,
                    'price_id' => $subscription->items->data[0]->price->id ?? 'unknown',
                    'user_id' => $user->id,
                    'flow_status' => 'ABORTED - No membership level matches price ID',
                ]);
                Log::info('[StripeSubscriptionSyncService] ════════════════════════════════════════════════════════');
                return null;
            }

            Log::info('[StripeSubscriptionSyncService] ✅ STEP 3 SUCCESS: Membership level found', [
                'membership_level_id' => $membershipLevel->id,
                'membership_level_name' => $membershipLevel->name,
                'membership_level_stripe_price_id' => $membershipLevel->stripe_price_id,
            ]);

            // STEP 4: Check for existing membership or create new one
            Log::info('[StripeSubscriptionSyncService] 📋 STEP 4: Checking for existing membership', [
                'subscription_id' => $subscription->id,
            ]);

            $existing = UserMembership::findByStripeSubscription($subscription->id);
            if ($existing) {
                Log::info('[StripeSubscriptionSyncService] ♻️ STEP 4: Found existing membership - updating', [
                    'membership_id' => $existing->id,
                    'subscription_id' => $subscription->id,
                    'current_status' => $existing->status,
                ]);
                $existing->updateFromStripeSubscription($subscription);

                Log::info('[StripeSubscriptionSyncService] ✅ FLOW COMPLETE: Existing membership updated', [
                    'membership_id' => $existing->id,
                    'user_id' => $existing->user_id,
                    'status' => $existing->status,
                ]);
                Log::info('[StripeSubscriptionSyncService] ════════════════════════════════════════════════════════');
                return $existing;
            }

            // Create new membership
            Log::info('[StripeSubscriptionSyncService] 🆕 STEP 4: No existing membership - creating new one', [
                'user_id' => $user->id,
                'membership_level_id' => $membershipLevel->id,
                'stripe_status' => $subscription->status,
            ]);

            $membership = UserMembership::create([
                'user_id' => $user->id,
                'membership_level_id' => $membershipLevel->id,
                'started_at' => Carbon::createFromTimestamp($subscription->current_period_start),
                'expires_at' => Carbon::createFromTimestamp($subscription->current_period_end),
                'status' => $this->mapStripeStatusToMembershipStatus($subscription->status),
                'payment_method' => PaymentMethod::STRIPE,
                'stripe_subscription_id' => $subscription->id,
                'stripe_customer_id' => $subscription->customer,
                'subscription_metadata' => [
                    'stripe_status' => $subscription->status,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'cancel_at_period_end' => $subscription->cancel_at_period_end,
                ],
                'auto_renew' => !$subscription->cancel_at_period_end,
            ]);

            Log::info('[StripeSubscriptionSyncService] ✅ FLOW COMPLETE: New membership created successfully', [
                'membership_id' => $membership->id,
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'membership_level' => $membershipLevel->name,
                'status' => $membership->status->value ?? $membership->status,
                'started_at' => $membership->started_at,
                'expires_at' => $membership->expires_at,
            ]);
            Log::info('[StripeSubscriptionSyncService] ════════════════════════════════════════════════════════');

            return $membership;
        });
    }

    /**
     * Update UserMembership from Stripe subscription updated event.
     */
    public function handleSubscriptionUpdated(object $subscription): ?UserMembership
    {
        Log::info('[StripeSubscriptionSyncService] Processing subscription.updated', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status
        ]);

        $membership = UserMembership::findByStripeSubscription($subscription->id);
        if (!$membership) {
            Log::warning('[StripeSubscriptionSyncService] Membership not found for subscription.updated', [
                'subscription_id' => $subscription->id
            ]);
            return null;
        }

        return DB::transaction(function () use ($membership, $subscription) {
            $membership->updateFromStripeSubscription($subscription);
            
            // Handle plan changes
            $membershipLevel = $this->findMembershipLevelByStripePrice($subscription);
            if ($membershipLevel && $membership->membership_level_id !== $membershipLevel->id) {
                Log::info('[StripeSubscriptionSyncService] Membership level changed', [
                    'membership_id' => $membership->id,
                    'old_level_id' => $membership->membership_level_id,
                    'new_level_id' => $membershipLevel->id
                ]);
                $membership->membership_level_id = $membershipLevel->id;
            }

            // Update auto_renew based on cancellation status
            $membership->auto_renew = !$subscription->cancel_at_period_end;
            $membership->save();

            Log::info('[StripeSubscriptionSyncService] Updated membership for subscription', [
                'membership_id' => $membership->id,
                'subscription_id' => $subscription->id,
                'new_status' => $membership->status
            ]);

            return $membership;
        });
    }

    /**
     * Handle Stripe subscription deleted/cancelled event.
     */
    public function handleSubscriptionDeleted(object $subscription): ?UserMembership
    {
        Log::info('[StripeSubscriptionSyncService] Processing subscription.deleted', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status
        ]);

        $membership = UserMembership::findByStripeSubscription($subscription->id);
        if (!$membership) {
            Log::warning('[StripeSubscriptionSyncService] Membership not found for subscription.deleted', [
                'subscription_id' => $subscription->id
            ]);
            return null;
        }

        return DB::transaction(function () use ($membership, $subscription) {
            // Update final subscription metadata
            $membership->updateFromStripeSubscription($subscription);
            
            // Set status to cancelled and disable auto-renewal
            $membership->status = MembershipStatus::CANCELLED;
            $membership->auto_renew = false;
            
            // If subscription ended immediately, expire the membership now
            if ($subscription->ended_at && $subscription->ended_at <= time()) {
                $membership->expires_at = Carbon::createFromTimestamp($subscription->ended_at);
                if ($membership->expires_at <= now()) {
                    $membership->status = MembershipStatus::EXPIRED;
                }
            }

            $membership->save();

            Log::info('[StripeSubscriptionSyncService] Cancelled membership for subscription', [
                'membership_id' => $membership->id,
                'subscription_id' => $subscription->id,
                'final_status' => $membership->status
            ]);

            return $membership;
        });
    }

    /**
     * Handle successful invoice payment for subscription renewal.
     */
    public function handleInvoicePaymentSucceeded(object $invoice): ?UserMembership
    {
        if (!$invoice->subscription) {
            Log::debug('[StripeSubscriptionSyncService] Invoice not related to subscription', [
                'invoice_id' => $invoice->id
            ]);
            return null;
        }

        Log::info('[StripeSubscriptionSyncService] Processing invoice.payment_succeeded', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription
        ]);

        $membership = UserMembership::findByStripeSubscription($invoice->subscription);
        if (!$membership) {
            Log::warning('[StripeSubscriptionSyncService] Membership not found for invoice payment', [
                'invoice_id' => $invoice->id,
                'subscription_id' => $invoice->subscription
            ]);
            return null;
        }

        return DB::transaction(function () use ($membership, $invoice) {
            // Update membership period based on invoice period
            if ($invoice->period_end) {
                $membership->expires_at = Carbon::createFromTimestamp($invoice->period_end);
            }

            // Ensure membership is active if payment succeeded
            if ($membership->status !== MembershipStatus::ACTIVE) {
                $membership->status = MembershipStatus::ACTIVE;
                Log::info('[StripeSubscriptionSyncService] Reactivated membership after successful payment', [
                    'membership_id' => $membership->id,
                    'invoice_id' => $invoice->id
                ]);
            }

            $membership->save();

            Log::info('[StripeSubscriptionSyncService] Extended membership for successful payment', [
                'membership_id' => $membership->id,
                'new_expiry' => $membership->expires_at,
                'invoice_id' => $invoice->id
            ]);

            return $membership;
        });
    }

    /**
     * Handle failed invoice payment for subscription.
     */
    public function handleInvoicePaymentFailed(object $invoice): ?UserMembership
    {
        if (!$invoice->subscription) {
            Log::debug('[StripeSubscriptionSyncService] Invoice not related to subscription', [
                'invoice_id' => $invoice->id
            ]);
            return null;
        }

        Log::info('[StripeSubscriptionSyncService] Processing invoice.payment_failed', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription,
            'attempt_count' => $invoice->attempt_count ?? 1
        ]);

        $membership = UserMembership::findByStripeSubscription($invoice->subscription);
        if (!$membership) {
            Log::warning('[StripeSubscriptionSyncService] Membership not found for failed invoice', [
                'invoice_id' => $invoice->id,
                'subscription_id' => $invoice->subscription
            ]);
            return null;
        }

        return DB::transaction(function () use ($membership, $invoice) {
            // Suspend membership on payment failure (let Stripe handle retry logic)
            if ($membership->status === MembershipStatus::ACTIVE) {
                $membership->status = MembershipStatus::SUSPENDED;
                Log::info('[StripeSubscriptionSyncService] Suspended membership due to payment failure', [
                    'membership_id' => $membership->id,
                    'invoice_id' => $invoice->id,
                    'attempt_count' => $invoice->attempt_count ?? 1
                ]);
            }

            // Update metadata with failure info
            $metadata = $membership->subscription_metadata ?? [];
            $metadata['last_payment_failure'] = [
                'invoice_id' => $invoice->id,
                'attempt_count' => $invoice->attempt_count ?? 1,
                'failure_date' => time(),
            ];
            $membership->subscription_metadata = $metadata;
            $membership->save();

            return $membership;
        });
    }

    /**
     * Resolve user from webhook by Stripe customer ID.
     * Uses two-tier resolution: stripe_id first, then email bootstrap.
     */
    private function findUserByStripeCustomer(string $customerId): ?User
    {
        // Tier 1: Try existing stripe_id or stripe_customer_ids (fast path for existing linked users)
        $user = User::withStripeCustomerId($customerId)->first();
        if ($user) {
            return $user;
        }

        // Tier 2: Bootstrap new users by fetching Stripe customer and matching email
        return $this->bootstrapUserFromStripeCustomer($customerId);
    }

    /**
     * Bootstrap user connection by fetching Stripe customer and matching by email.
     */
    private function bootstrapUserFromStripeCustomer(string $customerId): ?User
    {
        try {
            // Initialize Stripe API key if not already set
            if (!\Stripe\Stripe::getApiKey()) {
                \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            }

            // Fetch full customer details from Stripe API
            $stripeCustomer = \Stripe\Customer::retrieve($customerId);
            
            if (!$stripeCustomer->email) {
                Log::warning('[StripeSubscriptionSyncService] Stripe customer has no email address', [
                    'customer_id' => $customerId
                ]);
                return null;
            }

            // Find local user by email (requires unique email constraint)
            $user = User::where('email', $stripeCustomer->email)->first();
            
            if ($user) {
                // Link accounts with enhanced multi-customer support
                return DB::transaction(function () use ($user, $customerId, $stripeCustomer) {
                    // Double-check user wasn't linked by another process
                    $user->refresh();
                    
                    // Check if user already has this customer ID
                    if ($user->hasStripeCustomerId($customerId)) {
                        Log::info('[StripeSubscriptionSyncService] User already linked to this Stripe customer', [
                            'user_id' => $user->id,
                            'stripe_customer_id' => $customerId
                        ]);
                        return $user;
                    }

                    // Add the new customer ID to the user's collection
                    $user->addStripeCustomerId($customerId);
                    
                    Log::info('[StripeSubscriptionSyncService] Successfully linked user to additional Stripe customer via email', [
                        'user_id' => $user->id,
                        'email' => $stripeCustomer->email,
                        'stripe_customer_id' => $customerId,
                        'primary_stripe_id' => $user->stripe_id,
                        'all_customer_ids' => $user->getAllStripeCustomerIds()
                    ]);

                    return $user;
                });
            }

            // No matching user found - log as orphaned event
            Log::warning('[StripeSubscriptionSyncService] Orphaned webhook: No user found for Stripe customer', [
                'stripe_customer_id' => $customerId,
                'customer_email' => $stripeCustomer->email,
                'customer_created' => $stripeCustomer->created ?? null
            ]);

            return null;

        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('[StripeSubscriptionSyncService] Stripe API error while fetching customer for webhook', [
                'customer_id' => $customerId,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('[StripeSubscriptionSyncService] Unexpected error during user resolution', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Find membership level by Stripe price ID from subscription.
     */
    private function findMembershipLevelByStripePrice(object $subscription): ?MembershipLevel
    {
        Log::info('[StripeSubscriptionSyncService] 🔍 Finding membership level by Stripe price', [
            'subscription_id' => $subscription->id,
            'has_items' => !empty($subscription->items->data),
            'items_count' => count($subscription->items->data ?? []),
        ]);

        if (empty($subscription->items->data)) {
            Log::warning('[StripeSubscriptionSyncService] ❌ Subscription has no items', [
                'subscription_id' => $subscription->id,
            ]);
            return null;
        }

        $priceId = $subscription->items->data[0]->price->id;
        $productId = $subscription->items->data[0]->price->product ?? null;

        Log::info('[StripeSubscriptionSyncService] 🏷️ Extracted Stripe identifiers from subscription', [
            'subscription_id' => $subscription->id,
            'price_id' => $priceId,
            'product_id' => $productId,
        ]);

        // Primary lookup: Use the dedicated stripe_price_id column
        $level = MembershipLevel::where('stripe_price_id', $priceId)->first();

        if ($level) {
            Log::info('[StripeSubscriptionSyncService] ✅ Found membership level by stripe_price_id column', [
                'subscription_id' => $subscription->id,
                'price_id' => $priceId,
                'membership_level_id' => $level->id,
                'membership_level_name' => $level->name,
            ]);
            return $level;
        }

        Log::debug('[StripeSubscriptionSyncService] 🔄 stripe_price_id column lookup failed, trying metadata fallback', [
            'subscription_id' => $subscription->id,
            'price_id' => $priceId,
        ]);

        // Fallback: Check metadata->stripe_price_id for backward compatibility
        $level = MembershipLevel::whereJsonContains('metadata->stripe_price_id', $priceId)->first();

        if ($level) {
            Log::info('[StripeSubscriptionSyncService] ✅ Found membership level by metadata->stripe_price_id', [
                'subscription_id' => $subscription->id,
                'price_id' => $priceId,
                'membership_level_id' => $level->id,
                'membership_level_name' => $level->name,
            ]);
            return $level;
        }

        // Log all available membership levels for debugging
        $allLevels = MembershipLevel::select('id', 'name', 'stripe_price_id', 'stripe_product_id', 'metadata')
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'stripe_price_id' => $l->stripe_price_id,
                'stripe_product_id' => $l->stripe_product_id,
                'metadata_stripe_price_id' => $l->metadata['stripe_price_id'] ?? null,
            ]);

        Log::error('[StripeSubscriptionSyncService] ❌ No membership level found for Stripe price', [
            'subscription_id' => $subscription->id,
            'searched_price_id' => $priceId,
            'searched_product_id' => $productId,
            'available_membership_levels' => $allLevels->toArray(),
            'hint' => 'Ensure MembershipLevel.stripe_price_id matches the Stripe price ID',
        ]);

        return null;
    }

    /**
     * Map Stripe subscription status to membership status.
     */
    private function mapStripeStatusToMembershipStatus(string $stripeStatus): MembershipStatus
    {
        return match ($stripeStatus) {
            'active' => MembershipStatus::ACTIVE,
            'past_due' => MembershipStatus::SUSPENDED,
            'canceled', 'unpaid' => MembershipStatus::CANCELLED,
            'incomplete', 'incomplete_expired' => MembershipStatus::PENDING,
            default => MembershipStatus::PENDING,
        };
    }
}