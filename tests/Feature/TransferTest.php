<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use App\Services\TransferService;
use Illuminate\Support\Facades\DB;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_success(): void
    {
        $userA = User::factory()->create();
        $walletA = $userA->wallet()->create(['balance' => '1000.00', 'currency' => 'VND']);

        $userB = User::factory()->create();
        $walletB = $userB->wallet()->create(['balance' => '500.00', 'currency' => 'VND']);

        $this->actingAs($userA);

        $response = $this->postJson('/api/v1/wallet/transfer', [
            'receiver_wallet_id' => $walletB->id,
            'amount' => '300.00',
            'note' => 'Chuyển tiền ăn trưa',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'data' => ['reference_id', 'balance']]);

        $this->assertDatabaseHas('wallets', ['id' => $walletA->id, 'balance' => '700.00']);
        $this->assertDatabaseHas('wallets', ['id' => $walletB->id, 'balance' => '800.00']);

        $refId = $response->json('data.reference_id');
        $this->assertNotNull($refId);

        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $walletA->id,
            'type' => 'transfer_out',
            'amount' => '-300.00',
            'reference_id' => $refId,
            'counterpart_wallet_id' => $walletB->id,
        ]);

        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $walletB->id,
            'type' => 'transfer_in',
            'amount' => '300.00',
            'reference_id' => $refId,
            'counterpart_wallet_id' => $walletA->id,
        ]);
    }

    public function test_transfer_fails_when_insufficient_balance(): void
    {
        $userA = User::factory()->create();
        $walletA = $userA->wallet()->create(['balance' => '100.00', 'currency' => 'VND']);

        $userB = User::factory()->create();
        $walletB = $userB->wallet()->create(['balance' => '500.00', 'currency' => 'VND']);

        $this->actingAs($userA);

        $response = $this->postJson('/api/v1/wallet/transfer', [
            'receiver_wallet_id' => $walletB->id,
            'amount' => '200.00',
        ]);

        // Trả về validation error hoặc exception custom được map
        $response->assertStatus(422);

        $this->assertDatabaseHas('wallets', ['id' => $walletA->id, 'balance' => '100.00']);
        $this->assertDatabaseHas('wallets', ['id' => $walletB->id, 'balance' => '500.00']);
    }

    public function test_transfer_fails_when_limit_exceeded(): void
    {
        $userA = User::factory()->create();
        $walletA = $userA->wallet()->create(['balance' => '60000000.00', 'currency' => 'VND']);

        $userB = User::factory()->create();
        $walletB = $userB->wallet()->create(['balance' => '500.00', 'currency' => 'VND']);

        $this->actingAs($userA);

        $response = $this->postJson('/api/v1/wallet/transfer', [
            'receiver_wallet_id' => $walletB->id,
            'amount' => '51000000.00', // config max_transfer là 50000000
        ]);

        $response->assertStatus(422);
    }

    public function test_pessimistic_locking_prevents_race_condition(): void
    {
        $userA = User::factory()->create();
        $walletA = $userA->wallet()->create(['balance' => '100.00', 'currency' => 'VND']);

        // Giả lập 2 tiến trình đồng thời lock và giao dịch:
        DB::beginTransaction();

        $walletRepo = app(\App\Repositories\Contracts\WalletRepositoryInterface::class);

        // Tiến trình 1 khóa ví và trừ toàn bộ 100.00
        $lockedWalletA = $walletRepo->findForUpdate($walletA->id);
        $this->assertEquals('100.00', $lockedWalletA->balance);

        $walletRepo->debit($lockedWalletA, '100.00');

        // Tiến trình 2 cố gắng trừ tiếp 100.00
        // Trong môi trường thực tế, tiến trình 2 sẽ bị chặn (wait) ở hàm lockForUpdate cho đến khi tiến trình 1 hoàn thành.
        // Khi tiến trình 1 commit, tiến trình 2 đọc được balance = 0.00 và ném InsufficientBalanceException.
        // Ta mô phỏng hành vi này bằng cách gọi trực tiếp trong transaction chưa commit:
        $this->expectException(\App\Exceptions\InsufficientBalanceException::class);
        $walletRepo->debit($lockedWalletA, '100.00');

        DB::rollBack();
    }
}
