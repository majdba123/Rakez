<?php

namespace App\Services\AI\Skills\Context;

use App\Models\User;
use App\Services\AI\ContextBuilder;
use App\Services\AI\Skills\Contracts\SectionContextBuilderContract;

abstract class AbstractSectionContextBuilder implements SectionContextBuilderContract
{
    public function __construct(
        protected readonly ContextBuilder $legacyBuilder,
    ) {}

    /**
     * @return string|null
     */
    abstract protected function sectionKey(): ?string;

    public function build(User $user, array $capabilities, array $input): array
    {
        $context = is_array($input['context'] ?? null)
            ? $input['context']
            : $input;

        return $this->legacyBuilder->build(
            $user,
            $this->sectionKey(),
            $capabilities,
            $context,
        );
    }
}
