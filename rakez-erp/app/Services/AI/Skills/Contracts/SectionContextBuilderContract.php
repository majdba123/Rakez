<?php

namespace App\Services\AI\Skills\Contracts;

use App\Models\User;

interface SectionContextBuilderContract
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, string>  $capabilities
     * @return array<string, mixed>
     */
    public function build(User $user, array $capabilities, array $input): array;
}
