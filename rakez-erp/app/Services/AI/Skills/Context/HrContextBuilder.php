<?php

namespace App\Services\AI\Skills\Context;

class HrContextBuilder extends AbstractSectionContextBuilder
{
    protected function sectionKey(): ?string
    {
        return 'hr';
    }
}
