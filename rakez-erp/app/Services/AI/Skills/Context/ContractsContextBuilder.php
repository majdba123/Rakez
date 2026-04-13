<?php

namespace App\Services\AI\Skills\Context;

class ContractsContextBuilder extends AbstractSectionContextBuilder
{
    protected function sectionKey(): ?string
    {
        return 'contracts';
    }
}
