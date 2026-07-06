<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Wallet;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function createDeposit(Wallet $wallet, string $amount, string $referenceId, ?array $metadata = null): Transaction
    {
        return Transaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => $amount,
            'balance_before' => bcsub((string) $wallet->balance, $amount, 2),
            'balance_after' => $wallet->balance,
            'reference_id' => $referenceId,
            'status' => 'completed',
            'metadata' => $metadata,
        ]);
    }

    public function createTransferPair(Wallet $sender, Wallet $receiver, string $amount, string $referenceId, ?string $note = null): array
    {
        $metadata = $note ? ['note' => $note] : null;

        // Người gửi: transfer_out, trừ tiền
        $txOut = Transaction::create([
            'wallet_id' => $sender->id,
            'type' => 'transfer_out',
            'amount' => '-' . $amount,
            'balance_before' => bcadd((string) $sender->balance, $amount, 2), // trước khi trừ là balance + amount
            'balance_after' => $sender->balance,
            'reference_id' => $referenceId,
            'counterpart_wallet_id' => $receiver->id,
            'status' => 'completed',
            'metadata' => $metadata,
        ]);

        // Người nhận: transfer_in, cộng tiền
        $txIn = Transaction::create([
            'wallet_id' => $receiver->id,
            'type' => 'transfer_in',
            'amount' => $amount,
            'balance_before' => bcsub((string) $receiver->balance, $amount, 2), // trước khi cộng là balance - amount
            'balance_after' => $receiver->balance,
            'reference_id' => $referenceId,
            'counterpart_wallet_id' => $sender->id,
            'status' => 'completed',
            'metadata' => $metadata,
        ]);

        return [$txOut, $txIn];
    }
}
