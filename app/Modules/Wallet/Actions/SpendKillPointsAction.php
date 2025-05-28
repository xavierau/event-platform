<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\DataTransferObjects\WalletTransactionData;
use App\Modules\Wallet\Enums\WalletTransactionType;
use App\Modules\Wallet\Exceptions\InsufficientKillPointsException;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class SpendKillPointsAction
{
    /**
     * Spend kill points from a user's wallet.
     *
     * @throws InsufficientKillPointsException
     */
    public function execute(
        Wallet $wallet,
        int $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): WalletTransaction {
        if (!$wallet->hasEnoughKillPoints($amount)) {
            throw new InsufficientKillPointsException(
                "Insufficient kill points. Required: {$amount}, Available: {$wallet->kill_points_balance}"
            );
        }

        return DB::transaction(function () use ($wallet, $amount, $description, $referenceType, $referenceId, $metadata) {
            // Spend kill points from wallet
            $wallet->spendKillPoints($amount);

            // Create transaction record
            $transactionData = new WalletTransactionData(
                user_id: $wallet->user_id,
                wallet_id: $wallet->id,
                transaction_type: WalletTransactionType::SPEND_KILL_POINTS,
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
