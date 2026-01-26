<?php

namespace App\Services\AI\Exceptions;

class AiAssistantDisabledException extends AiAssistantException
{
    public function __construct()
    {
        parent::__construct('AI assistant is currently disabled.', 'ai_disabled', 503);
    }
}
