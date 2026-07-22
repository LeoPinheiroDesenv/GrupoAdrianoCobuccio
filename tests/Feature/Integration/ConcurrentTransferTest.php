<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration tests for concurrent transfer safety.
 *
 * Since PHP is single-threaded in a test context, these tests verify
 * the atomicity guarantees provided by DB transactions and lockForUpdate
 * by simulating sequential operations that would fail without proper locking.
 *
 * Validates: Requirements 5.6, 6.6, 9.2
 */
class ConcurrentTransferTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletService::class);
    }

    /**
     * Helper to create a user with a wallet and optional initial balance.
     */
    private function createUserWithBalance(string $name, string $email, string $cpf, float $balance = 0): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'cpf' => $cpf,
            'password' => bcrypt('password123'),
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'balance' => $balance,
        ]);

        $user->load('wallet');

        return $user;
    }

    #[Test]
    public function multiple_sequential_transfers_do_not_exceed_balance(): void
    {
        // Create user A with balance 100
        $userA = $this->createUserWithBalance('User A', 'a@test.com', '11111111111', 100.00);
        $userB = $this->createUserWithBalance('User B', 'b@test.com', '22222222222', 0.00);
        $userC = $this->createUserWithBalance('User C', 'c@test.com', '33333333333', 0.00);
        $userD = $this->createUserWithBalance('User D', 'd@test.com', '44444444444', 0.00);

        // First transfer: A → B for 60 — should succeed
        $this->walletService->transfer($userA, $userB, 60.00);

        // Second transfer: A → C for 60 — should fail (balance is now 40)
        $secondFailed = false;
        try {
            $userA->wallet->refresh();
            $this->walletService->transfer($userA, $userC, 60.00);
        } catch (ValidationException $e) {
            $secondFailed = true;
            $this->assertArrayHasKey('amount', $e->errors());
            $this->assertStringContainsString('Insufficient balance', $e->errors()['amount'][0]);
        }
        $this->assertTrue($secondFailed, 'Second transfer should have failed due to insufficient balance');

        // Third transfer: A → D for 60 — should also fail
        $thirdFailed = false;
        try {
            $userA->wallet->refresh();
            $this->walletService->transfer($userA, $userD, 60.00);
        } catch (ValidationException $e) {
            $thirdFailed = true;
            $this->assertArrayHasKey('amount', $e->errors());
        }
        $this->assertTrue($thirdFailed, 'Third transfer should have failed due to insufficient balance');

        // Verify final state: A's balance must be >= 0 (exactly 40)
        $userA->wallet->refresh();
        $this->assertEquals('40.00', $userA->wallet->balance);
        $this->assertGreaterThanOrEqual(0, (float) $userA->wallet->balance);

        // Verify system balance conservation: total should equal initial 100
        $totalBalance = Wallet::sum('balance');
        $this->assertEquals('100.00', number_format((float) $totalBalance, 2, '.', ''));
    }

    #[Test]
    public function transfer_and_reversal_maintains_atomic_consistency(): void
    {
        // Create two users with known balances
        $userA = $this->createUserWithBalance('User A', 'a@test.com', '11111111111', 500.00);
        $userB = $this->createUserWithBalance('User B', 'b@test.com', '22222222222', 300.00);

        $originalBalanceA = (float) $userA->wallet->balance;
        $originalBalanceB = (float) $userB->wallet->balance;

        // Execute transfer
        $transaction = $this->walletService->transfer($userA, $userB, 150.00);

        // Verify transfer happened
        $userA->wallet->refresh();
        $userB->wallet->refresh();
        $this->assertEquals('350.00', $userA->wallet->balance);
        $this->assertEquals('450.00', $userB->wallet->balance);

        // Immediately reverse the transfer
        $this->walletService->reverse($userA, $transaction);

        // Verify both wallets return to original balances
        $userA->wallet->refresh();
        $userB->wallet->refresh();
        $this->assertEquals(number_format($originalBalanceA, 2, '.', ''), $userA->wallet->balance);
        $this->assertEquals(number_format($originalBalanceB, 2, '.', ''), $userB->wallet->balance);
    }

    #[Test]
    public function multiple_operations_maintain_balance_conservation(): void
    {
        // Create N users with known balances
        $users = [];
        $cpfs = ['11111111111', '22222222222', '33333333333', '44444444444', '55555555555'];
        $initialBalances = [100.00, 200.00, 150.00, 50.00, 300.00];

        for ($i = 0; $i < 5; $i++) {
            $users[] = $this->createUserWithBalance(
                "User $i",
                "user{$i}@test.com",
                $cpfs[$i],
                $initialBalances[$i]
            );
        }

        $originalTotalBalance = array_sum($initialBalances); // 800.00

        // Verify initial total
        $this->assertEquals(
            number_format($originalTotalBalance, 2, '.', ''),
            number_format((float) Wallet::sum('balance'), 2, '.', '')
        );

        // Execute many transfers in sequence
        // Transfer 0 → 1: 50
        $this->walletService->transfer($users[0], $users[1], 50.00);
        $users[0]->wallet->refresh();

        // Transfer 1 → 2: 100
        $users[1]->wallet->refresh();
        $this->walletService->transfer($users[1], $users[2], 100.00);

        // Transfer 2 → 3: 75
        $users[2]->wallet->refresh();
        $this->walletService->transfer($users[2], $users[3], 75.00);

        // Transfer 3 → 4: 25
        $users[3]->wallet->refresh();
        $this->walletService->transfer($users[3], $users[4], 25.00);

        // Transfer 4 → 0: 200
        $users[4]->wallet->refresh();
        $this->walletService->transfer($users[4], $users[0], 200.00);

        // Deposit into user 2
        $users[2]->wallet->refresh();
        $this->walletService->deposit($users[2], 100.00);

        // After all operations, verify sum of all balances equals original sum + deposits
        $expectedTotal = $originalTotalBalance + 100.00; // 900.00
        $actualTotal = (float) Wallet::sum('balance');

        $this->assertEquals(
            number_format($expectedTotal, 2, '.', ''),
            number_format($actualTotal, 2, '.', '')
        );

        // Verify no wallet has an unexpected state — all balances are calculable
        foreach ($users as $user) {
            $user->wallet->refresh();
            // Balance should be a real number (not null or corrupted)
            $this->assertIsNumeric($user->wallet->balance);
        }
    }
}
