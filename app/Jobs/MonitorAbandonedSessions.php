<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Enums\TransactionStatusEnum;
use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Exception\ApiErrorException;
use Carbon\Carbon;

class MonitorAbandonedSessions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $stats = [
        'transactions_checked' => 0,
        'sessions_completed' => 0,
        'sessions_expired' => 0,
        'sessions_open' => 0,
        'sessions_errored' => 0,
        'bookings_confirmed' => 0,
        'transactions_updated' => 0,
    ];

    public int $timeout = 300; // 5 minutes timeout
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // Initialize Stripe API key
        if (config('services.stripe.secret')) {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('[MonitorAbandonedSessions] Starting abandoned session monitoring job');

        try {
            $this->monitorPendingTransactions();
            $this->cleanupExpiredSessions();
            $this->logResults();
        } catch (\Exception $e) {
            Log::error('[MonitorAbandonedSessions] Job failed: ' . $e->getMessage(), [
                'exception' => $e,
                'stats' => $this->stats
            ]);
            throw $e;
        }
    }

    /**
     * Monitor pending transactions for checkout session status updates.
     */
    private function monitorPendingTransactions(): void
    {
        // Find pending transactions older than 30 minutes with Stripe checkout sessions
        $cutoffTime = now()->subMinutes(30);
        
        $pendingTransactions = Transaction::where('status', TransactionStatusEnum::PENDING)
            ->where('created_at', '<', $cutoffTime)
            ->whereNotNull('stripe_checkout_session_id')
            ->with(['bookings']) // Eager load bookings for efficiency
            ->get();

        Log::info('[MonitorAbandonedSessions] Found pending transactions to check', [
            'count' => $pendingTransactions->count(),
            'cutoff_time' => $cutoffTime->toDateTimeString()
        ]);

        foreach ($pendingTransactions as $transaction) {
            $this->processTransaction($transaction);
        }
    }

    /**
     * Process a single abandoned transaction.
     */
    private function processTransaction(Transaction $transaction): void
    {
        $this->stats['transactions_checked']++;

        Log::info('[MonitorAbandonedSessions] Checking transaction', [
            'transaction_id' => $transaction->id,
            'stripe_session_id' => $transaction->stripe_checkout_session_id,
            'age_minutes' => $transaction->created_at->diffInMinutes(now())
        ]);

        try {
            // Retrieve current session status from Stripe
            $session = StripeSession::retrieve($transaction->stripe_checkout_session_id);
            
            $this->handleSessionStatus($transaction, $session);

        } catch (ApiErrorException $e) {
            $this->stats['sessions_errored']++;
            
            Log::error('[MonitorAbandonedSessions] Stripe API error for transaction', [
                'transaction_id' => $transaction->id,
                'stripe_session_id' => $transaction->stripe_checkout_session_id,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ]);

            // If session not found (deleted/expired), mark as expired
            if ($e->getStripeCode() === 'resource_missing') {
                $this->markTransactionAsExpired($transaction, 'Session not found in Stripe');
            }
        } catch (\Exception $e) {
            $this->stats['sessions_errored']++;
            
            Log::error('[MonitorAbandonedSessions] Unexpected error processing transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle different Stripe session statuses.
     */
    private function handleSessionStatus(Transaction $transaction, object $session): void
    {
        Log::info('[MonitorAbandonedSessions] Session status retrieved', [
            'transaction_id' => $transaction->id,
            'session_status' => $session->status,
            'payment_status' => $session->payment_status ?? 'unknown'
        ]);

        switch ($session->status) {
            case 'complete':
                $this->handleCompletedSession($transaction, $session);
                break;

            case 'expired':
                $this->handleExpiredSession($transaction, $session);
                break;

            case 'open':
                $this->handleOpenSession($transaction, $session);
                break;

            default:
                Log::warning('[MonitorAbandonedSessions] Unknown session status', [
                    'transaction_id' => $transaction->id,
                    'session_status' => $session->status
                ]);
        }
    }

    /**
     * Handle completed sessions that weren't processed by webhooks.
     */
    private function handleCompletedSession(Transaction $transaction, object $session): void
    {
        $this->stats['sessions_completed']++;

        Log::info('[MonitorAbandonedSessions] Found completed session, processing payment', [
            'transaction_id' => $transaction->id,
            'session_id' => $session->id,
            'payment_intent' => $session->payment_intent ?? 'none'
        ]);

        DB::transaction(function () use ($transaction, $session) {
            // Update transaction status
            $transaction->update([
                'status' => TransactionStatusEnum::COMPLETED,
                'stripe_payment_intent_id' => $session->payment_intent,
                'completed_at' => now(),
            ]);

            // Confirm associated bookings
            $bookingsUpdated = Booking::where('transaction_id', $transaction->id)
                ->where('status', BookingStatusEnum::PENDING)
                ->update([
                    'status' => BookingStatusEnum::CONFIRMED,
                    'confirmed_at' => now(),
                ]);

            $this->stats['transactions_updated']++;
            $this->stats['bookings_confirmed'] += $bookingsUpdated;

            Log::info('[MonitorAbandonedSessions] Successfully recovered completed payment', [
                'transaction_id' => $transaction->id,
                'bookings_confirmed' => $bookingsUpdated,
                'recovery_method' => 'abandoned_session_monitoring'
            ]);

            // Create a webhook event record for audit trail
            $this->createRecoveryWebhookEvent($transaction, $session, 'recovered_completed_session');
        });
    }

    /**
     * Handle expired sessions.
     */
    private function handleExpiredSession(Transaction $transaction, object $session): void
    {
        $this->stats['sessions_expired']++;
        $this->markTransactionAsExpired($transaction, 'Stripe session expired');
    }

    /**
     * Handle still-open sessions.
     */
    private function handleOpenSession(Transaction $transaction, object $session): void
    {
        $this->stats['sessions_open']++;

        // Check if session is very old (>24 hours) and should be considered abandoned
        $sessionAge = now()->diffInHours(Carbon::createFromTimestamp($session->created));
        
        if ($sessionAge > 24) {
            Log::info('[MonitorAbandonedSessions] Open session is very old, marking as expired', [
                'transaction_id' => $transaction->id,
                'session_age_hours' => $sessionAge
            ]);
            
            $this->markTransactionAsExpired($transaction, "Session open for {$sessionAge} hours");
        } else {
            Log::info('[MonitorAbandonedSessions] Session still open and recent', [
                'transaction_id' => $transaction->id,
                'session_age_hours' => $sessionAge
            ]);
        }
    }

    /**
     * Mark transaction as expired and cleanup.
     */
    private function markTransactionAsExpired(Transaction $transaction, string $reason): void
    {
        Log::info('[MonitorAbandonedSessions] Marking transaction as expired', [
            'transaction_id' => $transaction->id,
            'reason' => $reason
        ]);

        DB::transaction(function () use ($transaction, $reason) {
            // Update transaction status
            $transaction->update([
                'status' => TransactionStatusEnum::CANCELLED,
                'cancelled_at' => now(),
            ]);

            // Cancel associated bookings
            $bookingsCancelled = Booking::where('transaction_id', $transaction->id)
                ->whereIn('status', [BookingStatusEnum::PENDING, BookingStatusEnum::CONFIRMED])
                ->update([
                    'status' => BookingStatusEnum::CANCELLED,
                    'cancelled_at' => now(),
                ]);

            $this->stats['transactions_updated']++;

            Log::info('[MonitorAbandonedSessions] Transaction marked as expired', [
                'transaction_id' => $transaction->id,
                'bookings_cancelled' => $bookingsCancelled,
                'reason' => $reason
            ]);

            // Create audit trail
            $this->createRecoveryWebhookEvent($transaction, null, 'expired_session_cleanup', [
                'reason' => $reason,
                'bookings_cancelled' => $bookingsCancelled
            ]);
        });
    }

    /**
     * Clean up very old expired sessions (>7 days).
     */
    private function cleanupExpiredSessions(): void
    {
        $cleanupCutoff = now()->subDays(7);
        
        $expiredCount = Transaction::where('status', TransactionStatusEnum::CANCELLED)
            ->where('cancelled_at', '<', $cleanupCutoff)
            ->whereNotNull('stripe_checkout_session_id')
            ->count();

        if ($expiredCount > 0) {
            Log::info('[MonitorAbandonedSessions] Found old cancelled transactions for cleanup', [
                'count' => $expiredCount,
                'cutoff_date' => $cleanupCutoff->toDateString()
            ]);

            // Could implement additional cleanup logic here if needed
            // For now, just log for monitoring purposes
        }
    }

    /**
     * Create a webhook event record for recovery actions (audit trail).
     */
    private function createRecoveryWebhookEvent(Transaction $transaction, ?object $session, string $eventType, array $metadata = []): void
    {
        try {
            WebhookEvent::create([
                'stripe_event_id' => 'recovery_' . $transaction->id . '_' . time(),
                'event_type' => $eventType,
                'stripe_created_at' => now(),
                'status' => 'completed',
                'processed_at' => now(),
                'payload' => [
                    'transaction_id' => $transaction->id,
                    'stripe_session_id' => $transaction->stripe_checkout_session_id,
                    'session_data' => $session ? (array) $session : null,
                    'recovery_time' => now()->toISOString(),
                ],
                'metadata' => array_merge([
                    'source' => 'MonitorAbandonedSessions',
                    'transaction_id' => $transaction->id,
                    'recovery_type' => $eventType,
                ], $metadata),
                'processed_by' => 'MonitorAbandonedSessions::handle',
            ]);
        } catch (\Exception $e) {
            Log::warning('[MonitorAbandonedSessions] Failed to create recovery webhook event', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log final results and statistics.
     */
    private function logResults(): void
    {
        Log::info('[MonitorAbandonedSessions] Job completed', $this->stats);

        // Log important recoveries at higher level
        if ($this->stats['sessions_completed'] > 0) {
            Log::notice('[MonitorAbandonedSessions] Recovered completed payments', [
                'recovered_transactions' => $this->stats['sessions_completed'],
                'bookings_confirmed' => $this->stats['bookings_confirmed']
            ]);
        }

        if ($this->stats['sessions_expired'] > 0) {
            Log::info('[MonitorAbandonedSessions] Cleaned up expired sessions', [
                'expired_transactions' => $this->stats['sessions_expired']
            ]);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[MonitorAbandonedSessions] Job failed permanently', [
            'exception' => $exception->getMessage(),
            'stats' => $this->stats,
            'attempts' => $this->attempts()
        ]);
    }
}