<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {
    }

    #[OA\Post(
        path: "/api/v1/auth/register",
        operationId: "registerUser",
        summary: "Đăng ký tài khoản người dùng mới",
        description: "Đăng ký tài khoản mới và sinh ví điện tử tự động với số dư ban đầu bằng 0 VND",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Nguyễn Văn A"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "vana@gmail.com"),
                    new OA\Property(property: "phone", type: "string", example: "0987654321"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "Secret123@"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "Secret123@"),
                    new OA\Property(property: "currency", type: "string", example: "VND")
                ]
            )
        ),
        tags: ["Authentication"]
    )]
    #[OA\Response(
        response: 201,
        description: "Đăng ký thành công",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "success"),
                new OA\Property(property: "message", type: "string", example: "Đăng ký tài khoản thành công và đã tự động khởi tạo ví."),
                new OA\Property(property: "data", type: "object")
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: "Dữ liệu validate không hợp lệ"
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký tài khoản thành công và đã tự động khởi tạo ví.',
            'data' => $user,
        ], 201);
    }

    #[OA\Post(
        path: "/api/v1/auth/login",
        operationId: "loginUser",
        summary: "Đăng nhập tài khoản",
        description: "Đăng nhập bằng Email, Password và nhận Sanctum Bearer Token. Giới hạn 5 lần đăng nhập sai liên tiếp.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "vana@gmail.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "Secret123@")
                ]
            )
        ),
        tags: ["Authentication"]
    )]
    #[OA\Response(
        response: 200,
        description: "Đăng nhập thành công",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "success"),
                new OA\Property(property: "message", type: "string", example: "Đăng nhập thành công."),
                new OA\Property(property: "token", type: "string", example: "1|SanctumPlainTextTokenValueHere")
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: "Thông tin đăng nhập không khớp hoặc bị khóa login"
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->authService->login($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng nhập thành công.',
            'token' => $token,
        ]);
    }

    #[OA\Post(
        path: "/api/v1/auth/logout",
        operationId: "logoutUser",
        summary: "Đăng xuất tài khoản",
        description: "Hũy bỏ token hiện tại của người dùng",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"]
    )]
    #[OA\Response(
        response: 200,
        description: "Đăng xuất thành công",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "success"),
                new OA\Property(property: "message", type: "string", example: "Đăng xuất và hủy token thành công.")
            ]
        )
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng xuất và hủy token thành công.',
        ]);
    }
}
