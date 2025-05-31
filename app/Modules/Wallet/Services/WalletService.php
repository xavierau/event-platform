<?php

namespace App\Modules\Wallet\Services;

use App\Models\User;
use App\Modules\Wallet\Actions\AddKillPointsAction;
use App\Modules\Wallet\Actions\AddPointsAction;
use App\Modules\Wallet\Actions\SpendKillPointsAction;
use App\Modules\Wallet\Actions\SpendPointsAction;
use App\Modules\Wallet\DataTransferObjects\WalletData;
use App\Modules\Wallet\Enums\WalletTransactionType;
use App\Modules\Wallet\Exceptions\InsufficientKillPointsException;
use App\Modules\Wallet\Exceptions\InsufficientPointsException;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WalletService
{
    public function __construct(
        private readonly AddPointsAction $addPointsAction,
        private readonly SpendPointsAction $spendPointsAction,
        private readonly AddKillPointsAction $addKillPointsAction,
        private readonly SpendKillPointsAction $spendKillPointsAction,
    ) {}

    /**
     * Get or create a wallet for a user.
     */
    public function getOrCreateWallet(User $user): Wallet
    {
        return $user->wallet ?? $this->createWallet($user);
    }

    /**
     * Create a new wallet for a user.
     */
    public function createWallet(User $user): Wallet
    {
        $walletData = new WalletData(user_id: $user->id);

        return Wallet::create($walletData->toArray());
    }

    /**
     * Add points to a user's wallet.
     */
    public function addPoints(
        User $user,
        int $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): WalletTransaction {
        $wallet = $this->getOrCreateWallet($user);

        return $this->addPointsAction->execute(
            $wallet,
            $amount,
            $description,
            $referenceType,
            $referenceId,
            $metadata
        );
    }

    /**
     * Spend points from a user's wallet.
     *
     * @throws InsufficientPointsException
     */
    public function spendPoints(
        User $user,
        int $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): WalletTransaction {
        $wallet = $this->getOrCreateWallet($user);

        return $this->spendPointsAction->execute(
            $wallet,
            $amount,
            $description,
            $referenceType,
            $referenceId,
            $metadata
        );
    }

    /**
     * Add kill points to a user's wallet.
     */
    public function addKillPoints(
        User $user,
        int $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): WalletTransaction {
        $wallet = $this->getOrCreateWallet($user);

        return $this->addKillPointsAction->execute(
            $wallet,
            $amount,
            $description,
            $referenceType,
            $referenceId,
            $metadata
        );
    }

    /**
     * Spend kill points from a user's wallet.
     *
     * @throws InsufficientKillPointsException
     */
    public function spendKillPoints(
        User $user,
        int $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): WalletTransaction {
        $wallet = $this->getOrCreateWallet($user);

        return $this->spendKillPointsAction->execute(
            $wallet,
            $amount,
            $description,
            $referenceType,
            $referenceId,
            $metadata
        );
    }

    /**
     * Get wallet balance for a user.
     */
    public function getBalance(User $user): array
    {
        // Refresh the wallet relationship to get the latest data from database
        $user->load('wallet');
        $wallet = $this->getOrCreateWallet($user);

        return [
            'points_balance' => $wallet->points_balance,
            'kill_points_balance' => $wallet->kill_points_balance,
            'total_points_earned' => $wallet->total_points_earned,
            'total_points_spent' => $wallet->total_points_spent,
            'total_kill_points_earned' => $wallet->total_kill_points_earned,
            'total_kill_points_spent' => $wallet->total_kill_points_spent,
        ];
    }

    /**
     * Get transaction history for a user.
     */
    public function getTransactionHistory(
        User $user,
        ?WalletTransactionType $type = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $wallet = $this->getOrCreateWallet($user);

        $query = $wallet->transactions()
            ->latest()
            ->with(['wallet', 'user']);

        if ($type) {
            $query->ofType($type);
        }

        return $query->paginate($perPage);
    }

    /**
     * Transfer points between users.
     *
     * @throws InsufficientPointsException
     */
    public function transferPoints(
        User $fromUser,
        User $toUser,
        int $amount,
        string $description = 'Points transfer'
    ): array {
        // Spend points from sender
        $spendTransaction = $this->spendPoints(
            $fromUser,
            $amount,
            "Transfer to {$toUser->name}: {$description}",
            User::class,
            $toUser->id,
            ['transfer_type' => 'outgoing', 'recipient_id' => $toUser->id]
        );

        // Add points to receiver
        $addTransaction = $this->addPoints(
            $toUser,
            $amount,
            "Transfer from {$fromUser->name}: {$description}",
            User::class,
            $fromUser->id,
            ['transfer_type' => 'incoming', 'sender_id' => $fromUser->id]
        );

        return [
            'spend_transaction' => $spendTransaction,
            'add_transaction' => $addTransaction,
        ];
    }

    /**
     * Check if user has enough points.
     */
    public function hasEnoughPoints(User $user, int $amount): bool
    {
        $wallet = $this->getOrCreateWallet($user);
        return $wallet->hasEnoughPoints($amount);
    }

    /**
     * Check if user has enough kill points.
     */
    public function hasEnoughKillPoints(User $user, int $amount): bool
    {
        $wallet = $this->getOrCreateWallet($user);
        return $wallet->hasEnoughKillPoints($amount);
    }
}
