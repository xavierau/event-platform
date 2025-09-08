<?php

namespace App\Services;

use App\Actions\Registration\CreateUserWithSubscriptionAction;
use App\Actions\Registration\InitiateSubscriptionCheckoutAction;
use App\DataTransferObjects\Registration\RegistrationWithSubscriptionData;
use App\DataTransferObjects\Stripe\CheckoutSessionData;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class RegistrationService
{
    public function __construct(
        private CreateUserWithSubscriptionAction $createUserAction,
        private InitiateSubscriptionCheckoutAction $checkoutAction
    ) {}
    
    public function registerWithSubscription(RegistrationWithSubscriptionData $data): array
    {
        $user = $this->createUserAction->execute($data);
        
        event(new Registered($user));
        
        Auth::login($user);
        
        $checkoutData = CheckoutSessionData::from([
            'price_id' => $data->selected_price_id,
            'success_url' => route('register.subscription.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('register.subscription.cancel'),
            'customer_email' => $user->email,
            'metadata' => [
                'user_id' => $user->id,
                'registration_flow' => true,
            ],
            'trial_days' => $this->getTrialDays($data->selected_price_id),
            'allow_promotion_codes' => true,
        ]);
        
        $checkout = $this->checkoutAction->execute($user, $checkoutData);
        
        return [
            'user' => $user,
            'checkout' => $checkout,
        ];
    }
    
    private function getTrialDays(string $priceId): ?int
    {
        return match($priceId) {
            config('services.stripe.prices.premium', 'price_premium_monthly') => 14,
            config('services.stripe.prices.vip', 'price_vip_monthly') => 7,
            default => null,
        };
    }
}