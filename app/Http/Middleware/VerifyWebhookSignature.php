<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Exceptions\InvalidChecksumException;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->input('signature');
        $walletId = $request->input('wallet_id');
        $amount = $request->input('amount');
        $referenceId = $request->input('reference_id');

        if (!$signature || !$walletId || !$amount || !$referenceId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thiếu các thông tin bắt buộc để kiểm tra signature.'
            ], 400);
        }

        $secret = config('wallet.checksum_secret');
        $payload = sprintf('%s|%s|%s', $walletId, $amount, $referenceId);
        $computedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($computedSignature, $signature)) {
            throw new InvalidChecksumException();
        }

        return $next($request);
    }
}
