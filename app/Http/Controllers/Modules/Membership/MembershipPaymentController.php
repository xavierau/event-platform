<?php

namespace App\Http\Controllers\Modules\Membership;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Enums\TransactionStatusEnum;

class MembershipPaymentController extends Controller
{
    /**
     * Handle the successful payment redirect from Stripe.
     */
    public function handlePaymentSuccess(Request $request)
    {
        $transactionId = $request->query('transaction_id');
        $sessionId = $request->query('session_id');

        Log::info("Membership payment success redirect for transaction: {$transactionId}, session: {$sessionId}");

        // The actual purchase confirmation should be handled by a webhook
        // to ensure reliability. This page is just for user feedback.

        $transaction = Transaction::find($transactionId);

        // Here you would typically redirect to a dedicated success page
        // with the user's new membership details.
        return redirect()->route('dashboard')->with('success', 'Your membership purchase is being processed!');
    }

    /**
     * Handle the cancelled payment redirect from Stripe.
     */
    public function handlePaymentCancel(Request $request)
    {
        $transactionId = $request->query('transaction_id');
        Log::warning("Membership payment was cancelled for transaction: {$transactionId}");

        // You can update the transaction status here if you wish,
        // though it's often better to let a webhook handle final states.
        $transaction = Transaction::find($transactionId);
        if ($transaction && $transaction->status === TransactionStatusEnum::PENDING_PAYMENT) {
            // Optional: Mark as cancelled if not already updated by a webhook.
            // $transaction->update(['status' => TransactionStatusEnum::CANCELLED]);
        }

        return redirect()->route('dashboard')->with('error', 'Your membership purchase was cancelled.');
    }
}
