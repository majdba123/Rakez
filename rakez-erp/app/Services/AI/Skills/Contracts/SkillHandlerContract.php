<?php

namespace App\Services\AI\Skills\Contracts;

use App\Models\User;

interface SkillHandlerContract
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(User $user, array $definition, array $input, array $context): array;
}
