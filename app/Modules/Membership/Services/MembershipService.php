<?php

namespace App\Modules\Membership\Services;

use App\Enums\TransactionStatusEnum;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Membership\Actions\PurchaseMembershipAction;
use App\Modules\Membership\DataTransferObjects\MembershipPurchaseData;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use App\Modules\Membership\Exceptions\PaymentMethodNotAllowedException;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Wallet\Exceptions\InsufficientKillPointsException;
use App\Modules\Wallet\Exceptions\InsufficientPointsException;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

class MembershipService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly PurchaseMembershipAction $purchaseMembershipAction
    ) {}

    /**
     * Initiates the membership purchase process.
     *
     * - Validates the membership level.
     * - Handles payment via different methods (Points, Kill Points, Stripe).
     * - Creates a transaction record.
     * - For Stripe, initiates a Checkout session and returns the redirect URL.
     * - For Wallet, deducts points and creates the membership directly.
     *
     * @param User $user
     * @param MembershipPurchaseData $data
     * @return array|UserMembership
     * @throws ApiErrorException
     * @throws InsufficientKillPointsException
     * @throws InsufficientPointsException
     * @throws \Exception
     */
    public function purchaseMembership(User $user, MembershipPurchaseData $data): array|UserMembership
    {
        $membershipLevel = MembershipLevel::findOrFail($data->membership_level_id);
        $totalAmount = $membershipLevel->price;
        $currency = config('cashier.currency');

        // Handle wallet-based payments
        if ($data->payment_method->usesWallet()) {
            return $this->purchaseWithWallet($user, $data, $membershipLevel);
        }

        // Handle Stripe payment
        if ($data->payment_method === PaymentMethod::STRIPE) {
            return $this->initiateStripePurchase($user, $data, $membershipLevel, $totalAmount, $currency);
        }

        // Handle free/admin grants if necessary (or throw exception for unsupported methods)
        // For now, let's assume only wallet and Stripe are supported for direct purchase
        throw new \Exception("Unsupported payment method for direct purchase: {$data->payment_method->value}");
    }

    /**
     * @throws InsufficientPointsException
     * @throws InsufficientKillPointsException
     * @throws PaymentMethodNotAllowedException
     */
    private function purchaseWithWallet(User $user, MembershipPurchaseData $data, MembershipLevel $level): UserMembership
    {
        return DB::transaction(function () use ($user, $data, $level) {
            $description = "Purchase of {$level->name} membership";

            $transaction = match ($data->payment_method) {
                PaymentMethod::POINTS => $this->processPointsPayment($user, $level, $description),
                PaymentMethod::KILL_POINTS => $this->processKillPointsPayment($user, $level, $description),
                default => throw new \InvalidArgumentException('Unsupported wallet payment method.'),
            };

            // Update the DTO with the transaction reference
            $data->transaction_reference = $transaction->id;

            return $this->purchaseMembershipAction->execute($user, $data);
        });
    }

    /**
     * @throws PaymentMethodNotAllowedException
     * @throws InsufficientPointsException
     */
    private function processPointsPayment(User $user, MembershipLevel $level, string $description)
    {
        if (is_null($level->points_cost)) {
            throw new PaymentMethodNotAllowedException("This membership level cannot be purchased with points.");
        }
        return $this->walletService->spendPoints($user, $level->points_cost, $description, MembershipLevel::class, $level->id);
    }

    /**
     * @throws PaymentMethodNotAllowedException
     * @throws InsufficientKillPointsException
     */
    private function processKillPointsPayment(User $user, MembershipLevel $level, string $description)
    {
        if (is_null($level->kill_points_cost)) {
            throw new PaymentMethodNotAllowedException("This membership level cannot be purchased with kill points.");
        }
        return $this->walletService->spendKillPoints($user, $level->kill_points_cost, $description, MembershipLevel::class, $level->id);
    }

    /**
     * @throws ApiErrorException
     */
    private function initiateStripePurchase(User $user, MembershipPurchaseData $data, MembershipLevel $level, int $totalAmount, string $currency): array
    {
        return DB::transaction(function () use ($user, $data, $level, $totalAmount, $currency) {
            // 1. Create a transaction record to track this purchase attempt.
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'status' => TransactionStatusEnum::PENDING_PAYMENT,
                'metadata' => [
                    'type' => 'membership_purchase',
                    'membership_level_id' => $level->id,
                    'membership_level_name' => $level->getTranslation('name', 'en'),
                ],
            ]);

            // 2. Prepare the line item for Stripe Checkout.
            $lineItemsForStripe = [
                [
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $totalAmount,
                        'product_data' => [
                            'name' => $level->name,
                            'description' => $level->description,
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

    public function checkMembershipStatus(User $user): ?UserMembership
    {
        return $user->memberships()->latest('started_at')->first();
    }

    public function renewMembership(User $user, ?int $months = null): ?UserMembership
    {
        $membership = $this->checkMembershipStatus($user);

        if ($membership) {
            $membership->renew($months);
        }

        return $membership;
    }

    public function cancelMembership(User $user): ?UserMembership
    {
        $membership = $this->checkMembershipStatus($user);

        if ($membership) {
            $membership->cancel();
        }

        return $membership;
    }

    public function getMembershipBenefits(User $user): ?array
    {
        $membership = $this->checkMembershipStatus($user);

        return $membership?->level?->benefits;
    }

    public function handleExpiredMemberships(): void
    {
        UserMembership::expired()->where('status', '!=', MembershipStatus::EXPIRED)->get()->each->expire();
    }
}
