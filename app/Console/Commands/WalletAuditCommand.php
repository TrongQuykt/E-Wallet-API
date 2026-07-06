<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;

class WalletAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:audit';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Quét đối soát số dư của toàn bộ wallets so với tổng doanh số từ các transactions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("Bắt đầu quy trình đối soát số dư ví điện tử...");
        $mismatches = [];
        $totalChecked = 0;

        // Eloquent chunking nhằm tối ưu RAM khi bảng dữ liệu phình to
        Wallet::chunk(100, function ($wallets) use (&$mismatches, &$totalChecked) {
            /** @var Wallet $wallet */
            foreach ($wallets as $wallet) {
                // Tính tổng các giao dịch thành công (completed)
                // deposit(+) có giá trị dương, transfer_out(-) có giá trị âm, transfer_in(+) có giá trị dương
                $txSum = $wallet->transactions()
                    ->where('status', 'completed')
                    ->sum('amount');

                $walletBalance = (string) $wallet->balance;
                $txSumStr = number_format((float) $txSum, 2, '.', '');
                $walletBalanceStr = number_format((float) $walletBalance, 2, '.', '');

                if (bccomp($walletBalanceStr, $txSumStr, 2) !== 0) {
                    $mismatches[] = [
                        'wallet_id' => $wallet->id,
                        'user_id' => $wallet->user_id,
                        'balance' => $walletBalanceStr,
                        'transactions_sum' => $txSumStr,
                        'difference' => bcsub($walletBalanceStr, $txSumStr, 2),
                    ];
                }
                $totalChecked++;
            }
        });

        $this->info("Đã hoàn tất đối soát {$totalChecked} ví.");

        if (count($mismatches) > 0) {
            $this->error("CẢNH BÁO: Phát hiện " . count($mismatches) . " tài khoản bị lệch số dư!");

            $headers = ['Wallet ID', 'User ID', 'Số dư Ví', 'Tổng giao dịch thực tế', 'Chênh lệch'];
            $this->table($headers, $mismatches);

            // Ghi nhận lỗi đối khảo vào daily log
            Log::channel('transactions')->error(json_encode([
                'event' => 'audit_mismatch',
                'mismatches' => $mismatches,
                'total_checked' => $totalChecked,
                'timestamp' => now()->toIso8601String(),
            ]));

            return self::FAILURE;
        }

        $this->info("Thành công: Toàn bộ số dư của các ví trùng khớp hoàn hảo với doanh số lịch sử.");
        return self::SUCCESS;
    }
}
