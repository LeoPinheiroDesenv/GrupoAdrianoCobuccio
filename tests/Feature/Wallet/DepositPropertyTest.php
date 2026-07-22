<?php

namespace Tests\Feature\Wallet;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based test for deposit round-trip consistency.
 *
 * **Validates: Requirements 4.1, 9.3**
 *
 * Property 1: Deposit Round-Trip — For all valid deposit amounts,
 * depositing and then querying balance SHALL return previous balance + deposit amount.
 *
 * FOR ALL valid deposit amounts `a` where a > 0:
 *   Let balance_before = wallet.balance
 *   After deposit(a):
 *     wallet.balance == balance_before + a
 */
class DepositPropertyTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = new WalletService();
    }

    private function createUser(): User
    {
        $user = User::create([
            'name' => 'Property Test User',
            'email' => 'property-test@example.com',
            'cpf' => '98765432100',
            'password' => Hash::make('password123'),
        ]);

        $user->wallet()->create(['balance' => 0]);

        return $user;
    }

    /**
     * Generate a random valid deposit amount between 0.01 and 999999.99.
     */
    private function generateValidDepositAmount(): float
    {
        // Generate random cents between 1 and 99999999 (0.01 to 999999.99)
        $cents = random_int(1, 99999999);
        return round($cents / 100, 2);
    }

    /**
     * Property 1: Deposit Round-Trip
     *
     * FOR ALL valid deposit amounts `a` where a > 0:
     *   Let balance_before = wallet.balance
     *   After deposit(a):
     *     wallet.balance == balance_before + a
     *
     * **Validates: Requirements 4.1, 9.3**
     */
    #[Test]
    public function deposit_round_trip_property_holds_for_all_valid_amounts(): void
    {
        $user = $this->createUser();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $amount = $this->generateValidDepositAmount();

            // Record balance before deposit
            $user->wallet->refresh();
            $balanceBefore = (float) $user->wallet->balance;

            // Perform deposit
            $this->walletService->deposit($user, $amount);

            // Query balance after deposit
            $user->wallet->refresh();
            $balanceAfter = (float) $user->wallet->balance;

            // Assert: new balance == previous balance + deposit amount
            $expectedBalance = round($balanceBefore + $amount, 2);

            $this->assertEquals(
                $expectedBalance,
                $balanceAfter,
                sprintf(
                    'Deposit round-trip failed on iteration %d: balance_before=%.2f, deposit=%.2f, expected=%.2f, actual=%.2f',
                    $i + 1,
                    $balanceBefore,
                    $amount,
                    $expectedBalance,
                    $balanceAfter
                )
            );
        }
    }
}
