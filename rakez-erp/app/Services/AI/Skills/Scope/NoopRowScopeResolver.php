<?php

namespace App\Services\AI\Skills\Scope;

use App\Models\User;
use App\Services\AI\Skills\Scope\Contracts\RowScopeResolverContract;

class NoopRowScopeResolver implements RowScopeResolverContract
{
    public function resolve(User $user, array $definition, array $input): array
    {
        return [
            'status' => 'ok',
            'normalized_input' => $input,
            'data' => [],
        ];
    }
}
