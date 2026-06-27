<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

class ConflictException extends HttpException
{
    public function __construct(string $message = 'Conflict', ?\Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }
}
