<?php

namespace Tests\Feature\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based test for balance conservation on transfer.
 *
 * **Validates: Requirements 5.2, 5.6, 9.4**
 *
 * Property 2: Balance Conservation — For all valid transfers,
 * the total sum of all wallet balances in the system SHALL remain constant.
 *
 * FOR ALL valid transfers t:
 *   sum(all_wallet_balances) BEFORE == sum(all_wallet_balances) AFTER
 */
class TransferBalanceConservationPropertyTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = new WalletService();
    }

    /**
     * Create a set of users, each with a random initial balance.
     *
     * @param int $count Number of users to create
     * @return array<User>
     */
    private function createUsersWithRandomBalances(int $count): array
    {
        $users = [];

        for ($i = 0; $i < $count; $i++) {
            $user = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'cpf' => str_pad((string) (10000000000 + $i), 11, '0', STR_PAD_LEFT),
                'password' => Hash::make('password123'),
            ]);

            // Random initial balance between 100.00 and 10000.00
            $initialBalance = round(random_int(10000, 1000000) / 100, 2);

            $user->wallet()->create(['balance' => $initialBalance]);

            $users[] = $user;
        }

        return $users;
    }

    /**
     * Get the total sum of all wallet balances in the system.
     */
    private function getTotalSystemBalance(): float
    {
        return (float) Wallet::sum('balance');
    }

    /**
     * Property 2: Balance Conservation
     *
     * FOR ALL valid transfers t:
     *   sum(all_wallet_balances) BEFORE == sum(all_wallet_balances) AFTER
     *
     * **Validates: Requirements 5.2, 5.6, 9.4**
     */
    #[Test]
    public function balance_conservation_property_holds_for_all_valid_transfers(): void
    {
        $userCount = 5;
        $iterations = 50;

        $users = $this->createUsersWithRandomBalances($userCount);

        // Record total system balance before any transfers
        $originalTotalBalance = $this->getTotalSystemBalance();

        for ($i = 0; $i < $iterations; $i++) {
            // Pick random sender and receiver (different users)
            $senderIndex = random_int(0, $userCount - 1);
            do {
                $receiverIndex = random_int(0, $userCount - 1);
            } while ($receiverIndex === $senderIndex);

            $sender = $users[$senderIndex];
            $receiver = $users[$receiverIndex];

            // Refresh sender's wallet to get current balance
            $sender->wallet->refresh();
            $senderBalance = (float) $sender->wallet->balance;

            // Skip if sender has no balance to transfer
            if ($senderBalance <= 0) {
                continue;
            }

            // Pick random amount <= sender's current balance (between 0.01 and sender's balance)
            $maxCents = (int) ($senderBalance * 100);
            if ($maxCents < 1) {
                continue;
            }
            $amount = round(random_int(1, $maxCents) / 100, 2);

            // Execute transfer
            $this->walletService->transfer($sender, $receiver, $amount);

            // After transfer, assert total system balance is conserved
            $currentTotalBalance = $this->getTotalSystemBalance();

            $this->assertEquals(
                round($originalTotalBalance, 2),
                round($currentTotalBalance, 2),
                sprintf(
                    'Balance conservation violated on iteration %d: '
                    . 'sender=User%d (balance=%.2f), receiver=User%d, amount=%.2f, '
                    . 'expected_total=%.2f, actual_total=%.2f',
                    $i + 1,
                    $senderIndex,
                    $senderBalance,
                    $receiverIndex,
                    $amount,
                    $originalTotalBalance,
                    $currentTotalBalance
                )
            );
        }
    }
}
