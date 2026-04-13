<?php

namespace App\Services\AI\Skills\Context;

class KnowledgeContextBuilder extends AbstractSectionContextBuilder
{
    protected function sectionKey(): ?string
    {
        return 'general';
    }
}
