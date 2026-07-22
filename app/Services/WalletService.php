<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Wallet service for financial operations.
 */
class WalletService
{
    /**
     * Deposit amount into user's wallet atomically.
     *
     * @param User $user
     * @param float $amount
     * @return Transaction
     */
    public function deposit(User $user, float $amount): Transaction
    {
        try {
            $transaction = DB::transaction(function () use ($user, $amount) {
                $wallet = $user->wallet;

                $wallet->increment('balance', $amount);

                $transaction = Transaction::create([
                    'uuid' => Str::uuid()->toString(),
                    'wallet_id' => $wallet->id,
                    'type' => 'deposit',
                    'amount' => $amount,
                ]);

                return $transaction;
            });

            $user->wallet->refresh();

            Log::info('deposit.success', [
                'user_id' => $user->id,
                'amount' => $amount,
                'transaction_id' => $transaction->uuid,
                'new_balance' => $user->wallet->balance,
            ]);

            return $transaction;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('operation.failed', [
                'user_id' => $user->id,
                'operation' => 'deposit',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Transfer amount from sender to receiver atomically.
     *
     * @param User $sender
     * @param User $receiver
     * @param float $amount
     * @return Transaction The sender's transfer_sent transaction
     *
     * @throws ValidationException
     */
    public function transfer(User $sender, User $receiver, float $amount): Transaction
    {
        if ($sender->id === $receiver->id) {
            throw ValidationException::withMessages([
                'receiver' => ['Cannot transfer to yourself.'],
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Transfer amount must be greater than zero.'],
            ]);
        }

        try {
            $senderTransaction = DB::transaction(function () use ($sender, $receiver, $amount) {
                // Lock wallets in consistent order (by id) to prevent deadlocks
                $firstId = min($sender->wallet->id, $receiver->wallet->id);
                $secondId = max($sender->wallet->id, $receiver->wallet->id);

                $firstWallet = Wallet::where('id', $firstId)->lockForUpdate()->first();
                $secondWallet = Wallet::where('id', $secondId)->lockForUpdate()->first();

                $senderWallet = $firstWallet->id === $sender->wallet->id ? $firstWallet : $secondWallet;
                $receiverWallet = $firstWallet->id === $receiver->wallet->id ? $firstWallet : $secondWallet;

                // Check sufficient balance
                if ($senderWallet->balance < $amount) {
                    Log::warning('transfer.insufficient_balance', [
                        'user_id' => $sender->id,
                        'amount' => $amount,
                        'current_balance' => $senderWallet->balance,
                    ]);

                    throw ValidationException::withMessages([
                        'amount' => ['Insufficient balance.'],
                    ]);
                }

                // Debit sender
                $senderWallet->decrement('balance', $amount);

                // Credit receiver
                $receiverWallet->increment('balance', $amount);

                // Create transaction for sender
                $senderTransaction = Transaction::create([
                    'uuid' => Str::uuid()->toString(),
                    'wallet_id' => $senderWallet->id,
                    'target_wallet_id' => $receiverWallet->id,
                    'type' => 'transfer_sent',
                    'amount' => $amount,
                ]);

                // Create transaction for receiver
                Transaction::create([
                    'uuid' => Str::uuid()->toString(),
                    'wallet_id' => $receiverWallet->id,
                    'target_wallet_id' => $senderWallet->id,
                    'type' => 'transfer_received',
                    'amount' => $amount,
                ]);

                return $senderTransaction;
            });

            Log::info('transfer.success', [
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'amount' => $amount,
                'transaction_id' => $senderTransaction->uuid,
            ]);

            return $senderTransaction;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('operation.failed', [
                'user_id' => $sender->id,
                'operation' => 'transfer',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Reverse a transaction atomically.
     *
     * @param User $user
     * @param Transaction $transaction
     * @return Transaction The reversal transaction
     *
     * @throws ValidationException
     */
    public function reverse(User $user, Transaction $transaction): Transaction
    {
        // Validate transaction belongs to user's wallet
        $userWallet = $user->wallet;
        if ($transaction->wallet_id !== $userWallet->id) {
            throw ValidationException::withMessages([
                'transaction' => ['Transaction does not belong to this user.'],
            ]);
        }

        // Validate transaction has not already been reversed
        if ($transaction->is_reversed) {
            Log::warning('reversal.already_reversed', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->uuid,
            ]);

            throw ValidationException::withMessages([
                'transaction' => ['Transaction has already been reversed.'],
            ]);
        }

        try {
            $reversalTransaction = DB::transaction(function () use ($user, $transaction, $userWallet) {
                if ($transaction->type === 'deposit') {
                    // For deposit reversal: subtract amount from user's wallet
                    $wallet = Wallet::where('id', $userWallet->id)->lockForUpdate()->first();
                    $wallet->decrement('balance', $transaction->amount);
                } elseif ($transaction->type === 'transfer_sent') {
                    // For transfer reversal: credit sender, debit receiver
                    $senderWalletId = $userWallet->id;
                    $receiverWalletId = $transaction->target_wallet_id;

                    // Lock wallets in consistent order (by id) to prevent deadlocks
                    $firstId = min($senderWalletId, $receiverWalletId);
                    $secondId = max($senderWalletId, $receiverWalletId);

                    $firstWallet = Wallet::where('id', $firstId)->lockForUpdate()->first();
                    $secondWallet = Wallet::where('id', $secondId)->lockForUpdate()->first();

                    $senderWallet = $firstWallet->id === $senderWalletId ? $firstWallet : $secondWallet;
                    $receiverWallet = $firstWallet->id === $receiverWalletId ? $firstWallet : $secondWallet;

                    // Credit sender (add amount back)
                    $senderWallet->increment('balance', $transaction->amount);

                    // Debit receiver (subtract amount, allow negative balance)
                    $receiverWallet->decrement('balance', $transaction->amount);
                }

                // Mark original transaction as reversed
                $transaction->update(['is_reversed' => true]);

                // Create reversal transaction
                $reversalTransaction = Transaction::create([
                    'uuid' => Str::uuid()->toString(),
                    'wallet_id' => $userWallet->id,
                    'type' => 'reversal',
                    'amount' => $transaction->amount,
                    'reversed_transaction_id' => $transaction->id,
                ]);

                return $reversalTransaction;
            });

            Log::info('reversal.success', [
                'user_id' => $user->id,
                'original_transaction_id' => $transaction->uuid,
                'reversal_transaction_id' => $reversalTransaction->uuid,
                'amount' => $transaction->amount,
            ]);

            return $reversalTransaction;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('operation.failed', [
                'user_id' => $user->id,
                'operation' => 'reversal',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
