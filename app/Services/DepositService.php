<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Exceptions\InvalidChecksumException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepositService
{
    public function __construct(
        protected WalletRepositoryInterface $walletRepo,
        protected TransactionRepositoryInterface $transactionRepo
    ) {
    }

    /**
     * Nhận webhook nạp tiền, xác thực chữ ký signature và cộng số dư ví.
     */
    public function deposit(array $data): Transaction
    {
        $this->verifyChecksum($data);

        return DB::transaction(function () use ($data) {
            $wallet = $this->walletRepo->findForUpdate((int) $data['wallet_id']);

            $amount = (string) $data['amount'];
            $wallet = $this->walletRepo->credit($wallet, $amount);

            $transaction = $this->transactionRepo->createDeposit(
                $wallet,
                $amount,
                $data['reference_id'],
                $data['metadata'] ?? null
            );

            // Ghi log giao dịch dạng JSON phục vụ kiểm toán
            Log::channel('transactions')->info(json_encode([
                'event' => 'deposit',
                'wallet_id' => $wallet->id,
                'reference_id' => $data['reference_id'],
                'amount' => $amount,
                'balance_before' => bcsub((string) $wallet->balance, $amount, 2),
                'balance_after' => (string) $wallet->balance,
                'timestamp' => now()->toIso8601String(),
            ]));

            return $transaction;
        });
    }

    /**
     * Xác thực HMAC-SHA256 kết nối webhook.
     */
    protected function verifyChecksum(array $data): void
    {
        $signature = $data['signature'] ?? '';
        $secret = config('wallet.checksum_secret');

        $payload = sprintf('%s|%s|%s', $data['wallet_id'], $data['amount'], $data['reference_id']);
        $computedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($computedSignature, $signature)) {
            throw new InvalidChecksumException();
        }
    }
}
