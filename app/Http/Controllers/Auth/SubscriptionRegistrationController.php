<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrationWithSubscriptionRequest;
use App\Services\RegistrationService;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Cashier\Cashier;

class SubscriptionRegistrationController extends Controller
{
    public function __construct(
        private RegistrationService $registrationService
    ) {}
    
    public function create()
    {
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
        
        return Inertia::render('auth/RegisterWithPricing', [
            'membershipLevels' => $membershipLevels,
        ]);
    }
    
    public function store(RegistrationWithSubscriptionRequest $request)
    {
        $result = $this->registrationService->registerWithSubscription(
            $request->toDTO()
        );
        
        if (is_array($result['checkout']) && $result['checkout']['type'] === 'free') {
            return redirect()->route('register.subscription.success');
        }
        
        return $result['checkout'];
    }
    
    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');
        
        if ($sessionId) {
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);
            
            if ($session->payment_status !== 'paid' && $session->payment_status !== 'no_payment_required') {
                return redirect()->route('register.subscription.pending');
            }
        }
        
        return Inertia::render('auth/RegistrationSuccess', [
            'user' => auth()->user()->load('memberships.membershipLevel'),
        ]);
    }
    
    public function cancel()
    {
        if (auth()->check() && !auth()->user()->hasVerifiedEmail()) {
            $user = auth()->user();
            auth()->logout();
            $user->delete();
        }
        
        return Inertia::render('auth/RegistrationCancelled');
    }
    
    public function pending()
    {
        return Inertia::render('auth/RegistrationPending', [
            'user' => auth()->user(),
        ]);
    }
}