<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

class AiAssistantException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $statusCode = 400
    ) {
        parent::__construct($message, $statusCode);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
