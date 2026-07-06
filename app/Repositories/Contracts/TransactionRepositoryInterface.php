<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Wallet;
use App\Models\Transaction;

interface TransactionRepositoryInterface
{
    public function createDeposit(Wallet $wallet, string $amount, string $referenceId, ?array $metadata = null): Transaction;

    public function createTransferPair(Wallet $sender, Wallet $receiver, string $amount, string $referenceId, ?string $note = null): array;
}
