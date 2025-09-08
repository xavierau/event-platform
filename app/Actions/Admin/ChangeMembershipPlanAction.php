<?php

namespace App\Actions\Admin;

use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Membership\Enums\PaymentMethod;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Exceptions\InvalidCustomer;

class ChangeMembershipPlanAction
{
    public function execute(User $user, MembershipLevel $newLevel): array
    {
        $results = [
            'success' => false,
            'message' => '',
            'subscription_updated' => false,
            'membership_updated' => false,
            'old_level_id' => null,
            'new_level_id' => $newLevel->id,
        ];

        try {
            $currentMembership = $user->currentMembership();
            $results['old_level_id'] = $currentMembership?->membership_level_id;

            // Handle free plan (no Stripe subscription needed)
            if ($newLevel->price === 0) {
                return $this->handleFreePlan($user, $newLevel, $currentMembership, $results);
            }

            // Ensure user is a Stripe customer
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer([
                    'metadata' => [
                        'admin_plan_change' => now()->toIso8601String(),
                        'new_plan' => $newLevel->slug,
                    ],
                ]);
            }

            $stripe = Cashier::stripe();

            // Handle existing active subscription
            if ($currentMembership && $currentMembership->stripe_subscription_id) {
                $results = $this->updateExistingSubscription(
                    $stripe, 
                    $currentMembership, 
                    $newLevel, 
                    $results
                );
            } else {
                // Create new subscription
                $results = $this->createNewSubscription($user, $newLevel, $results);
            }

            // Update local membership record
            $this->updateLocalMembership($user, $newLevel, $currentMembership);
            $results['membership_updated'] = true;

            $results['success'] = true;
            $results['message'] = "Successfully changed plan to {$newLevel->name['en']}";

        } catch (\Exception $e) {
            $results['message'] = 'Failed to change membership plan: ' . $e->getMessage();
        }

        return $results;
    }

    private function handleFreePlan(
        User $user, 
        MembershipLevel $newLevel, 
        ?UserMembership $currentMembership, 
        array $results
    ): array {
        // Cancel existing subscription if exists
        if ($currentMembership && $currentMembership->stripe_subscription_id) {
            try {
                $stripe = Cashier::stripe();
                $stripe->subscriptions->cancel($currentMembership->stripe_subscription_id, [
                    'prorate' => true,
                ]);
                $results['subscription_updated'] = true;
            } catch (\Exception $e) {
                // Log but don't fail - we can still update to free plan
                logger()->warning('Failed to cancel Stripe subscription', [
                    'subscription_id' => $currentMembership->stripe_subscription_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update to free plan
        $this->updateLocalMembership($user, $newLevel, $currentMembership, [
            'stripe_subscription_id' => null,
            'payment_method' => PaymentMethod::ADMIN_GRANT,
            'auto_renew' => false,
        ]);

        $results['membership_updated'] = true;
        $results['success'] = true;
        $results['message'] = "Successfully changed to free plan";

        return $results;
    }

    private function updateExistingSubscription(
        $stripe, 
        UserMembership $currentMembership, 
        MembershipLevel $newLevel, 
        array $results
    ): array {
        try {
            $subscription = $stripe->subscriptions->retrieve($currentMembership->stripe_subscription_id);
            
            // Update subscription to new price
            $stripe->subscriptions->update($subscription->id, [
                'items' => [
                    [
                        'id' => $subscription->items->data[0]->id,
                        'price' => $newLevel->stripe_price_id,
                    ],
                ],
                'proration_behavior' => 'always_invoice',
                'metadata' => [
                    'admin_plan_change' => now()->toIso8601String(),
                    'new_plan' => $newLevel->slug,
                    'previous_plan' => $currentMembership->membershipLevel->slug ?? 'unknown',
                ],
            ]);

            $results['subscription_updated'] = true;

        } catch (\Exception $e) {
            throw new \Exception('Failed to update Stripe subscription: ' . $e->getMessage());
        }

        return $results;
    }

    private function createNewSubscription(User $user, MembershipLevel $newLevel, array $results): array
    {
        try {
            $subscription = $user->newSubscription('default', $newLevel->stripe_price_id)
                ->create(null, [
                    'metadata' => [
                        'admin_plan_change' => now()->toIso8601String(),
                        'plan' => $newLevel->slug,
                    ],
                ]);

            $results['subscription_updated'] = true;

        } catch (\Exception $e) {
            throw new \Exception('Failed to create Stripe subscription: ' . $e->getMessage());
        }

        return $results;
    }

    private function updateLocalMembership(
        User $user, 
        MembershipLevel $newLevel, 
        ?UserMembership $currentMembership,
        array $overrides = []
    ): void {
        $membershipData = array_merge([
            'membership_level_id' => $newLevel->id,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => $newLevel->duration_months 
                ? now()->addMonths($newLevel->duration_months) 
                : null,
            'payment_method' => $newLevel->price > 0 ? PaymentMethod::STRIPE : PaymentMethod::ADMIN_GRANT,
            'auto_renew' => $newLevel->price > 0,
        ], $overrides);

        if ($currentMembership) {
            $currentMembership->update($membershipData);
        } else {
            $user->memberships()->create($membershipData);
        }
    }

    public function changePlanForMultipleUsers(array $userIds, MembershipLevel $newLevel): array
    {
        $results = [
            'total' => count($userIds),
            'successful' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            
            if (!$user) {
                $results['failed']++;
                $results['details'][$userId] = [
                    'success' => false,
                    'message' => 'User not found',
                ];
                continue;
            }

            $changeResult = $this->execute($user, $newLevel);
            
            if ($changeResult['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }

            $results['details'][$userId] = $changeResult;
        }

        return $results;
    }
}