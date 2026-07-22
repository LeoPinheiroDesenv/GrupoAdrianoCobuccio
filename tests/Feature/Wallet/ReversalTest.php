<?php

namespace Tests\Feature\Wallet;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReversalTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedUser(string $email, string $cpf, float $balance = 0): array
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => $email,
            'cpf' => $cpf,
            'password' => Hash::make('senhasegura123'),
        ]);

        $user->wallet()->create(['balance' => $balance]);

        $token = auth('api')->login($user);

        return [$user, $token];
    }

    #[Test]
    public function it_reverses_deposit_and_subtracts_from_balance(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('user@example.com', '11122233344', 0);

        // Deposit 100
        $depositResponse = $this->postJson('/api/wallet/deposit', [
            'amount' => 100.00,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $depositResponse->assertStatus(201);
        $depositTransaction = Transaction::where('wallet_id', $user->wallet->id)
            ->where('type', 'deposit')
            ->first();

        // Reverse the deposit
        $response = $this->postJson("/api/wallet/reverse/{$depositTransaction->id}", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['uuid', 'type', 'amount', 'balance'])
            ->assertJson([
                'type' => 'reversal',
                'amount' => '100.00',
                'balance' => '0.00',
            ]);

        $user->wallet->refresh();
        $this->assertEquals('0.00', $user->wallet->balance);
    }

    #[Test]
    public function it_reverses_transfer_and_restores_both_wallets(): void
    {
        [$sender, $senderToken] = $this->createAuthenticatedUser('sender@example.com', '11122233344', 500.00);
        [$receiver, ] = $this->createAuthenticatedUser('receiver@example.com', '55566677788', 100.00);

        // Transfer 200 from sender to receiver
        $transferResponse = $this->postJson('/api/wallet/transfer', [
            'receiver_id' => $receiver->id,
            'amount' => 200.00,
        ], [
            'Authorization' => "Bearer $senderToken",
        ]);

        $transferResponse->assertStatus(201);

        $senderTransaction = Transaction::where('wallet_id', $sender->wallet->id)
            ->where('type', 'transfer_sent')
            ->first();

        // Reverse the transfer
        $response = $this->postJson("/api/wallet/reverse/{$senderTransaction->id}", [], [
            'Authorization' => "Bearer $senderToken",
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'type' => 'reversal',
                'amount' => '200.00',
                'balance' => '500.00',
            ]);

        $sender->wallet->refresh();
        $receiver->wallet->refresh();

        // Sender gets 200 back (300 + 200 = 500)
        $this->assertEquals('500.00', $sender->wallet->balance);
        // Receiver loses 200 (300 - 200 = 100)
        $this->assertEquals('100.00', $receiver->wallet->balance);
    }

    #[Test]
    public function it_rejects_double_reversal(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('user@example.com', '11122233344', 0);

        // Deposit 100
        $this->postJson('/api/wallet/deposit', [
            'amount' => 100.00,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $depositTransaction = Transaction::where('wallet_id', $user->wallet->id)
            ->where('type', 'deposit')
            ->first();

        // First reversal should succeed
        $firstReversal = $this->postJson("/api/wallet/reverse/{$depositTransaction->id}", [], [
            'Authorization' => "Bearer $token",
        ]);

        $firstReversal->assertStatus(201);

        // Second reversal should be rejected
        $secondReversal = $this->postJson("/api/wallet/reverse/{$depositTransaction->id}", [], [
            'Authorization' => "Bearer $token",
        ]);

        $secondReversal->assertStatus(422);
    }

    #[Test]
    public function it_allows_negative_balance_after_reversal(): void
    {
        // Scenario: Deposit 100, transfer 80, reverse the deposit → balance = -80
        [$userA, $tokenA] = $this->createAuthenticatedUser('usera@example.com', '99988877766', 0);
        [$userB, ] = $this->createAuthenticatedUser('userb@example.com', '44455566677', 0);

        // Deposit 100 to user A
        $this->postJson('/api/wallet/deposit', [
            'amount' => 100.00,
        ], [
            'Authorization' => "Bearer $tokenA",
        ]);

        $userA->wallet->refresh();
        $this->assertEquals('100.00', $userA->wallet->balance);

        // Transfer 80 from user A to user B
        $this->postJson('/api/wallet/transfer', [
            'receiver_id' => $userB->id,
            'amount' => 80.00,
        ], [
            'Authorization' => "Bearer $tokenA",
        ]);

        $userA->wallet->refresh();
        $this->assertEquals('20.00', $userA->wallet->balance);

        // Reverse the deposit (should allow negative balance)
        $depositTransaction = Transaction::where('wallet_id', $userA->wallet->id)
            ->where('type', 'deposit')
            ->first();

        $response = $this->postJson("/api/wallet/reverse/{$depositTransaction->id}", [], [
            'Authorization' => "Bearer $tokenA",
        ]);

        $response->assertStatus(201);

        $userA->wallet->refresh();
        // Balance should be 20 - 100 = -80
        $this->assertEquals('-80.00', $userA->wallet->balance);
    }

    #[Test]
    public function it_rejects_reversal_of_transaction_belonging_to_another_user(): void
    {
        [$userA, $tokenA] = $this->createAuthenticatedUser('usera@example.com', '11122233344', 0);
        [$userB, $tokenB] = $this->createAuthenticatedUser('userb@example.com', '55566677788', 0);

        // Deposit 100 to user A
        $this->postJson('/api/wallet/deposit', [
            'amount' => 100.00,
        ], [
            'Authorization' => "Bearer $tokenA",
        ]);

        $depositTransaction = Transaction::where('wallet_id', $userA->wallet->id)
            ->where('type', 'deposit')
            ->first();

        // User B tries to reverse user A's transaction
        $response = $this->postJson("/api/wallet/reverse/{$depositTransaction->id}", [], [
            'Authorization' => "Bearer $tokenB",
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_returns_404_for_non_existent_transaction(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('user@example.com', '11122233344', 0);

        $response = $this->postJson('/api/wallet/reverse/99999', [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function it_requires_authentication_for_reversal(): void
    {
        $response = $this->postJson('/api/wallet/reverse/1', []);

        $response->assertStatus(401);
    }
}
