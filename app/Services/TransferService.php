<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Wallet;
use Illuminate\Validation\ValidationException;

class TransferService
{
    public function __construct(
        protected WalletRepositoryInterface $walletRepo,
        protected TransactionRepositoryInterface $transactionRepo
    ) {
    }

    /**
     * Thực hiện chuyển tiền an toàn giữa 2 ví.
     * Áp dụng DB::transaction và lockForUpdate, sinh reference_id UUID đồng bộ.
     * Khóa dòng theo thứ tự ID tăng dần để ngăn ngừa deadlock.
     */
    public function transfer(int $senderWalletId, int $receiverWalletId, string $amount, ?string $note = null): array
    {
        if ($senderWalletId === $receiverWalletId) {
            throw ValidationException::withMessages([
                'receiver_wallet_id' => ['Không thể chuyển khoản đến chính ví của bạn.'],
            ]);
        }

        $maxTransfer = config('wallet.max_transfer');
        if (bccomp($amount, (string) $maxTransfer, 2) === 1) {
            throw ValidationException::withMessages([
                'amount' => ["Số tiền chuyển vượt quá giới hạn tối đa cho phép (" . number_format($maxTransfer) . " VND)."],
            ]);
        }

        $referenceId = Str::uuid()->toString();

        return DB::transaction(function () use ($senderWalletId, $receiverWalletId, $amount, $referenceId, $note) {
            // Ngăn ngừa Deadlock: Khóa các ví theo thứ tự ID tăng dần của ví
            $firstId = min($senderWalletId, $receiverWalletId);
            $secondId = max($senderWalletId, $receiverWalletId);

            $wallets = [];
            $wallets[$firstId] = $this->walletRepo->findForUpdate($firstId);
            $wallets[$secondId] = $this->walletRepo->findForUpdate($secondId);

            /** @var Wallet $sender */
            $sender = $wallets[$senderWalletId];
            /** @var Wallet $receiver */
            $receiver = $wallets[$receiverWalletId];

            if (!$sender->is_active) {
                throw ValidationException::withMessages(['sender' => ['Ví gửi đang ở trạng thái khoá.']]);
            }
            if (!$receiver->is_active) {
                throw ValidationException::withMessages(['receiver' => ['Ví nhận đang ở trạng thái khoá.']]);
            }

            // Ghi nợ ví gửi
            $sender = $this->walletRepo->debit($sender, $amount);

            // Ghi có ví nhận
            $receiver = $this->walletRepo->credit($receiver, $amount);

            // Ghi nhận cặp giao dịch
            $transactions = $this->transactionRepo->createTransferPair($sender, $receiver, $amount, $referenceId, $note);

            // Ghi log giao dịch JSON
            Log::channel('transactions')->info(json_encode([
                'event' => 'transfer',
                'reference_id' => $referenceId,
                'sender_wallet_id' => $senderWalletId,
                'receiver_wallet_id' => $receiverWalletId,
                'amount' => $amount,
                'timestamp' => now()->toIso8601String(),
            ]));

            return [
                'sender' => $sender,
                'receiver' => $receiver,
                'reference_id' => $referenceId,
                'transactions' => $transactions,
            ];
        });
    }
}
