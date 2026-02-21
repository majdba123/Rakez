<?php

namespace App\Services\AI\Tools;

use App\Models\User;

interface ToolContract
{
    /**
     * Execute the tool with authenticated user and validated arguments.
     *
     * @param  array<string, mixed>  $args
     * @return array{result: mixed, source_refs: array<int, array{type: string, title: string, ref: string}>}
     */
    public function __invoke(User $user, array $args): array;
}
