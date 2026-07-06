<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\RateLimiter;

class AuthService
{
    /**
     * Đăng ký tài khoản mới và tạo ví tự động với số dư = 0.
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            $user->wallet()->create([
                'balance' => '0.00',
                'currency' => $data['currency'] ?? 'VND',
                'is_active' => true,
            ]);

            return $user->load('wallet');
        });
    }

    /**
     * Đăng nhập người dùng bằng Sanctum Token, có tích hợp Rate Limiting chống DDOS.
     */
    public function login(array $data): string
    {
        $key = 'login-attempt:' . $data['email'];
        $maxAttempts = config('wallet.login_max_attempts', 5);
        $decayMinutes = config('wallet.login_decay_minutes', 15);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => ["Tài khoản bị khóa do đăng nhập sai nhiều lần. Vui lòng thử lại sau {$seconds} giây."],
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, $decayMinutes * 60);
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Tài khoản của bạn đã bị khóa.'],
            ]);
        }

        RateLimiter::clear($key);

        return $user->createToken('auth_token')->plainTextToken;
    }
}
