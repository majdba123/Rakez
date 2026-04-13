<?php

namespace App\Services\AI\Skills\Context;

class CreditContextBuilder extends AbstractSectionContextBuilder
{
    protected function sectionKey(): ?string
    {
        return 'credit';
    }
}
