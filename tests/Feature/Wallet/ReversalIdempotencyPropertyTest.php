<?php

namespace Tests\Feature\Wallet;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based test for reversal idempotency.
 *
 * **Validates: Requirements 6.5, 9.5**
 *
 * Property 3: Reversal Idempotency — For all reversed transactions,
 * attempting a second reversal of the same transaction SHALL be rejected.
 *
 * FOR ALL transactions T that have been reversed:
 *   reverse(T) SHALL throw ValidationException with "already been reversed" message
 */
class ReversalIdempotencyPropertyTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = new WalletService();
    }

    /**
     * Create a user with a wallet and initial deposit.
     */
    private function createUserWithBalance(string $suffix, float $balance): User
    {
        $user = User::create([
            'name' => "User {$suffix}",
            'email' => "user-{$suffix}@example.com",
            'cpf' => str_pad((string) (10000000000 + crc32($suffix) % 89999999999), 11, '0', STR_PAD_LEFT),
            'password' => Hash::make('password123'),
        ]);

        $user->wallet()->create(['balance' => $balance]);

        return $user;
    }

    /**
     * Generate a random valid amount between 0.01 and max.
     */
    private function generateRandomAmount(float $max = 10000.00): float
    {
        $maxCents = (int) ($max * 100);
        $cents = random_int(1, max(1, $maxCents));
        return round($cents / 100, 2);
    }

    /**
     * Property 3: Reversal Idempotency — Deposit reversals
     *
     * FOR ALL deposit transactions T that have been reversed:
     *   reverse(T) SHALL throw ValidationException
     *
     * **Validates: Requirements 6.5, 9.5**
     */
    #[Test]
    public function reversal_idempotency_property_holds_for_deposit_reversals(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $user = $this->createUserWithBalance("deposit-{$i}", 100000.00);

            $amount = $this->generateRandomAmount(5000.00);

            // Make a deposit
            $transaction = $this->walletService->deposit($user, $amount);

            // First reversal should succeed
            $reversalTransaction = $this->walletService->reverse($user, $transaction);
            $this->assertNotNull($reversalTransaction);

            // Refresh transaction to get updated is_reversed state
            $transaction->refresh();

            // Second reversal of the same transaction MUST be rejected
            $rejected = false;
            try {
                $this->walletService->reverse($user, $transaction);
            } catch (ValidationException $e) {
                $rejected = true;
                $this->assertStringContainsString(
                    'already been reversed',
                    $e->getMessage(),
                    sprintf(
                        'Iteration %d: Expected "already been reversed" message, got: %s',
                        $i + 1,
                        $e->getMessage()
                    )
                );
            }

            $this->assertTrue(
                $rejected,
                sprintf(
                    'Reversal idempotency violated on iteration %d: '
                    . 'second reversal of deposit (amount=%.2f) was NOT rejected',
                    $i + 1,
                    $amount
                )
            );
        }
    }

    /**
     * Property 3: Reversal Idempotency — Transfer reversals
     *
     * FOR ALL transfer transactions T that have been reversed:
     *   reverse(T) SHALL throw ValidationException
     *
     * **Validates: Requirements 6.5, 9.5**
     */
    #[Test]
    public function reversal_idempotency_property_holds_for_transfer_reversals(): void
    {
        $iterations = 20;

        for ($i = 0; $i < $iterations; $i++) {
            $sender = $this->createUserWithBalance("sender-{$i}", 100000.00);
            $receiver = $this->createUserWithBalance("receiver-{$i}", 1000.00);

            $amount = $this->generateRandomAmount(5000.00);

            // Make a transfer
            $transaction = $this->walletService->transfer($sender, $receiver, $amount);

            // First reversal should succeed
            $reversalTransaction = $this->walletService->reverse($sender, $transaction);
            $this->assertNotNull($reversalTransaction);

            // Refresh transaction to get updated is_reversed state
            $transaction->refresh();

            // Second reversal of the same transaction MUST be rejected
            $rejected = false;
            try {
                $this->walletService->reverse($sender, $transaction);
            } catch (ValidationException $e) {
                $rejected = true;
                $this->assertStringContainsString(
                    'already been reversed',
                    $e->getMessage(),
                    sprintf(
                        'Iteration %d: Expected "already been reversed" message, got: %s',
                        $i + 1,
                        $e->getMessage()
                    )
                );
            }

            $this->assertTrue(
                $rejected,
                sprintf(
                    'Reversal idempotency violated on iteration %d: '
                    . 'second reversal of transfer (amount=%.2f) was NOT rejected',
                    $i + 1,
                    $amount
                )
            );
        }
    }
}
