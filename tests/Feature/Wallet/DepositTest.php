<?php

namespace Tests\Feature\Wallet;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DepositTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedUser(float $balance = 0): array
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'deposit-test@example.com',
            'cpf' => '11122233344',
            'password' => Hash::make('senhasegura123'),
        ]);

        $user->wallet()->create(['balance' => $balance]);

        $token = auth('api')->login($user);

        return [$user, $token];
    }

    #[Test]
    public function it_deposits_successfully_and_updates_balance(): void
    {
        [$user, $token] = $this->createAuthenticatedUser(100.00);

        $response = $this->postJson('/api/wallet/deposit', [
            'amount' => 50.00,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['uuid', 'type', 'amount', 'balance'])
            ->assertJson([
                'type' => 'deposit',
                'amount' => '50.00',
                'balance' => '150.00',
            ]);

        $this->assertNotEmpty($response->json('uuid'));
    }

    #[Test]
    public function it_rejects_deposit_with_zero_amount(): void
    {
        [$user, $token] = $this->createAuthenticatedUser(100.00);

        $response = $this->postJson('/api/wallet/deposit', [
            'amount' => 0,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_rejects_deposit_with_negative_amount(): void
    {
        [$user, $token] = $this->createAuthenticatedUser(100.00);

        $response = $this->postJson('/api/wallet/deposit', [
            'amount' => -50,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_creates_transaction_record_on_deposit(): void
    {
        [$user, $token] = $this->createAuthenticatedUser(0);

        $response = $this->postJson('/api/wallet/deposit', [
            'amount' => 75.50,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(201);

        $wallet = $user->wallet;

        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => '75.50',
        ]);

        $transaction = Transaction::where('wallet_id', $wallet->id)->first();
        $this->assertNotNull($transaction);
        $this->assertNotEmpty($transaction->uuid);
        $this->assertEquals('deposit', $transaction->type);
        $this->assertEquals('75.50', $transaction->amount);
    }

    #[Test]
    public function it_requires_authentication_for_deposit(): void
    {
        $response = $this->postJson('/api/wallet/deposit', [
            'amount' => 100,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_handles_deposit_on_negative_balance(): void
    {
        [$user, $token] = $this->createAuthenticatedUser(-50.00);

        $response = $this->postJson('/api/wallet/deposit', [
            'amount' => 100.00,
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'balance' => '50.00',
            ]);

        $user->wallet->refresh();
        $this->assertEquals('50.00', $user->wallet->balance);
    }
}
