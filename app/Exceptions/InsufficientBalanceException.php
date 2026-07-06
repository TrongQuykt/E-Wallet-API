<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

class InsufficientBalanceException extends Exception
{
    public function __construct(string $message = "Số dư tài khoản không đủ để thực hiện giao dịch.", int $code = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
