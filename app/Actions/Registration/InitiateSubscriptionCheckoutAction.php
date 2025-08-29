<?php

namespace App\Actions\Registration;

use App\DataTransferObjects\Stripe\CheckoutSessionData;
use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use Laravel\Cashier\Checkout;

class InitiateSubscriptionCheckoutAction
{
    public function execute(User $user, CheckoutSessionData $data): Checkout|array
    {
        $membershipLevel = MembershipLevel::where('stripe_price_id', $data->price_id)->firstOrFail();
        
        if ($membershipLevel->price === 0) {
            return $this->handleFreeTier($user, $membershipLevel, $data);
        }
        
        $checkoutBuilder = $user->newSubscription('default', $data->price_id);
        
        if ($data->trial_days) {
            $checkoutBuilder->trialDays($data->trial_days);
        }
        
        if ($data->allow_promotion_codes) {
            $checkoutBuilder->allowPromotionCodes();
        }
        
        return $checkoutBuilder->checkout([
            'success_url' => $data->success_url,
            'cancel_url' => $data->cancel_url,
            'metadata' => array_merge($data->metadata ?? [], [
                'membership_level_id' => $membershipLevel->id,
                'user_id' => $user->id,
            ]),
        ]);
    }
    
    private function handleFreeTier(User $user, MembershipLevel $level, CheckoutSessionData $data): array
    {
        $user->newSubscription('default', $data->price_id)->create();
        
        return [
            'type' => 'free',
            'url' => $data->success_url,
            'membership_level' => $level,
        ];
    }
}