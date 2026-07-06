<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AddressController extends Controller
{
    #[OA\Get(
        path: "/api/v1/addresses",
        operationId: "getAddresses",
        summary: "Danh sách địa chỉ của người dùng",
        security: [["bearerAuth" => []]],
        tags: ["Addresses"]
    )]
    #[OA\Response(
        response: 200,
        description: "Thành công"
    )]
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->get();
        return response()->json([
            'status' => 'success',
            'data' => $addresses
        ]);
    }

    #[OA\Post(
        path: "/api/v1/addresses",
        operationId: "storeAddress",
        summary: "Thêm địa chỉ mới",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["type", "street"],
                properties: [
                    new OA\Property(property: "type", type: "string", example: "home"),
                    new OA\Property(property: "street", type: "string", example: "123 Nguyễn Huệ"),
                    new OA\Property(property: "ward", type: "string", example: "Bến Nghé"),
                    new OA\Property(property: "district", type: "string", example: "Quận 1"),
                    new OA\Property(property: "province", type: "string", example: "Hồ Chí Minh"),
                    new OA\Property(property: "is_default", type: "boolean", example: true)
                ]
            )
        ),
        tags: ["Addresses"]
    )]
    #[OA\Response(
        response: 201,
        description: "Đã tạo mới địa chỉ thành công"
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:home,work,other'],
            'street' => ['required', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        if ($validated['is_default'] ?? false) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm địa chỉ thành công.',
            'data' => $address
        ], 201);
    }

    #[OA\Put(
        path: "/api/v1/addresses/{id}",
        operationId: "updateAddress",
        summary: "Cập nhật thông tin địa chỉ",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "street", type: "string", example: "145 Nguyễn Huệ")
                ]
            )
        ),
        tags: ["Addresses"]
    )]
    #[OA\Response(
        response: 200,
        description: "Cập nhật thành công"
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);

        $validated = $request->validate([
            'type' => ['nullable', 'in:home,work,other'],
            'street' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($validated['is_default'] ?? false) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật địa chỉ thành công.',
            'data' => $address
        ]);
    }

    #[OA\Delete(
        path: "/api/v1/addresses/{id}",
        operationId: "deleteAddress",
        summary: "Xóa địa chỉ",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        tags: ["Addresses"]
    )]
    #[OA\Response(
        response: 200,
        description: "Xóa thành công"
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Xoá địa chỉ thành công.'
        ]);
    }
}
