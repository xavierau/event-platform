<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\DataTransferObjects\WalletTransactionData;
use App\Modules\Wallet\Enums\WalletTransactionType;
use App\Modules\Wallet\Exceptions\InsufficientPointsException;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class SpendPointsAction
{
    /**
     * Spend points from a user's wallet.
     *
     * @throws InsufficientPointsException
     */
    public function execute(
        Wallet $wallet,
        int $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): WalletTransaction {
        if (!$wallet->hasEnoughPoints($amount)) {
            throw new InsufficientPointsException(
                "Insufficient points. Required: {$amount}, Available: {$wallet->points_balance}"
            );
        }

        return DB::transaction(function () use ($wallet, $amount, $description, $referenceType, $referenceId, $metadata) {
            // Spend points from wallet
            $wallet->spendPoints($amount);

            // Create transaction record
            $transactionData = new WalletTransactionData(
                user_id: $wallet->user_id,
                wallet_id: $wallet->id,
                transaction_type: WalletTransactionType::SPEND_POINTS,
                amount: $amount,
                description: $description,
                reference_type: $referenceType,
                reference_id: $referenceId,
                metadata: $metadata
            );

            return WalletTransaction::create($transactionData->toArray());
        });
    }
}
