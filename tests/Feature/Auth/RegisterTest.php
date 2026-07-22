<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_registers_a_user_with_valid_data(): void
    {
        $payload = [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'cpf' => '12345678901',
            'password' => 'senhasegura123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['name', 'email', 'cpf'],
                'token',
            ])
            ->assertJson([
                'user' => [
                    'name' => 'João Silva',
                    'email' => 'joao@example.com',
                    'cpf' => '12345678901',
                ],
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function it_creates_a_wallet_with_zero_balance_on_registration(): void
    {
        $payload = [
            'name' => 'Maria Souza',
            'email' => 'maria@example.com',
            'cpf' => '98765432100',
            'password' => 'senhasegura123',
        ];

        $this->postJson('/api/register', $payload)->assertStatus(201);

        $user = User::where('email', 'maria@example.com')->first();
        $this->assertNotNull($user->wallet);
        $this->assertEquals('0.00', $user->wallet->balance);
    }

    #[Test]
    public function it_hashes_the_password_with_bcrypt(): void
    {
        $payload = [
            'name' => 'Carlos Lima',
            'email' => 'carlos@example.com',
            'cpf' => '11122233344',
            'password' => 'senhasegura123',
        ];

        $this->postJson('/api/register', $payload)->assertStatus(201);

        $user = User::where('email', 'carlos@example.com')->first();
        $this->assertNotEquals('senhasegura123', $user->password);
        $this->assertTrue(password_verify('senhasegura123', $user->password));
    }

    #[Test]
    public function it_rejects_duplicate_email(): void
    {
        User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'cpf' => '11111111111',
            'password' => bcrypt('password123'),
        ]);

        $payload = [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'cpf' => '22222222222',
            'password' => 'senhasegura123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_rejects_duplicate_cpf(): void
    {
        User::create([
            'name' => 'Existing User',
            'email' => 'first@example.com',
            'cpf' => '12345678901',
            'password' => bcrypt('password123'),
        ]);

        $payload = [
            'name' => 'New User',
            'email' => 'second@example.com',
            'cpf' => '12345678901',
            'password' => 'senhasegura123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cpf']);
    }

    #[Test]
    public function it_rejects_cpf_with_non_digit_characters(): void
    {
        $payload = [
            'name' => 'Ana Santos',
            'email' => 'ana@example.com',
            'cpf' => '123.456.789',
            'password' => 'senhasegura123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cpf']);
    }

    #[Test]
    public function it_rejects_cpf_with_wrong_length(): void
    {
        $payload = [
            'name' => 'Pedro Costa',
            'email' => 'pedro@example.com',
            'cpf' => '123456789',
            'password' => 'senhasegura123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cpf']);
    }

    #[Test]
    public function it_rejects_password_shorter_than_8_characters(): void
    {
        $payload = [
            'name' => 'Lucas Almeida',
            'email' => 'lucas@example.com',
            'cpf' => '99988877766',
            'password' => 'short',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function it_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'cpf', 'password']);
    }

    #[Test]
    public function it_rejects_invalid_email_format(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'cpf' => '12345678901',
            'password' => 'senhasegura123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
