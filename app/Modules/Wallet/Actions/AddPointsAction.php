<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\DataTransferObjects\WalletTransactionData;
use App\Modules\Wallet\Enums\WalletTransactionType;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class AddPointsAction
{
    /**
     * Add points to a user's wallet.
     */
    public function execute(
        Wallet $wallet,
        int $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): WalletTransaction {
        return DB::transaction(function () use ($wallet, $amount, $description, $referenceType, $referenceId, $metadata) {
            // Add points to wallet
            $wallet->addPoints($amount);

            // Create transaction record
            $transactionData = new WalletTransactionData(
                user_id: $wallet->user_id,
                wallet_id: $wallet->id,
                transaction_type: WalletTransactionType::EARN_POINTS,
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
