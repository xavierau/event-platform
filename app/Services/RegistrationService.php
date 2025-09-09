<?php

namespace App\Services;

use App\Actions\Registration\CreateUserWithSubscriptionAction;
use App\Actions\Registration\InitiateSubscriptionCheckoutAction;
use App\DataTransferObjects\Registration\RegistrationWithSubscriptionData;
use App\DataTransferObjects\Stripe\CheckoutSessionData;
use App\Models\RegistrationAuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RegistrationService
{
    public function __construct(
        private CreateUserWithSubscriptionAction $createUserAction,
        private InitiateSubscriptionCheckoutAction $checkoutAction
    ) {}
    
    public function registerWithSubscription(RegistrationWithSubscriptionData $data): array
    {
        $flowId = session('registration_flow_id') ?? RegistrationAuditLog::generateFlowId();

        // Log user creation start
        RegistrationAuditLog::logStep([
            'flow_id' => $flowId,
            'step' => 'user_creation',
            'action' => 'user_creation_started',
            'status' => 'pending',
            'message' => 'Starting user creation process',
            'email' => $data->email,
            'selected_plan' => $data->selected_price_id,
            'request_data' => [
                'name' => $data->name,
                'email' => $data->email,
                'mobile_number' => $data->mobile_number,
                'selected_price_id' => $data->selected_price_id,
            ],
        ]);

        Log::channel('registration')->info('Starting user creation', [
            'flow_id' => $flowId,
            'email' => $data->email,
            'selected_plan' => $data->selected_price_id,
        ]);

        try {
            $user = $this->createUserAction->execute($data);

            // Log user creation success
            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'user_creation',
                'action' => 'user_created',
                'status' => 'success',
                'message' => 'User created successfully',
                'user_id' => $user->id,
                'email' => $user->email,
                'selected_plan' => $data->selected_price_id,
                'response_data' => [
                    'user_id' => $user->id,
                    'user_created_at' => $user->created_at->toISOString(),
                ],
            ]);

            Log::channel('registration')->info('User created successfully', [
                'flow_id' => $flowId,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            
        } catch (\Exception $e) {
            // Log user creation failure
            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'user_creation',
                'action' => 'user_creation_failed',
                'status' => 'failed',
                'message' => 'User creation failed',
                'email' => $data->email,
                'selected_plan' => $data->selected_price_id,
                'error_message' => $e->getMessage(),
                'metadata' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ]);

            Log::channel('registration')->error('User creation failed', [
                'flow_id' => $flowId,
                'email' => $data->email,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            throw $e;
        }

        // Log registration event
        try {
            event(new Registered($user));

            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'registration_event',
                'action' => 'registered_event_fired',
                'status' => 'success',
                'message' => 'Registration event fired successfully',
                'user_id' => $user->id,
                'email' => $user->email,
                'selected_plan' => $data->selected_price_id,
            ]);

        } catch (\Exception $e) {
            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'registration_event',
                'action' => 'registered_event_failed',
                'status' => 'failed',
                'message' => 'Registration event failed to fire',
                'user_id' => $user->id,
                'email' => $user->email,
                'selected_plan' => $data->selected_price_id,
                'error_message' => $e->getMessage(),
            ]);

            Log::channel('registration')->error('Registration event failed', [
                'flow_id' => $flowId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
        
        // Log user login
        try {
            Auth::login($user);

            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'user_authentication',
                'action' => 'user_logged_in',
                'status' => 'success',
                'message' => 'User logged in successfully',
                'user_id' => $user->id,
                'email' => $user->email,
                'selected_plan' => $data->selected_price_id,
            ]);

        } catch (\Exception $e) {
            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'user_authentication',
                'action' => 'user_login_failed',
                'status' => 'failed',
                'message' => 'User login failed',
                'user_id' => $user->id,
                'email' => $user->email,
                'selected_plan' => $data->selected_price_id,
                'error_message' => $e->getMessage(),
            ]);

            Log::channel('registration')->error('User login failed after creation', [
                'flow_id' => $flowId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Log checkout data preparation
        RegistrationAuditLog::logStep([
            'flow_id' => $flowId,
            'step' => 'checkout_preparation',
            'action' => 'checkout_data_prepared',
            'status' => 'success',
            'message' => 'Checkout data prepared',
            'user_id' => $user->id,
            'email' => $user->email,
            'selected_plan' => $data->selected_price_id,
            'response_data' => [
                'price_id' => $data->selected_price_id,
                'trial_days' => $this->getTrialDays($data->selected_price_id),
                'allow_promotion_codes' => true,
            ],
        ]);
        
        $checkoutData = CheckoutSessionData::from([
            'price_id' => $data->selected_price_id,
            'success_url' => route('register.subscription.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('register.subscription.cancel'),
            'customer_email' => $user->email,
            'metadata' => [
                'user_id' => $user->id,
                'registration_flow' => true,
                'flow_id' => $flowId,
            ],
            'trial_days' => $this->getTrialDays($data->selected_price_id),
            'allow_promotion_codes' => true,
        ]);

        // Log checkout execution
        try {
            $checkout = $this->checkoutAction->execute($user, $checkoutData);

            $checkoutType = is_array($checkout) ? ($checkout['type'] ?? 'unknown') : 'redirect';

            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'checkout_execution',
                'action' => 'checkout_created',
                'status' => 'success',
                'message' => 'Checkout session created successfully',
                'user_id' => $user->id,
                'email' => $user->email,
                'selected_plan' => $data->selected_price_id,
                'response_data' => [
                    'checkout_type' => $checkoutType,
                    'is_free_plan' => $checkoutType === 'free',
                ],
            ]);

            Log::channel('registration')->info('Checkout created successfully', [
                'flow_id' => $flowId,
                'user_id' => $user->id,
                'email' => $user->email,
                'checkout_type' => $checkoutType,
            ]);
            
        } catch (\Exception $e) {
            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'checkout_execution',
                'action' => 'checkout_failed',
                'status' => 'failed',
                'message' => 'Checkout session creation failed',
                'user_id' => $user->id,
                'email' => $user->email,
                'selected_plan' => $data->selected_price_id,
                'error_message' => $e->getMessage(),
                'metadata' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ]);

            Log::channel('registration')->error('Checkout creation failed', [
                'flow_id' => $flowId,
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            throw $e;
        }
        
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