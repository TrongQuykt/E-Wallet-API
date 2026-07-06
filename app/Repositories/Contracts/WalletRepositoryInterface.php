<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Wallet;

interface WalletRepositoryInterface
{
    public function find(int $id): Wallet;

    public function findForUpdate(int $id): Wallet;

    public function findByUserId(int $userId): Wallet;

    public function credit(Wallet $wallet, string $amount): Wallet;

    public function debit(Wallet $wallet, string $amount): Wallet;
}
