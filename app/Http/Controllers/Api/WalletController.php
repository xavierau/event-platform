<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Wallet\Exceptions\InsufficientKillPointsException;
use App\Modules\Wallet\Exceptions\InsufficientPointsException;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * Get user's wallet balance
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();
        $balance = $this->walletService->getBalance($user);

        return response()->json([
            'success' => true,
            'data' => $balance,
        ]);
    }

    /**
     * Get user's transaction history
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $transactions = $this->walletService->getTransactionHistory($user);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Add points to user's wallet
     */
    public function addPoints(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $transaction = $this->walletService->addPoints(
            $user,
            $validated['amount'],
            $validated['description']
        );

        $newBalance = $this->walletService->getBalance($user);

        return response()->json([
            'success' => true,
            'message' => 'Points added successfully',
            'data' => [
                'transaction' => [
                    'id' => $transaction->id,
                    'transaction_type' => $transaction->transaction_type->value,
                    'amount' => $transaction->amount,
                    'description' => $transaction->description,
                ],
                'new_balance' => [
                    'points_balance' => $newBalance['points_balance'],
                    'kill_points_balance' => $newBalance['kill_points_balance'],
                ],
            ],
        ]);
    }

    /**
     * Add kill points to user's wallet
     */
    public function addKillPoints(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $transaction = $this->walletService->addKillPoints(
            $user,
            $validated['amount'],
            $validated['description']
        );

        $newBalance = $this->walletService->getBalance($user);

        return response()->json([
            'success' => true,
            'message' => 'Kill points added successfully',
            'data' => [
                'transaction' => [
                    'id' => $transaction->id,
                    'transaction_type' => $transaction->transaction_type->value,
                    'amount' => $transaction->amount,
                    'description' => $transaction->description,
                ],
                'new_balance' => [
                    'points_balance' => $newBalance['points_balance'],
                    'kill_points_balance' => $newBalance['kill_points_balance'],
                ],
            ],
        ]);
    }

    /**
     * Spend points from user's wallet
     */
    public function spendPoints(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        try {
            $transaction = $this->walletService->spendPoints(
                $user,
                $validated['amount'],
                $validated['description']
            );

            $newBalance = $this->walletService->getBalance($user);

            return response()->json([
                'success' => true,
                'message' => 'Points spent successfully',
                'data' => [
                    'transaction' => [
                        'id' => $transaction->id,
                        'transaction_type' => $transaction->transaction_type->value,
                        'amount' => $transaction->amount,
                        'description' => $transaction->description,
                    ],
                    'new_balance' => [
                        'points_balance' => $newBalance['points_balance'],
                        'kill_points_balance' => $newBalance['kill_points_balance'],
                    ],
                ],
            ]);
        } catch (InsufficientPointsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Spend kill points from user's wallet
     */
    public function spendKillPoints(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        try {
            $transaction = $this->walletService->spendKillPoints(
                $user,
                $validated['amount'],
                $validated['description']
            );

            $newBalance = $this->walletService->getBalance($user);

            return response()->json([
                'success' => true,
                'message' => 'Kill points spent successfully',
                'data' => [
                    'transaction' => [
                        'id' => $transaction->id,
                        'transaction_type' => $transaction->transaction_type->value,
                        'amount' => $transaction->amount,
                        'description' => $transaction->description,
                    ],
                    'new_balance' => [
                        'points_balance' => $newBalance['points_balance'],
                        'kill_points_balance' => $newBalance['kill_points_balance'],
                    ],
                ],
            ]);
        } catch (InsufficientKillPointsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Transfer points between users
     */
    public function transfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn([$request->user()->id]) // Cannot transfer to self
            ],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $recipient = User::findOrFail($validated['recipient_id']);

        try {
            $result = $this->walletService->transferPoints(
                $user,
                $recipient,
                $validated['amount'],
                $validated['description']
            );

            $senderBalance = $this->walletService->getBalance($user);
            $recipientBalance = $this->walletService->getBalance($recipient);

            return response()->json([
                'success' => true,
                'message' => 'Points transferred successfully',
                'data' => [
                    'spend_transaction' => [
                        'id' => $result['spend_transaction']->id,
                        'amount' => $result['spend_transaction']->amount,
                        'description' => $result['spend_transaction']->description,
                    ],
                    'add_transaction' => [
                        'id' => $result['add_transaction']->id,
                        'amount' => $result['add_transaction']->amount,
                        'description' => $result['add_transaction']->description,
                    ],
                    'sender_new_balance' => [
                        'points_balance' => $senderBalance['points_balance'],
                        'kill_points_balance' => $senderBalance['kill_points_balance'],
                    ],
                    'recipient_new_balance' => [
                        'points_balance' => $recipientBalance['points_balance'],
                        'kill_points_balance' => $recipientBalance['kill_points_balance'],
                    ],
                ],
            ]);
        } catch (InsufficientPointsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
