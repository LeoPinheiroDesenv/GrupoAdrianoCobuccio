<?php

namespace Tests\Feature\Wallet;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferTest extends TestCase
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
    public function it_transfers_successfully_debiting_sender_and_crediting_receiver(): void
    {
        [$sender, $token] = $this->createAuthenticatedUser('sender@example.com', '11122233344', 500.00);
        [$receiver, ] = $this->createAuthenticatedUser('receiver@example.com', '55566677788', 100.00);

        $response = $this->postJson('/api/wallet/transfer', [
            'receiver_id' => $receiver->id,
            'amount' => 200.00,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['uuid', 'type', 'amount', 'balance'])
            ->assertJson([
                'type' => 'transfer_sent',
                'amount' => '200.00',
                'balance' => '300.00',
            ]);

        $this->assertNotEmpty($response->json('uuid'));

        $sender->wallet->refresh();
        $receiver->wallet->refresh();

        $this->assertEquals('300.00', $sender->wallet->balance);
        $this->assertEquals('300.00', $receiver->wallet->balance);
    }

    #[Test]
    public function it_rejects_transfer_with_insufficient_balance(): void
    {
        [$sender, $token] = $this->createAuthenticatedUser('sender@example.com', '11122233344', 50.00);
        [$receiver, ] = $this->createAuthenticatedUser('receiver@example.com', '55566677788', 100.00);

        $response = $this->postJson('/api/wallet/transfer', [
            'receiver_id' => $receiver->id,
            'amount' => 200.00,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(422);

        // Balances should remain unchanged
        $sender->wallet->refresh();
        $receiver->wallet->refresh();

        $this->assertEquals('50.00', $sender->wallet->balance);
        $this->assertEquals('100.00', $receiver->wallet->balance);
    }

    #[Test]
    public function it_rejects_self_transfer(): void
    {
        [$sender, $token] = $this->createAuthenticatedUser('sender@example.com', '11122233344', 500.00);

        $response = $this->postJson('/api/wallet/transfer', [
            'receiver_id' => $sender->id,
            'amount' => 100.00,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(422);

        // Balance should remain unchanged
        $sender->wallet->refresh();
        $this->assertEquals('500.00', $sender->wallet->balance);
    }

    #[Test]
    public function it_rejects_transfer_to_non_existent_user(): void
    {
        [$sender, $token] = $this->createAuthenticatedUser('sender@example.com', '11122233344', 500.00);

        $response = $this->postJson('/api/wallet/transfer', [
            'receiver_id' => 99999,
            'amount' => 100.00,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(422);

        // Balance should remain unchanged
        $sender->wallet->refresh();
        $this->assertEquals('500.00', $sender->wallet->balance);
    }

    #[Test]
    public function it_creates_two_transaction_records_on_transfer(): void
    {
        [$sender, $token] = $this->createAuthenticatedUser('sender@example.com', '11122233344', 500.00);
        [$receiver, ] = $this->createAuthenticatedUser('receiver@example.com', '55566677788', 100.00);

        $response = $this->postJson('/api/wallet/transfer', [
            'receiver_id' => $receiver->id,
            'amount' => 150.00,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(201);

        // Verify sender's transfer_sent transaction
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $sender->wallet->id,
            'target_wallet_id' => $receiver->wallet->id,
            'type' => 'transfer_sent',
            'amount' => '150.00',
        ]);

        // Verify receiver's transfer_received transaction
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $receiver->wallet->id,
            'target_wallet_id' => $sender->wallet->id,
            'type' => 'transfer_received',
            'amount' => '150.00',
        ]);

        // Verify both transactions have UUIDs
        $senderTx = Transaction::where('wallet_id', $sender->wallet->id)
            ->where('type', 'transfer_sent')
            ->first();
        $receiverTx = Transaction::where('wallet_id', $receiver->wallet->id)
            ->where('type', 'transfer_received')
            ->first();

        $this->assertNotNull($senderTx);
        $this->assertNotNull($receiverTx);
        $this->assertNotEmpty($senderTx->uuid);
        $this->assertNotEmpty($receiverTx->uuid);
        $this->assertNotEquals($senderTx->uuid, $receiverTx->uuid);
    }

    #[Test]
    public function it_requires_authentication_for_transfer(): void
    {
        $response = $this->postJson('/api/wallet/transfer', [
            'receiver_id' => 1,
            'amount' => 100.00,
        ]);

        $response->assertStatus(401);
    }
}
