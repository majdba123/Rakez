<?php

namespace App\Services\AI\Exceptions;

class AiUnauthorizedSectionException extends AiAssistantException
{
    public function __construct(string $section = '')
    {
        $message = $section 
            ? "You do not have permission to access the '{$section}' section."
            : "You do not have permission to access this section.";
            
        parent::__construct($message, 'UNAUTHORIZED_SECTION', 403);
    }
}
