<?php

namespace App\Services\AI\Skills\Context;

class MarketingContextBuilder extends AbstractSectionContextBuilder
{
    protected function sectionKey(): ?string
    {
        return 'marketing_dashboard';
    }
}
