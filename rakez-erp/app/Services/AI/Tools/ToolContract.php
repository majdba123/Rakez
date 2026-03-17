<?php

namespace App\Services\AI\Tools;

use App\Models\User;

interface ToolContract
{
    /**
     * Execute the tool for the given user.
     *
     * @param  array<string, mixed>  $args
     * @return array{result: mixed, source_refs: array}
     */
    public function __invoke(User $user, array $args): array;
}
