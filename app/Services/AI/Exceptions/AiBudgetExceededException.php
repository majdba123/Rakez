<?php

namespace App\Services\AI\Exceptions;

class AiBudgetExceededException extends AiAssistantException
{
    public function __construct(int $limit, int $used)
    {
        parent::__construct(
            "Daily token budget exceeded ({$used}/{$limit}). Please try again later.",
            'ai_budget_exceeded',
            429
        );
    }
}
