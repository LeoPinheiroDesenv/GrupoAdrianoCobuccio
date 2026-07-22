<?php

namespace Tests\Feature\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function complete_user_lifecycle_register_login_deposit_transfer_reverse_verify(): void
    {
        // ──────────────────────────────────────────────────────────────
        // Step 1: Register User A
        // ──────────────────────────────────────────────────────────────
        $registerResponseA = $this->postJson('/api/register', [
            'name' => 'User A',
            'email' => 'usera@example.com',
            'cpf' => '12345678901',
            'password' => 'senhasegura123',
            'password_confirmation' => 'senhasegura123',
        ]);

        $registerResponseA->assertStatus(201)
            ->assertJsonStructure(['user', 'token']);

        $tokenA = $registerResponseA->json('token');
        $this->assertNotEmpty($tokenA);

        // ──────────────────────────────────────────────────────────────
        // Step 2: Register User B
        // ──────────────────────────────────────────────────────────────
        $registerResponseB = $this->postJson('/api/register', [
            'name' => 'User B',
            'email' => 'userb@example.com',
            'cpf' => '98765432100',
            'password' => 'senhasegura456',
            'password_confirmation' => 'senhasegura456',
        ]);

        $registerResponseB->assertStatus(201)
            ->assertJsonStructure(['user', 'token']);

        $tokenB = $registerResponseB->json('token');
        $this->assertNotEmpty($tokenB);

        // ──────────────────────────────────────────────────────────────
        // Step 3: Login User A (get a fresh token)
        // ──────────────────────────────────────────────────────────────
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'usera@example.com',
            'password' => 'senhasegura123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure(['token']);

        $tokenA = $loginResponse->json('token');
        $this->assertNotEmpty($tokenA);

        // ──────────────────────────────────────────────────────────────
        // Step 4: Deposit 500 to User A
        // ──────────────────────────────────────────────────────────────
        $depositResponse = $this->postJson('/api/wallet/deposit', [
            'amount' => 500.00,
        ], [
            'Authorization' => "Bearer $tokenA",
        ]);

        $depositResponse->assertStatus(201)
            ->assertJson([
                'type' => 'deposit',
                'amount' => '500.00',
                'balance' => '500.00',
            ]);

        // ──────────────────────────────────────────────────────────────
        // Step 5: Transfer 200 from User A to User B
        // ──────────────────────────────────────────────────────────────
        // Get User B's ID from the database
        $userB = \App\Models\User::where('email', 'userb@example.com')->first();

        $transferResponse = $this->postJson('/api/wallet/transfer', [
            'receiver_id' => $userB->id,
            'amount' => 200.00,
        ], [
            'Authorization' => "Bearer $tokenA",
        ]);

        $transferResponse->assertStatus(201)
            ->assertJson([
                'type' => 'transfer_sent',
                'amount' => '200.00',
                'balance' => '300.00',
            ]);

        $transferTransactionId = \App\Models\Transaction::where('wallet_id', \App\Models\User::where('email', 'usera@example.com')->first()->wallet->id)
            ->where('type', 'transfer_sent')
            ->first()
            ->id;

        // ──────────────────────────────────────────────────────────────
        // Step 6: Check User A balance — should be 300
        // ──────────────────────────────────────────────────────────────
        $balanceResponse = $this->getJson('/api/wallet/balance', [
            'Authorization' => "Bearer $tokenA",
        ]);

        $balanceResponse->assertStatus(200)
            ->assertJson(['balance' => '300.00']);

        // ──────────────────────────────────────────────────────────────
        // Step 7: Check User A statement — 2 transactions (deposit + transfer_sent)
        // ──────────────────────────────────────────────────────────────
        $statementResponse = $this->getJson('/api/wallet/statement', [
            'Authorization' => "Bearer $tokenA",
        ]);

        $statementResponse->assertStatus(200);

        $transactions = $statementResponse->json();
        $this->assertCount(2, $transactions);

        $types = array_column($transactions, 'type');
        $this->assertContains('deposit', $types);
        $this->assertContains('transfer_sent', $types);

        // ──────────────────────────────────────────────────────────────
        // Step 8: Reverse the transfer
        // ──────────────────────────────────────────────────────────────
        $reversalResponse = $this->postJson("/api/wallet/reverse/{$transferTransactionId}", [], [
            'Authorization' => "Bearer $tokenA",
        ]);

        $reversalResponse->assertStatus(201)
            ->assertJson([
                'type' => 'reversal',
                'amount' => '200.00',
            ]);

        // ──────────────────────────────────────────────────────────────
        // Step 9: Check User A final balance — should be 500
        // ──────────────────────────────────────────────────────────────
        $finalBalanceA = $this->getJson('/api/wallet/balance', [
            'Authorization' => "Bearer $tokenA",
        ]);

        $finalBalanceA->assertStatus(200)
            ->assertJson(['balance' => '500.00']);

        // ──────────────────────────────────────────────────────────────
        // Step 10: Check User B final balance — should be 0
        // ──────────────────────────────────────────────────────────────
        $finalBalanceB = $this->getJson('/api/wallet/balance', [
            'Authorization' => "Bearer $tokenB",
        ]);

        $finalBalanceB->assertStatus(200)
            ->assertJson(['balance' => '0.00']);

        // ──────────────────────────────────────────────────────────────
        // Step 11: Verify final state — statements reflect all operations
        // ──────────────────────────────────────────────────────────────

        // User A statement: deposit + transfer_sent + reversal = 3 transactions
        $finalStatementA = $this->getJson('/api/wallet/statement', [
            'Authorization' => "Bearer $tokenA",
        ]);

        $finalStatementA->assertStatus(200);
        $txA = $finalStatementA->json();
        $this->assertCount(3, $txA);

        $typesA = array_column($txA, 'type');
        $this->assertContains('deposit', $typesA);
        $this->assertContains('transfer_sent', $typesA);
        $this->assertContains('reversal', $typesA);

        // User B statement: transfer_received = 1 transaction
        $finalStatementB = $this->getJson('/api/wallet/statement', [
            'Authorization' => "Bearer $tokenB",
        ]);

        $finalStatementB->assertStatus(200);
        $txB = $finalStatementB->json();
        $this->assertCount(1, $txB);
        $this->assertEquals('transfer_received', $txB[0]['type']);

        // Final balance assertions confirmed
        $userA = \App\Models\User::where('email', 'usera@example.com')->first();
        $this->assertEquals('500.00', $userA->wallet->fresh()->balance);
        $this->assertEquals('0.00', $userB->wallet->fresh()->balance);
    }
}
