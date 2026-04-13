<?php

namespace App\Services\AI\Skills\Context;

class InventoryContextBuilder extends AbstractSectionContextBuilder
{
    protected function sectionKey(): ?string
    {
        return 'units';
    }
}
