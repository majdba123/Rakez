<?php

namespace App\Services\AI\SafeWrites\Contracts;

use App\Models\User;

interface SafeWriteActionHandler
{
    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function propose(User $user, array $action, array $input): array;

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $proposal
     * @return array<string, mixed>
     */
    public function preview(User $user, array $action, array $proposal): array;

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function confirm(User $user, array $action, array $input): array;

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function reject(User $user, array $action, array $input): array;
}
