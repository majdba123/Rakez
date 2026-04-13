<?php

namespace App\Services\AI\Skills\Scope\Contracts;

use App\Models\User;

interface RowScopeResolverContract
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $input
     * @return array{
     *   status:string,
     *   normalized_input?:array<string,mixed>,
     *   message?:string,
     *   reason?:string,
     *   follow_up_questions?:array<int,string>,
     *   data?:array<string,mixed>
     * }
     */
    public function resolve(User $user, array $definition, array $input): array;
}
