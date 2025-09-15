<?php

namespace App\Http\Controllers;

use App\Actions\Organizer\AcceptInvitationAction;
use App\Models\Organizer;
use App\Models\User;
use App\Services\InvitationTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function __construct(
        private InvitationTokenService $tokenService,
        private AcceptInvitationAction $acceptInvitationAction
    ) {}

    /**
     * Handle invitation acceptance via signed URL.
     */
    public function accept(Request $request)
    {
        try {
            // Validate the signed URL (middleware handles this)
            $tokenData = $this->tokenService->validateInvitationToken($request->all());
            
            Log::info('Processing invitation acceptance', $tokenData);

            // Find the organizer and user
            $organizer = Organizer::findOrFail($tokenData['organizer_id']);
            $user = User::where('email', $tokenData['email'])->first();

            if (!$user) {
                // This is a new user invitation - redirect to registration
                return $this->handleNewUserInvitation($tokenData, $organizer);
            }

            // This is an existing user - check if they're logged in
            if (!Auth::check()) {
                // User exists but not logged in - redirect to login with return URL
                return $this->handleExistingUserLogin($tokenData, $organizer, $user);
            }

            // User is logged in - verify they're the right user
            if (Auth::id() !== $user->id) {
                Auth::logout();
                return $this->handleExistingUserLogin($tokenData, $organizer, $user);
            }

            // Accept the invitation
            return $this->processInvitationAcceptance($tokenData, $organizer, $user);

        } catch (\Exception $e) {
            Log::error('Invitation acceptance failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('home')->with('error', 'Invalid or expired invitation link.');
        }
    }

    /**
     * Handle new user invitation - redirect to registration.
     */
    private function handleNewUserInvitation(array $tokenData, Organizer $organizer)
    {
        $organizerName = is_array($organizer->name) 
            ? ($organizer->name['en'] ?? $organizer->name[array_key_first($organizer->name)] ?? 'Unknown')
            : $organizer->name;

        return Inertia::render('auth/RegisterFromInvitation', [
            'invitation' => [
                'organizer_name' => $organizerName,
                'role' => $tokenData['role'],
                'email' => $tokenData['email'],
            ],
            'token_data' => base64_encode(json_encode($tokenData)),
        ]);
    }

    /**
     * Handle existing user login.
     */
    private function handleExistingUserLogin(array $tokenData, Organizer $organizer, User $user)
    {
        $organizerName = is_array($organizer->name) 
            ? ($organizer->name['en'] ?? $organizer->name[array_key_first($organizer->name)] ?? 'Unknown')
            : $organizer->name;

        // Store invitation data in session for after login
        session(['pending_invitation' => $tokenData]);

        return Inertia::render('auth/LoginFromInvitation', [
            'invitation' => [
                'organizer_name' => $organizerName,
                'role' => $tokenData['role'],
                'email' => $tokenData['email'],
                'user_name' => $user->name,
            ],
            'return_url' => route('invitation.accept', $tokenData),
        ]);
    }

    /**
     * Process the actual invitation acceptance.
     */
    private function processInvitationAcceptance(array $tokenData, Organizer $organizer, User $user)
    {
        try {
            // Accept the invitation using the existing action
            $result = $this->acceptInvitationAction->execute(
                organizer: $organizer,
                user: $user
            );

            if ($result) {
                $organizerName = is_array($organizer->name) 
                    ? ($organizer->name['en'] ?? $organizer->name[array_key_first($organizer->name)] ?? 'Unknown')
                    : $organizer->name;

                return redirect()->route('dashboard')->with('success', 
                    "Welcome! You have successfully joined {$organizerName} as a {$tokenData['role']}."
                );
            }

            throw new \Exception('Failed to accept invitation');

        } catch (\Exception $e) {
            Log::error('Failed to accept invitation', [
                'user_id' => $user->id,
                'organizer_id' => $organizer->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('home')->with('error', 
                'Unable to accept invitation. The invitation may have expired or already been processed.'
            );
        }
    }

    /**
     * Complete registration and accept invitation.
     */
    public function completeRegistration(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
            'token_data' => 'required|string',
        ]);

        try {
            $tokenData = json_decode(base64_decode($request->token_data), true);
            
            if (!$tokenData) {
                throw new \Exception('Invalid token data');
            }

            // Find or create the user
            $user = User::where('email', $tokenData['email'])->first();
            
            if (!$user) {
                // Create new user
                $user = User::create([
                    'name' => $request->name,
                    'email' => $tokenData['email'],
                    'password' => Hash::make($request->password),
                    'email_verified_at' => now(), // Auto-verify via invitation
                ]);
            } else {
                // Update existing user with proper credentials
                $user->update([
                    'name' => $request->name,
                    'password' => Hash::make($request->password),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            }

            // Log the user in
            Auth::login($user);

            // Find organizer and accept invitation
            $organizer = Organizer::findOrFail($tokenData['organizer_id']);
            
            return $this->processInvitationAcceptance($tokenData, $organizer, $user);

        } catch (\Exception $e) {
            Log::error('Registration from invitation failed', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown'
            ]);

            return back()->withErrors(['error' => 'Registration failed. Please try again.']);
        }
    }
}