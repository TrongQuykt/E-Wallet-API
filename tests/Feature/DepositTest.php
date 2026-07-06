<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class DepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_with_valid_checksum_success(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallet()->create([
            'balance' => '1000.00',
            'currency' => 'VND'
        ]);

        $walletId = $wallet->id;
        $amount = '500000.00';
        $referenceId = 'dep_tx_90123';
        $secret = config('wallet.checksum_secret');

        // Tạo signature chuẩn HMAC-SHA256: wallet_id|amount|reference_id
        $payload = sprintf('%s|%s|%s', $walletId, $amount, $referenceId);
        $signature = hash_hmac('sha256', $payload, $secret);

        $response = $this->postJson('/api/v1/wallet/deposit', [
            'wallet_id' => $walletId,
            'amount' => $amount,
            'reference_id' => $referenceId,
            'signature' => $signature,
            'metadata' => ['bank' => 'Vietcombank']
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.balance_after', '501000.00');

        $this->assertDatabaseHas('wallets', [
            'id' => $walletId,
            'balance' => '501000.00'
        ]);

        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $walletId,
            'type' => 'deposit',
            'amount' => '500000.00',
            'balance_before' => '1000.00',
            'balance_after' => '501000.00',
            'reference_id' => $referenceId,
        ]);
    }

    public function test_deposit_with_invalid_checksum_fails_400(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallet()->create([
            'balance' => '1000.00',
            'currency' => 'VND'
        ]);

        $response = $this->postJson('/api/v1/wallet/deposit', [
            'wallet_id' => $wallet->id,
            'amount' => '500000.00',
            'reference_id' => 'dep_tx_90123',
            'signature' => 'invalid-hmac-checksum-signature',
        ]);

        // Trả về lỗi Validation / Middleware Error
        $response->assertStatus(400);

        $this->assertDatabaseHas('wallets', [
            'id' => $wallet->id,
            'balance' => '1000.00'
        ]);
    }
}
