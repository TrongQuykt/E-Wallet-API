<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class BankAccountController extends Controller
{
    #[OA\Get(
        path: "/api/v1/bank-accounts",
        operationId: "getBankAccounts",
        summary: "Danh sách tài khoản ngân hàng liên kết",
        security: [["bearerAuth" => []]],
        tags: ["Bank Accounts"]
    )]
    #[OA\Response(
        response: 200,
        description: "Thành công"
    )]
    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()->bankAccounts()->get();
        return response()->json([
            'status' => 'success',
            'data' => $accounts
        ]);
    }

    #[OA\Post(
        path: "/api/v1/bank-accounts",
        operationId: "storeBankAccount",
        summary: "Liên kết tài khoản ngân hàng mới",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["bank_name", "bank_code", "account_number", "account_name"],
                properties: [
                    new OA\Property(property: "bank_name", type: "string", example: "Vietcombank"),
                    new OA\Property(property: "bank_code", type: "string", example: "VCB"),
                    new OA\Property(property: "account_number", type: "string", example: "0071001234567"),
                    new OA\Property(property: "account_name", type: "string", example: "NGUYEN VAN A"),
                    new OA\Property(property: "branch", type: "string", example: "Chi nhánh TP.HCM"),
                    new OA\Property(property: "is_default", type: "boolean", example: false)
                ]
            )
        ),
        tags: ["Bank Accounts"]
    )]
    #[OA\Response(
        response: 201,
        description: "Liên kết thành công"
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_code' => ['required', 'string', 'max:20'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        if ($validated['is_default'] ?? false) {
            $user->bankAccounts()->update(['is_default' => false]);
        }

        $bankAccount = $user->bankAccounts()->create(array_merge($validated, [
            'is_verified' => false
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Liên kết tài khoản ngân hàng thành công.',
            'data' => $bankAccount
        ], 201);
    }

    #[OA\Delete(
        path: "/api/v1/bank-accounts/{id}",
        operationId: "deleteBankAccount",
        summary: "Gỡ bỏ tài khoản ngân hàng liên kết",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        tags: ["Bank Accounts"]
    )]
    #[OA\Response(
        response: 200,
        description: "Xóa thành công"
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $bankAccount = $request->user()->bankAccounts()->findOrFail($id);
        $bankAccount->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Gỡ bỏ liên kết ngân hàng thành công.'
        ]);
    }
}
