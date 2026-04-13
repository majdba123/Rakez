<?php

namespace App\Services\AI\Skills\Context;

class AccountingContextBuilder extends AbstractSectionContextBuilder
{
    protected function sectionKey(): ?string
    {
        return 'accounting';
    }
}
