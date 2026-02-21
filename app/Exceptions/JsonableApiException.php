<?php

namespace App\Exceptions;

use Exception;

abstract class JsonableApiException extends Exception
{
    protected string $errorCode;

    public function __construct(
        string $message,
        string $errorCode,
        int $statusCode = 400
    ) {
        parent::__construct($message, $statusCode);
        $this->errorCode = $errorCode;
    }

    /**
     * Get the error code.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], $this->getCode() ?: 400);
    }
}
