<?php

namespace App\Modules\Membership\Services;

use App\Enums\TransactionStatusEnum;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Membership\DataTransferObjects\MembershipPurchaseData;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

class MembershipService
{
    /**
     * Initiates the membership purchase process.
     *
     * - Validates the membership level.
     * - Creates a transaction record.
     * - Initiates a Stripe Checkout session and returns the redirect URL.
     *
     * @param \App\Models\User $user
     * @param \App\Modules\Membership\DataTransferObjects\MembershipPurchaseData $data
     * @return array
     * @throws \Stripe\Exception\ApiErrorException|\Exception
     */
    public function initiateMembershipPurchase(User $user, MembershipPurchaseData $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $membershipLevel = MembershipLevel::findOrFail($data->membership_level_id);
            $totalAmount = $membershipLevel->price;
            $currency = config('cashier.currency');

            // 1. Create a transaction record to track this purchase attempt.
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'status' => TransactionStatusEnum::PENDING_PAYMENT,
                'metadata' => [
                    'type' => 'membership_purchase',
                    'membership_level_id' => $membershipLevel->id,
                    'membership_level_name' => $membershipLevel->getTranslation('name', 'en'),
                ],
            ]);

            // 2. Prepare the line item for Stripe Checkout.
            $lineItemsForStripe = [
                [
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $totalAmount,
                        'product_data' => [
                            'name' => $membershipLevel->name,
                            'description' => $membershipLevel->description,
                        ],
                    ],
                    'quantity' => 1,
                ],
            ];

            // 3. Prepare parameters for the Stripe Checkout session.
            $checkoutParams = [
                'success_url' => route('membership.payment.success', ['transaction_id' => $transaction->id]) . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('membership.payment.cancel', ['transaction_id' => $transaction->id]),
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'user_id' => $user->id,
                    'customer_email' => $user->email,
                ],
            ];

            // 4. Create the Stripe Checkout session.
            try {
                $checkoutSession = $user->checkout($lineItemsForStripe, $checkoutParams);

                // 5. Save the Stripe session ID to our transaction record.
                $transaction->update([
                    'payment_gateway' => 'stripe',
                    'payment_gateway_transaction_id' => $checkoutSession->id,
                ]);

                return [
                    'requires_payment' => true,
                    'checkout_url' => $checkoutSession->url,
                    'transaction_id' => $transaction->id,
                ];
            } catch (ApiErrorException $e) {
                Log::error('Stripe API Error during membership purchase initiation: ' . $e->getMessage(), [
                    'transaction_id' => $transaction->id,
                ]);
                throw $e;
            } catch (\Exception $e) {
                Log::error('General error during membership purchase initiation: ' . $e->getMessage(), [
                    'transaction_id' => $transaction->id,
                ]);
                throw $e;
            }
        });
    }
}
