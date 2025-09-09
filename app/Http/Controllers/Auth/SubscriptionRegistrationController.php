<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrationWithSubscriptionRequest;
use App\Models\RegistrationAuditLog;
use App\Services\RegistrationService;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Cashier\Cashier;

class SubscriptionRegistrationController extends Controller
{
    public function __construct(
        private RegistrationService $registrationService
    ) {}
    
    public function create(Request $request)
    {
        // Generate flow ID to track this registration journey
        $flowId = RegistrationAuditLog::generateFlowId();
        
        // Log registration page visit
        RegistrationAuditLog::logStep([
            'flow_id' => $flowId,
            'step' => 'registration_page_visit',
            'action' => 'page_loaded',
            'status' => 'success',
            'message' => 'User visited registration page with pricing plans',
            'metadata' => [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'session_id' => session()->getId(),
                'referer' => $request->header('referer'),
            ],
        ]);

        // Log membership levels loading
        try {
            $membershipLevels = MembershipLevel::active()
                ->ordered()
                ->get()
                ->map(fn($level) => [
                    'id' => $level->id,
                    'name' => $level->name,
                    'description' => $level->description,
                    'price' => $level->price,
                    'price_formatted' => '$' . number_format($level->price / 100, 2),
                    'stripe_price_id' => $level->stripe_price_id,
                    'duration_months' => $level->duration_months,
                    'benefits' => $level->benefits,
                    'is_popular' => $level->metadata['is_popular'] ?? false,
                    'slug' => $level->slug,
                ]);

            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'membership_levels_loading',
                'action' => 'data_loaded',
                'status' => 'success',
                'message' => 'Successfully loaded membership levels for display',
                'response_data' => [
                    'membership_levels_count' => $membershipLevels->count(),
                    'available_plans' => $membershipLevels->pluck('slug')->toArray(),
                ],
            ]);

            Log::channel('registration')->info('Registration page visited', [
                'flow_id' => $flowId,
                'membership_levels_count' => $membershipLevels->count(),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ]);
            
        } catch (\Exception $e) {
            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'membership_levels_loading',
                'action' => 'data_load_failed',
                'status' => 'failed',
                'message' => 'Failed to load membership levels',
                'error_message' => $e->getMessage(),
            ]);

            Log::channel('registration')->error('Failed to load membership levels on registration page', [
                'flow_id' => $flowId,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
        
        return Inertia::render('auth/RegisterWithPricing', [
            'membershipLevels' => $membershipLevels,
            'flowId' => $flowId, // Pass flow ID to frontend for tracking
        ]);
    }
    
    public function store(RegistrationWithSubscriptionRequest $request)
    {
        $flowId = $request->input('flow_id') ?? RegistrationAuditLog::generateFlowId();
        $requestData = $request->validated();

        // Log form submission
        RegistrationAuditLog::logStep([
            'flow_id' => $flowId,
            'step' => 'form_submission',
            'action' => 'form_submitted',
            'status' => 'success',
            'message' => 'Registration form submitted successfully',
            'request_data' => $requestData, // Will be sanitized by model
            'email' => $requestData['email'],
            'selected_plan' => $requestData['selected_price_id'],
            'metadata' => [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'session_id' => session()->getId(),
            ],
        ]);

        Log::channel('registration')->info('Registration form submitted', [
            'flow_id' => $flowId,
            'email' => $requestData['email'],
            'selected_plan' => $requestData['selected_price_id'],
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
        ]);

        try {
            // Log validation success
            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'form_validation',
                'action' => 'validation_passed',
                'status' => 'success',
                'message' => 'Form validation passed',
                'email' => $requestData['email'],
                'selected_plan' => $requestData['selected_price_id'],
            ]);

            // Store flow_id in session for tracking through service
            session(['registration_flow_id' => $flowId]);

            $result = $this->registrationService->registerWithSubscription($request->toDTO());
            
            // Log registration result
            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'registration_processing',
                'action' => 'registration_completed',
                'status' => 'success',
                'message' => 'Registration processing completed',
                'user_id' => $result['user']->id,
                'email' => $result['user']->email,
                'selected_plan' => $requestData['selected_price_id'],
                'response_data' => [
                    'checkout_type' => is_array($result['checkout']) ? ($result['checkout']['type'] ?? 'unknown') : 'redirect',
                    'user_created' => true,
                ],
            ]);

            if (is_array($result['checkout']) && $result['checkout']['type'] === 'free') {
                RegistrationAuditLog::logStep([
                    'flow_id' => $flowId,
                    'step' => 'free_plan_completion',
                    'action' => 'free_plan_registered',
                    'status' => 'success',
                    'message' => 'Free plan registration completed successfully',
                    'user_id' => $result['user']->id,
                    'email' => $result['user']->email,
                    'selected_plan' => $requestData['selected_price_id'],
                ]);

                Log::channel('registration')->info('Free plan registration completed', [
                    'flow_id' => $flowId,
                    'user_id' => $result['user']->id,
                    'email' => $result['user']->email,
                    'plan' => $requestData['selected_price_id'],
                ]);

                return redirect()->route('register.subscription.success');
            }

            // Log Stripe redirect
            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'stripe_redirect',
                'action' => 'redirecting_to_stripe',
                'status' => 'success',
                'message' => 'Redirecting user to Stripe for payment',
                'user_id' => $result['user']->id,
                'email' => $result['user']->email,
                'selected_plan' => $requestData['selected_price_id'],
            ]);

            Log::channel('registration')->info('Redirecting to Stripe checkout', [
                'flow_id' => $flowId,
                'user_id' => $result['user']->id,
                'email' => $result['user']->email,
                'plan' => $requestData['selected_price_id'],
            ]);
            
            return $result['checkout'];
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log validation errors
            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'form_validation',
                'action' => 'validation_failed',
                'status' => 'failed',
                'message' => 'Form validation failed',
                'email' => $requestData['email'] ?? null,
                'selected_plan' => $requestData['selected_price_id'] ?? null,
                'error_message' => 'Validation errors: ' . json_encode($e->errors()),
                'response_data' => [
                    'validation_errors' => $e->errors(),
                ],
            ]);

            Log::channel('registration')->warning('Registration validation failed', [
                'flow_id' => $flowId,
                'email' => $requestData['email'] ?? null,
                'errors' => $e->errors(),
            ]);

            throw $e;
            
        } catch (\Exception $e) {
            // Log general registration failure
            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'registration_processing',
                'action' => 'registration_failed',
                'status' => 'failed',
                'message' => 'Registration processing failed with exception',
                'email' => $requestData['email'] ?? null,
                'selected_plan' => $requestData['selected_price_id'] ?? null,
                'error_message' => $e->getMessage(),
                'metadata' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ]);

            Log::channel('registration')->error('Registration failed with exception', [
                'flow_id' => $flowId,
                'email' => $requestData['email'] ?? null,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
    
    public function success(Request $request)
    {
        $flowId = session('registration_flow_id') ?? 'unknown';
        $sessionId = $request->get('session_id');
        $user = auth()->user();
        
        // Log success page visit
        RegistrationAuditLog::logStep([
            'flow_id' => $flowId,
            'step' => 'success_page_visit',
            'action' => 'success_page_loaded',
            'status' => 'success',
            'message' => 'User visited registration success page',
            'user_id' => $user?->id,
            'email' => $user?->email,
            'stripe_session_id' => $sessionId,
            'metadata' => [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'session_id' => session()->getId(),
            ],
        ]);
        
        if ($sessionId) {
            try {
                $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);
                
                // Log Stripe session validation
                RegistrationAuditLog::logStep([
                    'flow_id' => $flowId,
                    'step' => 'stripe_session_validation',
                    'action' => 'session_retrieved',
                    'status' => 'success',
                    'message' => 'Successfully retrieved Stripe session',
                    'user_id' => $user?->id,
                    'email' => $user?->email,
                    'stripe_session_id' => $sessionId,
                    'response_data' => [
                        'payment_status' => $session->payment_status,
                        'customer' => $session->customer,
                        'subscription' => $session->subscription,
                    ],
                ]);
                
                if ($session->payment_status !== 'paid' && $session->payment_status !== 'no_payment_required') {
                    // Log payment pending
                    RegistrationAuditLog::logStep([
                        'flow_id' => $flowId,
                        'step' => 'payment_verification',
                        'action' => 'payment_pending',
                        'status' => 'pending',
                        'message' => 'Payment is still pending, redirecting to pending page',
                        'user_id' => $user?->id,
                        'email' => $user?->email,
                        'stripe_session_id' => $sessionId,
                        'response_data' => [
                            'payment_status' => $session->payment_status,
                        ],
                    ]);

                    Log::channel('registration')->warning('Payment pending on success page', [
                        'flow_id' => $flowId,
                        'user_id' => $user?->id,
                        'session_id' => $sessionId,
                        'payment_status' => $session->payment_status,
                    ]);
                    
                    return redirect()->route('register.subscription.pending');
                }

                // Log successful payment
                RegistrationAuditLog::logStep([
                    'flow_id' => $flowId,
                    'step' => 'payment_verification',
                    'action' => 'payment_confirmed',
                    'status' => 'success',
                    'message' => 'Payment confirmed successfully',
                    'user_id' => $user?->id,
                    'email' => $user?->email,
                    'stripe_session_id' => $sessionId,
                    'response_data' => [
                        'payment_status' => $session->payment_status,
                    ],
                ]);

                Log::channel('registration')->info('Payment confirmed on success page', [
                    'flow_id' => $flowId,
                    'user_id' => $user?->id,
                    'session_id' => $sessionId,
                    'payment_status' => $session->payment_status,
                ]);
                
            } catch (\Exception $e) {
                // Log Stripe session retrieval failure
                RegistrationAuditLog::logStep([
                    'flow_id' => $flowId,
                    'step' => 'stripe_session_validation',
                    'action' => 'session_retrieval_failed',
                    'status' => 'failed',
                    'message' => 'Failed to retrieve Stripe session',
                    'user_id' => $user?->id,
                    'email' => $user?->email,
                    'stripe_session_id' => $sessionId,
                    'error_message' => $e->getMessage(),
                ]);

                Log::channel('registration')->error('Failed to retrieve Stripe session on success page', [
                    'flow_id' => $flowId,
                    'user_id' => $user?->id,
                    'session_id' => $sessionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Log final success
        RegistrationAuditLog::logStep([
            'flow_id' => $flowId,
            'step' => 'registration_complete',
            'action' => 'registration_success',
            'status' => 'success',
            'message' => 'Registration flow completed successfully',
            'user_id' => $user?->id,
            'email' => $user?->email,
            'stripe_session_id' => $sessionId,
        ]);

        Log::channel('registration')->info('Registration completed successfully', [
            'flow_id' => $flowId,
            'user_id' => $user?->id,
            'email' => $user?->email,
            'session_id' => $sessionId,
        ]);
        
        return Inertia::render('auth/RegistrationSuccess', [
            'user' => $user->load('memberships.membershipLevel'),
        ]);
    }
    
    public function cancel(Request $request)
    {
        $flowId = session('registration_flow_id') ?? 'unknown';
        $user = auth()->user();

        // Log cancellation visit
        RegistrationAuditLog::logStep([
            'flow_id' => $flowId,
            'step' => 'registration_cancelled',
            'action' => 'cancellation_page_visited',
            'status' => 'cancelled',
            'message' => 'User visited registration cancellation page',
            'user_id' => $user?->id,
            'email' => $user?->email,
            'metadata' => [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'session_id' => session()->getId(),
            ],
        ]);

        Log::channel('registration')->warning('Registration cancelled', [
            'flow_id' => $flowId,
            'user_id' => $user?->id,
            'email' => $user?->email,
        ]);
        
        if (auth()->check() && !auth()->user()->hasVerifiedEmail()) {
            $userToDelete = auth()->user();
            
            // Log user cleanup
            RegistrationAuditLog::logStep([
                'flow_id' => $flowId,
                'step' => 'user_cleanup',
                'action' => 'unverified_user_deleted',
                'status' => 'success',
                'message' => 'Deleted unverified user after cancellation',
                'user_id' => $userToDelete->id,
                'email' => $userToDelete->email,
            ]);

            Log::channel('registration')->info('Deleted unverified user after registration cancellation', [
                'flow_id' => $flowId,
                'user_id' => $userToDelete->id,
                'email' => $userToDelete->email,
            ]);
            
            auth()->logout();
            $userToDelete->delete();
        }
        
        return Inertia::render('auth/RegistrationCancelled');
    }
    
    public function pending(Request $request)
    {
        $flowId = session('registration_flow_id') ?? 'unknown';
        $user = auth()->user();

        // Log pending page visit
        RegistrationAuditLog::logStep([
            'flow_id' => $flowId,
            'step' => 'registration_pending',
            'action' => 'pending_page_visited',
            'status' => 'pending',
            'message' => 'User visited registration pending page',
            'user_id' => $user?->id,
            'email' => $user?->email,
            'metadata' => [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'session_id' => session()->getId(),
            ],
        ]);

        Log::channel('registration')->info('Registration pending page visited', [
            'flow_id' => $flowId,
            'user_id' => $user?->id,
            'email' => $user?->email,
        ]);
        
        return Inertia::render('auth/RegistrationPending', [
            'user' => $user,
        ]);
    }
}