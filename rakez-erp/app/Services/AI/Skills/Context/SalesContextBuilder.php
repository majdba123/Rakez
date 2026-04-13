<?php

namespace App\Services\AI\Skills\Context;

class SalesContextBuilder extends AbstractSectionContextBuilder
{
    protected function sectionKey(): ?string
    {
        return 'sales';
    }
}
