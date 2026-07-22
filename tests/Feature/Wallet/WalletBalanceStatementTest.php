<?php

namespace Tests\Feature\Wallet;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WalletBalanceStatementTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedUser(float $balance = 0): array
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'cpf' => '12345678901',
            'password' => Hash::make('senhasegura123'),
        ]);

        $user->wallet()->create(['balance' => $balance]);

        $token = auth('api')->login($user);

        return [$user, $token];
    }

    #[Test]
    public function it_returns_wallet_balance_for_authenticated_user(): void
    {
        [$user, $token] = $this->createAuthenticatedUser(150.75);

        $response = $this->getJson('/api/wallet/balance', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJson(['balance' => '150.75']);
    }

    #[Test]
    public function it_returns_zero_balance_for_new_wallet(): void
    {
        [$user, $token] = $this->createAuthenticatedUser(0);

        $response = $this->getJson('/api/wallet/balance', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJson(['balance' => '0.00']);
    }

    #[Test]
    public function it_requires_authentication_for_balance(): void
    {
        $response = $this->getJson('/api/wallet/balance');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_returns_statement_ordered_by_date_desc(): void
    {
        [$user, $token] = $this->createAuthenticatedUser(500.00);

        $wallet = $user->wallet;

        // Create transactions with different dates using query builder to bypass model events
        $tx1Uuid = Str::uuid()->toString();
        $tx2Uuid = Str::uuid()->toString();
        $tx3Uuid = Str::uuid()->toString();

        $tx1 = new Transaction([
            'uuid' => $tx1Uuid,
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => 100.00,
            'is_reversed' => false,
        ]);
        $tx1->created_at = now()->subDays(2);
        $tx1->updated_at = now()->subDays(2);
        $tx1->save();

        $tx2 = new Transaction([
            'uuid' => $tx2Uuid,
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => 200.00,
            'is_reversed' => false,
        ]);
        $tx2->created_at = now()->subDay();
        $tx2->updated_at = now()->subDay();
        $tx2->save();

        $tx3 = new Transaction([
            'uuid' => $tx3Uuid,
            'wallet_id' => $wallet->id,
            'type' => 'transfer_sent',
            'amount' => 50.00,
            'is_reversed' => false,
        ]);
        $tx3->created_at = now();
        $tx3->updated_at = now();
        $tx3->save();

        $response = $this->getJson('/api/wallet/statement', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(3, $data);

        // Most recent first
        $this->assertEquals($tx3Uuid, $data[0]['uuid']);
        $this->assertEquals($tx2Uuid, $data[1]['uuid']);
        $this->assertEquals($tx1Uuid, $data[2]['uuid']);
    }

    #[Test]
    public function it_returns_correct_transaction_fields_in_statement(): void
    {
        [$user, $token] = $this->createAuthenticatedUser(100.00);

        $wallet = $user->wallet;

        Transaction::create([
            'uuid' => 'test-uuid-123',
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => 100.00,
            'is_reversed' => false,
        ]);

        $response = $this->getJson('/api/wallet/statement', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['uuid', 'type', 'amount', 'date', 'is_reversed'],
            ]);

        $data = $response->json();
        $this->assertEquals('test-uuid-123', $data[0]['uuid']);
        $this->assertEquals('deposit', $data[0]['type']);
        $this->assertEquals('100.00', $data[0]['amount']);
        $this->assertFalse($data[0]['is_reversed']);
        $this->assertNotEmpty($data[0]['date']);
    }

    #[Test]
    public function it_returns_empty_statement_for_wallet_with_no_transactions(): void
    {
        [$user, $token] = $this->createAuthenticatedUser(0);

        $response = $this->getJson('/api/wallet/statement', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJson([]);
    }

    #[Test]
    public function it_requires_authentication_for_statement(): void
    {
        $response = $this->getJson('/api/wallet/statement');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_shows_reversed_status_in_statement(): void
    {
        [$user, $token] = $this->createAuthenticatedUser(100.00);

        $wallet = $user->wallet;

        Transaction::create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => 100.00,
            'is_reversed' => true,
        ]);

        $response = $this->getJson('/api/wallet/statement', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data[0]['is_reversed']);
    }
}
