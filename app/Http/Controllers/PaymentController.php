<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\Payment\CreateCheckoutSessionData;
use App\Enums\BookingStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Booking;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Contracts\Auth\Authenticatable; // Correct type for Auth::user()
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Laravel\Cashier\Checkout;
use Stripe\Exception\ApiErrorException;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Webhook;
use Stripe\Event;
use App\Modules\Membership\Actions\PurchaseMembershipAction;
use App\Modules\Membership\DataTransferObjects\MembershipPurchaseData;

class PaymentController extends Controller
{
    /**
     * Create a Stripe Checkout session and return the redirect URL.
     *
     * @param  \App\DataTransferObjects\Payment\CreateCheckoutSessionData  $data
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCheckoutSession(CreateCheckoutSessionData $data)
    {
        Log::info('[PaymentController] Attempting to create checkout session.', ['user_id' => Auth::id(), 'data_items_count' => count($data->items)]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            Log::warning('[PaymentController] User not authenticated for checkout session creation.');
            return response()->json(['error' => 'User not authenticated.'], 401);
        }

        $lineItems = [];
        foreach ($data->items as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => strtolower($item->currency),
                    'unit_amount' => $item->amount, // Amount in cents
                    'product_data' => [
                        'name' => $item->name,
                        'description' => $item->description,
                    ],
                ],
                'quantity' => $item->quantity,
            ];
        }

        $metadata = [];

        try {
            $checkoutSession = $user->checkout($lineItems, [
                // 'success_url' => $successUrl,
                // 'cancel_url' => $cancelUrl,
                'metadata' => $metadata,
                'customer_email' => $user->email,
            ]);

            Log::info('[PaymentController] Stripe checkout session created successfully.', [
                'user_id' => $user->id,
                'checkout_session_id' => $checkoutSession->id,
                'checkout_url' => $checkoutSession->url
            ]);
            return response()->json(['checkout_url' => $checkoutSession->url]);
        } catch (ApiErrorException $e) {
            Log::error('[PaymentController] Stripe API Error during checkout session creation: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
            return response()->json(['error' => 'Could not initiate payment. Please try again.'], 500);
        } catch (\Exception $e) {
            Log::error('[PaymentController] General error creating checkout session: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'data_items_count' => count($data->items)
            ]);
            return response()->json(['error' => 'Could not initiate payment. Please try again.'], 500);
        }
    }

    /**
     * Handle successful payment redirect from Stripe.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
     */
    public function handlePaymentSuccess(Request $request): \Inertia\Response|RedirectResponse
    {
        Log::info('[PaymentController] Handling payment success redirect.', [
            'user_id' => Auth::id(),
            'query_params' => $request->query()
        ]);

        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        $validationResult = $this->validateInitialPaymentRequest($request, $authUser);
        if ($validationResult instanceof RedirectResponse) {
            return $validationResult;
        }

        /** @var Transaction $transaction */
        $transaction = $validationResult['transaction'];
        /** @var string $dbStripeSessionId */ // Renamed for clarity, this is from transaction->payment_gateway_transaction_id
        $dbStripeSessionId = $validationResult['stripe_session_id'];
        /** @var User $user */
        $user = $validationResult['user'];

        Log::debug('[PaymentController] Retrieved Stripe session ID from DB for success handling.', ['db_stripe_session_id' => $dbStripeSessionId, 'transaction_id' => $transaction->id]);

        try {
            $checkoutSession = $this->retrieveStripeSession($user, $dbStripeSessionId);
            if (!$checkoutSession) {
                Log::error('[PaymentController] Failed to retrieve Stripe session on success page.', ['stripe_session_id' => $dbStripeSessionId, 'transaction_id' => $transaction->id]);
                return $this->redirectToHomeWithFlashHelper(
                    'error',
                    'There was an issue confirming your payment. Please contact support.',
                    $dbStripeSessionId // Use the ID from DB
                );
            }

            Log::info('[PaymentController] Stripe checkout session retrieved successfully on success page.', ['stripe_session' => $checkoutSession]);

            Log::info('[PaymentController] Stripe checkout session retrieved successfully on success page.', ['stripe_session_id' => $checkoutSession->id, 'payment_status' => $checkoutSession->payment_status, 'transaction_id' => $transaction->id]);

            // Log if transaction_id from URL (which is $transaction->id here)
            // mismatches transaction_id in Stripe session metadata, if present.
            $metadataOrderId = $checkoutSession->metadata['transaction_id'] ?? null;
            if (is_string($metadataOrderId) && $metadataOrderId !== strval($transaction->id)) {
                Log::warning('Transaction ID from DB mismatches order_id in Stripe session metadata on success page.', [
                    'db_transaction_id' => $transaction->id,
                    'metadata_order_id' => $metadataOrderId,
                    'stripe_session_id' => $dbStripeSessionId
                ]);
                // Decide if this is a critical error. For now, proceed with $transaction from DB.
            }

            $bookings = Booking::where('transaction_id', $transaction->id)
                ->get();

            $this->proactivelyUpdateTransactionAndBookingStatuses($transaction, $checkoutSession);

            if ($transaction->status === TransactionStatusEnum::CONFIRMED && $bookings->isNotEmpty()) {
                Log::info('[PaymentController] Transaction confirmed, rendering success page.', ['transaction_id' => $transaction->id, 'stripe_session_id' => $dbStripeSessionId]);
                return $this->renderPaymentSuccessPage($transaction, $bookings, $dbStripeSessionId);
            }

            Log::info('[PaymentController] Transaction status and payment status.', ['transaction_status' => $transaction->status, 'checkout_session_payment_status' => $checkoutSession->payment_status, 'transaction_status_is_same' => $transaction->status === TransactionStatusEnum::PENDING_PAYMENT, 'checkout_session_payment_status_is_paid' => $checkoutSession->payment_status === 'paid']);
            if ($transaction->status === TransactionStatusEnum::PENDING_PAYMENT && $checkoutSession->payment_status === 'paid') {
                Log::info("[PaymentController] Transaction PENDING_PAYMENT but Stripe session 'paid' on success page. Webhook might be delayed. Rendering success page with pending flag.", [
                    'transaction_id' => $transaction->id,
                    'stripe_session_id' => $dbStripeSessionId
                ]);

                return $this->renderPaymentSuccessPage($transaction, $bookings, $dbStripeSessionId, true);
            }

            Log::warning('[PaymentController] Transaction not confirmed or bookings empty on success page visit after checks. Redirecting home.', [
                'transaction_id' => $transaction->id,
                'transaction_status' => $transaction->status,
                'booking_count' => $bookings->count(),
                'stripe_session_id' => $dbStripeSessionId,
                'stripe_payment_status' => $checkoutSession->payment_status
            ]);

            return $this->redirectToHomeWithFlashHelper(
                'success',
                'Payment processed! Your booking details will be updated shortly.',
                $dbStripeSessionId,
                'warning',
                "Transaction not confirmed or bookings empty on success page visit after checks.",
                [
                    'transaction_id' => $transaction->id,
                    'transaction_status' => $transaction->status,
                    'booking_count' => $bookings->count(),
                    'stripe_session_id' => $dbStripeSessionId
                ]
            );
        } catch (\Exception $e) {
            Log::error("Unexpected error handling payment success for session {$dbStripeSessionId}: " . $e->getMessage(), ['exception' => $e, 'transaction_id' => $transaction->id]);
            return $this->redirectToHomeWithFlashHelper(
                'error',
                'An unexpected error occurred while confirming your payment. Please contact support.',
                $dbStripeSessionId
            );
        }
    }

    /**
     * Validates the initial payment success request: authenticates user, checks for transaction_id,
     * retrieves the transaction, verifies user ownership, and extracts the Stripe session ID.
     *
     * @param Request $request
     * @param Authenticatable|null $authUser
     * @return array|RedirectResponse Returns an array ['transaction' => Transaction, 'stripe_session_id' => string] or a RedirectResponse on failure.
     */
    private function validateInitialPaymentRequest(Request $request, ?Authenticatable $authUser): array|RedirectResponse
    {
        Log::debug('[PaymentController] Validating initial payment success request.', ['query_params' => $request->query(), 'auth_user_id' => $authUser?->getAuthIdentifier()]);

        $transactionIdParam = $request->query('transaction_id');
        $transactionId = is_string($transactionIdParam) ? $transactionIdParam : null;

        if (!$transactionId) {
            Log::warning('Payment success accessed without transaction_id.');
            return $this->redirectToHomeWithFlashHelper(
                'error',
                'Invalid payment success link. Transaction identifier missing.'
            );
        }

        if (!$authUser) {
            Log::warning('Payment success accessed by unauthenticated user.', ['transaction_id' => $transactionId]);
            return Redirect::route('login')->with('flash', [
                'type' => 'error',
                'message' => 'Please login to view your booking details.',
                'attempted_transaction_id' => $transactionId
            ]);
        }
        /** @var User $user */
        $user = $authUser;

        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            Log::error('Transaction not found from transaction_id in URL.', ['transaction_id' => $transactionId, 'user_id' => $user->id]);
            return $this->redirectToHomeWithFlashHelper(
                'error',
                'Could not find your booking details. Please contact support.',
                null, // No stripe session ID known yet
                'error',
                'Transaction not found via URL transaction_id on success page.',
                ['transaction_id' => $transactionId, 'user_id' => $user->id]
            );
        }

        if ($transaction->user_id !== $user->id) {
            Log::error('User mismatch for transaction on success page.', [
                'transaction_id' => $transactionId,
                'transaction_user_id' => $transaction->user_id,
                'auth_user_id' => $user->id
            ]);
            return $this->redirectToHomeWithFlashHelper(
                'error',
                'You are not authorized to view this booking.',
                null, // No stripe session ID known yet
                'error',
                'User ID mismatch for transaction on success page.',
                ['transaction_id' => $transactionId, 'transaction_user_id' => $transaction->user_id, 'auth_user_id' => $user->id]
            );
        }

        // Use payment_gateway_transaction_id to retrieve the Stripe Session ID (or other gateway's ID)
        $stripeSessionId = $transaction->payment_gateway_transaction_id;

        if (!is_string($stripeSessionId) || empty($stripeSessionId)) {
            Log::error('Payment gateway transaction ID (e.g., Stripe session ID) not found or empty on transaction.', [
                'transaction_id' => $transaction->id,
                'user_id' => $user->id
            ]);
            return $this->redirectToHomeWithFlashHelper(
                'error',
                'Payment session details are missing for your booking. Please contact support.',
                null,
                'error',
                'payment_gateway_transaction_id is missing/empty on transaction on success page.',
                ['transaction_id' => $transaction->id, 'user_id' => $user->id]
            );
        }

        return ['transaction' => $transaction, 'stripe_session_id' => $stripeSessionId, 'user' => $user];
    }

    private function retrieveStripeSession(User $user, string $stripeSessionId): ?StripeCheckoutSession
    {
        try {
            Log::debug('[PaymentController] Attempting to retrieve Stripe session.', ['user_id' => $user->id, 'stripe_session_id' => $stripeSessionId]);
            $session = $user->stripe()->checkout->sessions->retrieve($stripeSessionId);
            Log::debug('[PaymentController] Stripe session retrieved successfully from Stripe API.', ['stripe_session_id' => $stripeSessionId, 'payment_status' => $session->payment_status]);
            return $session;
        } catch (ApiErrorException $e) {
            Log::error("[PaymentController] Stripe API Error retrieving session {$stripeSessionId} on success: " . $e->getMessage(), [
                'user_id' => $user->id,
                'stripe_session_id' => $stripeSessionId,
                'stripe_error_code' => $e->getStripeCode(),
            ]);
            return null;
        }
    }

    private function proactivelyUpdateTransactionAndBookingStatuses(Transaction $transaction, StripeCheckoutSession $checkoutSession): void
    {
        if ($transaction->status === TransactionStatusEnum::PENDING_PAYMENT && $checkoutSession->payment_status === 'paid') {
            Log::info("[PaymentController] Proactively updating transaction and bookings to CONFIRMED based on paid Stripe session.", [
                'transaction_id' => $transaction->id,
                'stripe_session_id' => $checkoutSession->id,
                'current_transaction_status' => $transaction->status
            ]);
            $transaction->status = TransactionStatusEnum::CONFIRMED;
            $paymentIntent = $checkoutSession->payment_intent;
            if (is_string($paymentIntent)) {
                $transaction->payment_intent_id = $paymentIntent;
            }
            // $transaction->payment_gateway_transaction_id is already set to $checkoutSession->id by the webhook or initial creation.
            $transaction->save();

            Booking::where('transaction_id', $transaction->id)
                ->where('status', BookingStatusEnum::PENDING_CONFIRMATION)
                ->update(['status' => BookingStatusEnum::CONFIRMED]);

            Log::info("[PaymentController] Transaction and bookings proactively updated.", ['transaction_id' => $transaction->id, 'new_transaction_status' => $transaction->status]);
        }
    }

    private function renderPaymentSuccessPage(Transaction $transaction, EloquentCollection $bookings, string $stripeSessionId, bool $pendingConfirmation = false): \Inertia\Response
    {
        Log::debug('[PaymentController] Rendering payment success page.', [
            'transaction_id' => $transaction->id,
            'booking_count' => $bookings->count(),
            'stripe_session_id' => $stripeSessionId,
            'pending_confirmation' => $pendingConfirmation
        ]);
        return Inertia::render('Payment/Success', [
            'transaction' => $transaction,
            'bookings' => $bookings,
            'session_id' => $stripeSessionId,
            'pending_confirmation' => $pendingConfirmation,
        ]);
    }

    private function redirectToHomeWithFlashHelper(string $type, string $message, ?string $stripeSessionId = null, ?string $logLevel = null, ?string $logMessage = null, ?array $logContext = null): RedirectResponse
    {
        if ($logLevel && $logMessage) {
            $context = $logContext ?? [];
            if ($stripeSessionId !== null) { // Ensure stripeSessionId is not null before adding to context
                $context['stripe_session_id_for_flash_redirect'] = $stripeSessionId;
            }
            Log::{$logLevel}($logMessage, $context);
        }

        $flashData = ['type' => $type, 'message' => $message];
        return Redirect::route('home')->with('flash', $flashData);
    }

    /**
     * Handle cancelled payment redirect from Stripe.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handlePaymentCancel(Request $request)
    {
        $sessionIdQueryParam = $request->query('session_id');
        $stripeSessionId = is_string($sessionIdQueryParam) ? $sessionIdQueryParam : null;

        $transactionIdQueryParam = $request->query('transaction_id');
        $transactionId = is_string($transactionIdQueryParam) ? $transactionIdQueryParam : null;

        Log::info("[PaymentController] Payment cancelled by user.", [
            'stripe_session_id' => $stripeSessionId,
            'transaction_id' => $transactionId,
            'user_id' => Auth::id()
        ]);

        if ($transactionId) {
            $transaction = Transaction::find($transactionId);
            if ($transaction && $transaction->status === TransactionStatusEnum::PENDING_PAYMENT) {
                $transaction->update(['status' => TransactionStatusEnum::CANCELLED]);
                Booking::where('transaction_id', $transactionId)
                    ->where('status', BookingStatusEnum::PENDING_CONFIRMATION)
                    ->update(['status' => BookingStatusEnum::CANCELLED]);
                Log::info("[PaymentController] Transaction and bookings marked CANCELLED due to payment cancellation.", ['transaction_id' => $transactionId]);
            }
        }

        Log::info('[PaymentController] Redirecting to home after payment cancellation.', ['stripe_session_id' => $stripeSessionId, 'transaction_id' => $transactionId]);
        return $this->redirectToHomeWithFlashHelper(
            'info',
            'Your payment was cancelled. You have not been charged. Your booking has been cancelled.',
            $stripeSessionId // This is fine as redirectToHomeWithFlashHelper handles ?string
        );
    }

    /**
     * Handle Stripe webhook events.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        Log::info('[PaymentController] Stripe webhook received.', ['stripe_signature_present' => !empty($sigHeader)]);
        Log::debug('[PaymentController] Webhook payload size.', ['size' => strlen($payload)]);

        if (!$endpointSecret) {
            Log::error('[PaymentController] Stripe webhook secret not configured.');
            return response('Webhook secret not configured', 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            Log::info('[PaymentController] Stripe webhook event constructed successfully.', ['event_id' => $event->id, 'event_type' => $event->type]);
        } catch (\UnexpectedValueException $e) {
            Log::error('[PaymentController] Invalid payload in Stripe webhook: ' . $e->getMessage());
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('[PaymentController] Invalid signature in Stripe webhook: ' . $e->getMessage());
            return response('Invalid signature', 400);
        }

        Log::info('[PaymentController] Processing Stripe webhook event.', ['type' => $event->type, 'id' => $event->id]);

        // Handle the event
        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event);
                    break;
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event);
                    break;
                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event);
                    break;
                // Add other event types your application needs to handle
                // case 'invoice.payment_succeeded':
                //    $this->handleInvoicePaymentSucceeded($event['data']['object']);
                //    break;
                // case 'invoice.payment_failed':
                //    $this->handleInvoicePaymentFailed($event['data']['object']);
                //    break;
                default:
                    Log::info('[PaymentController] Unhandled Stripe webhook event type: ' . $event->type, ['event_id' => $event->id]);
            }
        } catch (\Exception $e) {
            Log::error('[PaymentController] Error processing Stripe webhook: ' . $e->getMessage(), [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'exception' => $e
            ]);
            return response('Webhook processing failed', 500); // Important to return 500 so Stripe retries if appropriate
        }

        return response('Webhook handled', 200);
    }

    /**
     * Handle checkout session completed event.
     *
     * @param  \Stripe\Event\CheckoutSessionCompletedEvent  $event
     * @return void
     */
    protected function handleCheckoutSessionCompleted(Event $event): void
    {
        $session = $event->data->object;
        Log::info('[PaymentController] Handling checkout.session.completed event.', ['session_id' => $session->id, 'payment_intent' => $session->payment_intent, 'event_id' => $event->id]);

        // The transaction_id was passed in metadata when creating the checkout session
        $transactionId = $session->metadata->transaction_id ?? null; // transaction_id in metadata is our transaction_id

        if (!$transactionId) {
            Log::error('[PaymentController] Transaction ID not found in Stripe checkout session metadata for checkout.session.completed.', ['session_id' => $session->id, 'event_id' => $event->id]);
            return;
        }
        Log::debug('[PaymentController] Transaction ID from metadata.', ['transaction_id' => $transactionId, 'session_id' => $session->id]);

        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            Log::error('[PaymentController] Transaction not found for Stripe checkout session during checkout.session.completed.', ['transaction_id' => $transactionId, 'session_id' => $session->id, 'event_id' => $event->id]);
            return;
        }
        Log::debug('[PaymentController] Transaction found for checkout.session.completed.', ['transaction_id' => $transaction->id, 'current_status' => $transaction->status, 'session_id' => $session->id]);

        // Prevent processing already completed transactions again from webhook
        if ($transaction->status === TransactionStatusEnum::CONFIRMED) {
            Log::info('[PaymentController] Transaction already confirmed. Skipping update from checkout.session.completed.', ['transaction_id' => $transactionId, 'session_id' => $session->id, 'event_id' => $event->id]);
            return;
        }

        $transaction->status = TransactionStatusEnum::CONFIRMED;
        $transaction->payment_gateway = 'stripe';
        $transaction->payment_gateway_transaction_id = $session->id; // Store Stripe Session ID here
        $transaction->payment_intent_id = $session->payment_intent;
        $transaction->save();

        Log::info('[PaymentController] Transaction updated to CONFIRMED via checkout.session.completed.', ['transaction_id' => $transaction->id, 'stripe_session_id' => $session->id, 'event_id' => $event->id]);

        Booking::where('transaction_id', $transactionId)
            ->where('status', BookingStatusEnum::PENDING_CONFIRMATION)
            ->update(['status' => BookingStatusEnum::CONFIRMED]);
    }

    /**
     * Handle payment intent succeeded event.
     *
     * @param  \Stripe\Event\PaymentIntentSucceededEvent  $event
     * @return void
     */
    protected function handlePaymentIntentSucceeded(Event $event): void
    {
        $paymentIntent = $event->data->object;
        Log::info('[PaymentController] Handling payment_intent.succeeded event.', ['payment_intent_id' => $paymentIntent->id, 'event_id' => $event->id]);

        // Attempt to find the transaction using the Payment Intent ID
        // This is a fallback, as checkout.session.completed should be the primary confirmation path
        $transaction = Transaction::where('payment_intent_id', $paymentIntent->id)
            ->orWhere('payment_gateway_payment_intent_id', $paymentIntent->id) // Fallback for old data if any
            ->first();

        if ($transaction) {
            Log::debug('[PaymentController] Transaction found for payment_intent.succeeded.', ['transaction_id' => $transaction->id, 'current_status' => $transaction->status, 'payment_intent_id' => $paymentIntent->id]);
            if ($transaction->status !== TransactionStatusEnum::CONFIRMED) {
                $transaction->status = TransactionStatusEnum::CONFIRMED;
                // Ensure payment_intent_id is set if it was matched by the fallback
                if (empty($transaction->payment_intent_id)) {
                    $transaction->payment_intent_id = $paymentIntent->id;
                }
                // We might not have the checkout session ID here reliably from $paymentIntent object itself.
                // $session->id (Checkout Session ID) is best obtained from checkout.session.completed event.
                // If stripe_session_id is empty, and we could get it from $paymentIntent->latest_charge, that could be an option.
                // For now, we focus on confirming status and payment_intent_id.
                $transaction->save();
                Log::info('[PaymentController] Transaction confirmed via payment_intent.succeeded.', ['transaction_id' => $transaction->id, 'payment_intent_id' => $paymentIntent->id, 'event_id' => $event->id]);
            } else {
                Log::info('[PaymentController] Transaction already confirmed. payment_intent.succeeded for already confirmed transaction.', ['transaction_id' => $transaction->id, 'payment_intent_id' => $paymentIntent->id, 'event_id' => $event->id]);
            }
        } else {
            Log::warning('[PaymentController] Transaction not found for payment_intent.succeeded. This might be okay if checkout.session.completed handled it.', ['payment_intent_id' => $paymentIntent->id, 'event_id' => $event->id]);
        }
    }

    /**
     * Handle payment intent failed event.
     *
     * @param  \Stripe\Event\PaymentIntentFailedEvent  $event
     * @return void
     */
    protected function handlePaymentIntentFailed(Event $event): void
    {
        $paymentIntent = $event->data->object;
        Log::info('[PaymentController] Handling payment_intent.payment_failed event.', ['payment_intent_id' => $paymentIntent->id, 'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Unknown', 'event_id' => $event->id]);

        $transaction = Transaction::where('payment_intent_id', $paymentIntent->id)
            ->orWhere('payment_gateway_payment_intent_id', $paymentIntent->id) // Fallback for old data
            ->first();

        if ($transaction) {
            Log::debug('[PaymentController] Transaction found for payment_intent.payment_failed.', ['transaction_id' => $transaction->id, 'current_status' => $transaction->status, 'payment_intent_id' => $paymentIntent->id]);
            // Only update if the transaction is not already in a final state (e.g. confirmed from a retry or another means)
            if ($transaction->status === TransactionStatusEnum::PENDING_PAYMENT || $transaction->status === null) {
                $transaction->status = TransactionStatusEnum::FAILED_PAYMENT;
                $transaction->save();

                // Update associated bookings to CANCELLED
                $transaction->bookings()->update(['status' => BookingStatusEnum::CANCELLED]);

                Log::info('[PaymentController] Transaction and bookings updated to FAILED_PAYMENT/CANCELLED due to payment_intent.payment_failed.', ['transaction_id' => $transaction->id, 'payment_intent_id' => $paymentIntent->id, 'event_id' => $event->id]);
            } else {
                Log::info('[PaymentController] Payment intent failed for a transaction not in pending state.', ['transaction_id' => $transaction->id, 'current_status' => $transaction->status, 'payment_intent_id' => $paymentIntent->id, 'event_id' => $event->id]);
            }
        } else {
            Log::warning('[PaymentController] Transaction not found for payment_intent.payment_failed.', ['payment_intent_id' => $paymentIntent->id, 'event_id' => $event->id]);
        }
    }

    /**
     * Handle the logic for a successful membership purchase.
     *
     * @param \App\Models\Transaction $transaction
     * @return void
     */
    protected function handleMembershipPurchase(Transaction $transaction): void
    {
        $user = $transaction->user;
        $membershipLevelId = $transaction->metadata['membership_level_id'];

        $purchaseData = MembershipPurchaseData::from([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevelId,
            'payment_method' => 'stripe',
            'auto_renew' => false, // or from metadata if you add it
        ]);

        app(PurchaseMembershipAction::class)->execute($user, $purchaseData);

        Log::info('Membership purchase processed via webhook.', ['transaction_id' => $transaction->id, 'user_id' => $user->id]);
    }

    /**
     * Handle the logic for a successful booking purchase.
     *
     * @param \App\Models\Transaction $transaction
     * @return void
     */
    protected function handleBookingPurchase(Transaction $transaction): void
    {
        Booking::where('transaction_id', $transaction->id)->update(['status' => BookingStatusEnum::CONFIRMED]);
        Log::info('Booking statuses updated to confirmed.', ['transaction_id' => $transaction->id]);
    }
}
