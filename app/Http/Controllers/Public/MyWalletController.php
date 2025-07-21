<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyWalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    )
    {
    }

    /**
     * Display the user's wallet page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user(); // Auth middleware should ensure user is not null

        $balance = $this->walletService->getBalance($user);
        // TODO: Add filtering capabilities to getTransactionHistory if needed for frontend filters
        $transactions = $this->walletService->getTransactionHistory($user, null, 15); // Default 15 per page

        return Inertia::render('Public/MyWallet', [
            'balance' => $balance,
            'transactions' => $transactions,
            'code' => $this->walletService->encodeWalletCode($this->walletService->getOrCreateWallet($user)), // Assuming the wallet has a code attribute
        ]);
    }
}
