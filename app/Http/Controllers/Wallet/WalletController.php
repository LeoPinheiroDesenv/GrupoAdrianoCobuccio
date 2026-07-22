<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepositRequest;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * Get wallet balance.
     *
     * Returns the current authenticated user's wallet balance.
     */
    public function balance(): JsonResponse
    {
        $user = auth()->user();
        $wallet = $user->wallet;

        return response()->json([
            'balance' => $wallet->balance,
        ]);
    }

    /**
     * Get wallet statement.
     *
     * Returns the list of transactions ordered by date descending,
     * with type, amount, date, and reversal status.
     */
    public function statement(): JsonResponse
    {
        $user = auth()->user();
        $wallet = $user->wallet;

        $transactions = $wallet->transactions()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'uuid' => $transaction->uuid,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'date' => $transaction->created_at->toIso8601String(),
                    'is_reversed' => $transaction->is_reversed,
                ];
            });

        return response()->json($transactions);
    }

    /**
     * Handle deposit.
     *
     * Validates amount and creates a deposit transaction atomically.
     */
    public function deposit(DepositRequest $request): JsonResponse
    {
        $user = auth()->user();
        $amount = (float) $request->validated()['amount'];

        $transaction = $this->walletService->deposit($user, $amount);

        $user->wallet->refresh();

        return response()->json([
            'uuid' => $transaction->uuid,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'balance' => $user->wallet->balance,
        ], 201);
    }
}
