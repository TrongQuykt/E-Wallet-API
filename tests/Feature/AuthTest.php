<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_wallet_successfully(): void
    {
        $payload = [
            'name' => 'Nguyễn Văn A',
            'email' => 'vana@gmail.com',
            'phone' => '0987654321',
            'password' => 'Secret123@',
            'password_confirmation' => 'Secret123@',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'wallet' => [
                        'id',
                        'balance',
                        'currency'
                    ]
                ]
            ]);

        $this->assertDatabaseHas('users', ['email' => 'vana@gmail.com']);
        $this->assertDatabaseHas('wallets', [
            'balance' => '0.00',
            'currency' => 'VND'
        ]);
    }

    public function test_login_returns_token_successfully(): void
    {
        $user = User::factory()->create([
            'email' => 'vana@gmail.com',
            'password' => 'Secret123@',
        ]);
        $user->wallet()->create(['balance' => '0.00']);

        $payload = [
            'email' => 'vana@gmail.com',
            'password' => 'Secret123@',
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'token']);
    }

    public function test_login_lockout_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'vana@gmail.com',
            'password' => 'Secret123@',
        ]);

        $payload = [
            'email' => $user->email,
            'password' => 'wrong-password',
        ];

        // Gửi 5 request sai liên tụ
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/auth/login', $payload);
            $response->assertStatus(422);
        }

        // Lần thứ 6 sẽ ném lockout error
        $response = $this->postJson('/api/v1/auth/login', $payload);
        $response->assertStatus(422);

        $message = $response->json('message') ?? $response->json('errors.email.0');
        $this->assertStringContainsString('Tài khoản bị khóa', $message);
    }
}
