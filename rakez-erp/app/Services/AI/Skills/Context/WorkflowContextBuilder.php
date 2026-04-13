<?php

namespace App\Services\AI\Skills\Context;

class WorkflowContextBuilder extends AbstractSectionContextBuilder
{
    protected function sectionKey(): ?string
    {
        return 'notifications';
    }
}
