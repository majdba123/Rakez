<?php

namespace App\Services\AI\Skills\Scope;

use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Skills\Scope\Contracts\RowScopeResolverContract;

class RowScopeResolverRegistry
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function resolve(array $definition): RowScopeResolverContract
    {
        $rowScope = (array) ($definition['row_scope'] ?? []);
        $resolverClass = (string) ($rowScope['resolver'] ?? RequiredInputsRowScopeResolver::class);

        if ($resolverClass === '' || ! class_exists($resolverClass)) {
            throw new AiAssistantException('Row scope resolver is not configured.', 'ai_validation_failed', 422);
        }

        $resolver = app($resolverClass);
        if (! $resolver instanceof RowScopeResolverContract) {
            throw new AiAssistantException('Row scope resolver contract is invalid.', 'ai_validation_failed', 422);
        }

        return $resolver;
    }
}
