<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\DepositRequest;
use App\Http\Requests\Wallet\TransferRequest;
use App\Services\DepositService;
use App\Services\TransferService;
use App\Repositories\Contracts\WalletRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class WalletController extends Controller
{
    public function __construct(
        protected DepositService $depositService,
        protected TransferService $transferService,
        protected WalletRepositoryInterface $walletRepo
    ) {
    }

    #[OA\Get(
        path: "/api/v1/wallet/balance",
        operationId: "getWalletBalance",
        summary: "Kiểm tra số dư ví hiện tại",
        description: "Trả về thông tin ví của người dùng đang được đăng nhập gồm số dư và tiền tệ.",
        security: [["bearerAuth" => []]],
        tags: ["Wallet"]
    )]
    #[OA\Response(
        response: 200,
        description: "Thành công",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "success"),
                new OA\Property(
                    property: "data",
                    type: "object",
                    properties: [
                        new OA\Property(property: "wallet_id", type: "integer", example: 1),
                        new OA\Property(property: "balance", type: "string", example: "1500000.00"),
                        new OA\Property(property: "currency", type: "string", example: "VND")
                    ]
                )
            ]
        )
    )]
    public function balance(Request $request): JsonResponse
    {
        $wallet = $this->walletRepo->findByUserId((int) $request->user()->id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'wallet_id' => $wallet->id,
                'balance' => $wallet->balance,
                'currency' => $wallet->currency,
            ]
        ]);
    }

    #[OA\Post(
        path: "/api/v1/wallet/deposit",
        operationId: "depositWallet",
        summary: "Webhook nhận nạp tiền",
        description: "API Webhook dành cho các cổng thanh toán bên thứ ba gửi IPN để cộng tiền vào tài khoản ví sau khi thanh toán thành công.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["wallet_id", "amount", "reference_id", "signature"],
                properties: [
                    new OA\Property(property: "wallet_id", type: "integer", example: 1),
                    new OA\Property(property: "amount", type: "number", format: "float", example: 500000),
                    new OA\Property(property: "reference_id", type: "string", example: "dep_ref_982347923"),
                    new OA\Property(property: "signature", type: "string", example: "9e107d9d372bb6826bd81..."),
                    new OA\Property(property: "metadata", type: "object")
                ]
            )
        ),
        tags: ["Wallet"]
    )]
    #[OA\Response(
        response: 200,
        description: "Nạp tiền thành công",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "success"),
                new OA\Property(property: "message", type: "string", example: "Nạp tiền vào tài khoản thành công."),
                new OA\Property(
                    property: "data",
                    type: "object",
                    properties: [
                        new OA\Property(property: "transaction_id", type: "integer", example: 12),
                        new OA\Property(property: "balance_after", type: "string", example: "500000.00"),
                        new OA\Property(property: "reference_id", type: "string", example: "dep_ref_982347923")
                    ]
                )
            ]
        )
    )]
    public function deposit(DepositRequest $request): JsonResponse
    {
        $transaction = $this->depositService->deposit($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Nạp tiền vào tài khoản thành công.',
            'data' => [
                'transaction_id' => $transaction->id,
                'balance_after' => $transaction->balance_after,
                'reference_id' => $transaction->reference_id,
            ]
        ]);
    }

    #[OA\Post(
        path: "/api/v1/wallet/transfer",
        operationId: "transferMoney",
        summary: "Chuyển tiền giữa hai ví",
        description: "Trừ số dư ví A (người chuyển hiện tại), cộng số dư ví B, ghi nhận 2 giao dịch transfer_out và transfer_in.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["receiver_wallet_id", "amount"],
                properties: [
                    new OA\Property(property: "receiver_wallet_id", type: "integer", example: 2),
                    new OA\Property(property: "amount", type: "number", format: "float", example: 50000),
                    new OA\Property(property: "note", type: "string", example: "Trả tiền ăn trưa")
                ]
            )
        ),
        tags: ["Wallet"]
    )]
    #[OA\Response(
        response: 200,
        description: "Chuyển tiền thành công",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "success"),
                new OA\Property(property: "message", type: "string", example: "Chuyển tiền thành công."),
                new OA\Property(
                    property: "data",
                    type: "object",
                    properties: [
                        new OA\Property(property: "reference_id", type: "string", example: "uuid-string-xxxx-yyyy"),
                        new OA\Property(property: "balance", type: "string", example: "1450000.00")
                    ]
                )
            ]
        )
    )]
    public function transfer(TransferRequest $request): JsonResponse
    {
        $senderWallet = $this->walletRepo->findByUserId((int) $request->user()->id);

        $result = $this->transferService->transfer(
            $senderWallet->id,
            (int) $request->receiver_wallet_id,
            (string) $request->amount,
            $request->note
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Chuyển tiền thành công.',
            'data' => [
                'reference_id' => $result['reference_id'],
                'balance' => $result['sender']->balance,
            ]
        ]);
    }
}
