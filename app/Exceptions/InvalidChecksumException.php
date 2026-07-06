<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

class InvalidChecksumException extends Exception
{
    public function __construct(string $message = "Chữ ký webhook (Checksum Signature) không hợp lệ.", int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
