<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReversalController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * Handle transaction reversal.
     *
     * Validates transaction exists and belongs to authenticated user,
     * delegates to WalletService for atomic reversal.
     */
    public function reverse(string $transaction_id): JsonResponse
    {
        $transaction = Transaction::find((int) $transaction_id);

        if (!$transaction) {
            return response()->json([
                'errors' => ['transaction' => ['Transaction not found.']],
            ], 404);
        }

        $user = auth()->user();

        try {
            $reversalTransaction = $this->walletService->reverse($user, $transaction);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        }

        $user->wallet->refresh();

        return response()->json([
            'uuid' => $reversalTransaction->uuid,
            'type' => $reversalTransaction->type,
            'amount' => $reversalTransaction->amount,
            'balance' => $user->wallet->balance,
        ], 201);
    }
}
