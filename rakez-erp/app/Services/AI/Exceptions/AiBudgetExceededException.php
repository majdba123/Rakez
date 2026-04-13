<?php

namespace App\Services\AI\Exceptions;

class AiBudgetExceededException extends AiAssistantException
{
    public function __construct(int $limit, int $used)
    {
        $msg = sprintf(
            (string) config('ai_assistant.messages.budget_exceeded', 'تم تجاوز حدّ الرموز اليومي (%1$d/%2$d). يُرجى المحاولة لاحقًا.'),
            $used,
            $limit
        );

        parent::__construct(
            $msg,
            'ai_budget_exceeded',
            429
        );
    }
}
