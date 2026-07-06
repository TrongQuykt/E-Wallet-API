<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Wallet;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Exceptions\InsufficientBalanceException;

class WalletRepository implements WalletRepositoryInterface
{
    public function find(int $id): Wallet
    {
        return Wallet::findOrFail($id);
    }

    public function findForUpdate(int $id): Wallet
    {
        return Wallet::lockForUpdate()->findOrFail($id);
    }

    public function findByUserId(int $userId): Wallet
    {
        return Wallet::where('user_id', $userId)->firstOrFail();
    }

    public function credit(Wallet $wallet, string $amount): Wallet
    {
        // Đảm bảo tính toán chính xác số dư thập phân với BCMath
        $newBalance = bcadd((string) $wallet->balance, $amount, 2);
        $wallet->update(['balance' => $newBalance]);

        return $wallet->fresh();
    }

    public function debit(Wallet $wallet, string $amount): Wallet
    {
        // So sánh balance & amount
        if (bccomp((string) $wallet->balance, $amount, 2) === -1) {
            throw new InsufficientBalanceException();
        }

        $newBalance = bcsub((string) $wallet->balance, $amount, 2);
        $wallet->update(['balance' => $newBalance]);

        return $wallet->fresh();
    }
}
