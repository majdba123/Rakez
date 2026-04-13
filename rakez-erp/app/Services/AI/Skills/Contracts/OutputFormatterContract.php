<?php

namespace App\Services\AI\Skills\Contracts;

interface OutputFormatterContract
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $execution
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function format(array $definition, array $execution, array $context, array $input): array;
}
