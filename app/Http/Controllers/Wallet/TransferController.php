<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferRequest;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TransferController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * Handle transfer between users.
     *
     * Validates input, finds receiver, and delegates to WalletService.
     * Returns appropriate error messages for insufficient balance,
     * self-transfer, or invalid receiver.
     */
    public function transfer(TransferRequest $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        $receiver = User::find($validated['receiver_id']);

        try {
            $transaction = $this->walletService->transfer(
                $user,
                $receiver,
                (float) $validated['amount']
            );
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        }

        $user->wallet->refresh();

        return response()->json([
            'uuid' => $transaction->uuid,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'balance' => $user->wallet->balance,
        ], 201);
    }
}
