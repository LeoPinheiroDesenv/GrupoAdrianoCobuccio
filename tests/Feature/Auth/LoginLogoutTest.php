<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginLogoutTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'cpf' => '12345678901',
            'password' => Hash::make('senhasegura123'),
        ], $overrides));

        $user->wallet()->create(['balance' => 0]);

        return $user;
    }

    #[Test]
    public function it_logs_in_with_valid_credentials(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'senhasegura123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token']);

        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function it_returns_generic_error_on_invalid_password(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Credenciais inválidas']);

        // Should not reveal which field is wrong
        $this->assertStringNotContainsString('senha', $response->json('error'));
        $this->assertStringNotContainsString('password', $response->json('error'));
    }

    #[Test]
    public function it_returns_generic_error_on_invalid_email(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'senhasegura123',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Credenciais inválidas']);

        // Should not reveal which field is wrong
        $this->assertStringNotContainsString('email', strtolower($response->json('error')));
    }

    #[Test]
    public function it_validates_email_is_required(): void
    {
        $response = $this->postJson('/api/login', [
            'password' => 'senhasegura123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_validates_password_is_required(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function it_validates_email_format(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'not-an-email',
            'password' => 'senhasegura123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_logs_out_and_invalidates_token(): void
    {
        $this->createUser();

        // Login first
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'senhasegura123',
        ]);

        $token = $loginResponse->json('token');

        // Logout
        $response = $this->postJson('/api/logout', [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logout realizado com sucesso']);

        // Token should be invalidated - accessing protected route should fail
        $protectedResponse = $this->postJson('/api/logout', [], [
            'Authorization' => "Bearer $token",
        ]);

        $protectedResponse->assertStatus(401);
    }

    #[Test]
    public function it_requires_authentication_for_logout(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_returns_401_when_accessing_protected_wallet_route_without_token(): void
    {
        $response = $this->getJson('/api/wallet/balance');

        $response->assertStatus(401);
    }
}
