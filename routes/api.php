<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Middleware\VerifyWebhookSignature;

Route::prefix('v1')->group(function () {
    // Authentication Routes không auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Webhook nhận nạp tiền (chỉ cần VerifyWebhookSignature middleware, không cần auth:sanctum)
    Route::post('/wallet/deposit', [WalletController::class, 'deposit'])
        ->middleware(VerifyWebhookSignature::class);

    // Group các API yêu cầu đăng nhập bằng Sanctum Token
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Wallet APIs
        Route::get('/wallet/balance', [WalletController::class, 'balance']);
        Route::post('/wallet/transfer', [WalletController::class, 'transfer']);

        // Address APIs (CRUD)
        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

        // Bank Account APIs
        Route::get('/bank-accounts', [BankAccountController::class, 'index']);
        Route::post('/bank-accounts', [BankAccountController::class, 'store']);
        Route::delete('/bank-accounts/{id}', [BankAccountController::class, 'destroy']);
    });
});

