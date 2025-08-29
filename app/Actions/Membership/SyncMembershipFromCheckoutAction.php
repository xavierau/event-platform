<?php

namespace App\Actions\Membership;

use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use Stripe\Checkout\Session;

class SyncMembershipFromCheckoutAction
{
    public function execute(Session $session): ?UserMembership
    {
        if ($session->mode !== 'subscription') {
            return null;
        }
        
        $user = User::where('stripe_id', $session->customer)->first();
        if (!$user) {
            return null;
        }
        
        $membershipLevelId = $session->metadata->membership_level_id ?? null;
        if (!$membershipLevelId) {
            return null;
        }
        
        $membershipLevel = MembershipLevel::find($membershipLevelId);
        if (!$membershipLevel) {
            return null;
        }
        
        $existing = UserMembership::where('stripe_subscription_id', $session->subscription)
            ->first();
            
        if ($existing) {
            return $existing;
        }
        
        return UserMembership::create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'started_at' => now(),
            'expires_at' => $membershipLevel->duration_months 
                ? now()->addMonths($membershipLevel->duration_months)
                : null,
            'status' => MembershipStatus::ACTIVE,
            'payment_method' => PaymentMethod::STRIPE,
            'stripe_subscription_id' => $session->subscription,
            'stripe_customer_id' => $session->customer,
            'subscription_metadata' => [
                'checkout_session_id' => $session->id,
                'amount_total' => $session->amount_total,
                'currency' => $session->currency,
            ],
            'auto_renew' => true,
        ]);
    }
}