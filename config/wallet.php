<?php

declare(strict_types=1);

return [
    'checksum_secret' => env('WALLET_CHECKSUM_SECRET', 'my-e-wallet-secure-checksum-key-2026-secret'),
    'max_transfer' => (float) env('WALLET_MAX_TRANSFER', 50000000.00),
    'login_max_attempts' => (int) env('WALLET_LOGIN_MAX_ATTEMPTS', 5),
    'login_decay_minutes' => (int) env('WALLET_LOGIN_DECAY_MINUTES', 15),
];
