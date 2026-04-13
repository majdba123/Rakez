<?php

namespace App\Services\AI\Skills\Scope;

use App\Models\User;
use App\Services\AI\Skills\Scope\Contracts\RowScopeResolverContract;

class RequiredInputsRowScopeResolver implements RowScopeResolverContract
{
    public function resolve(User $user, array $definition, array $input): array
    {
        $rowScope = (array) ($definition['row_scope'] ?? []);
        $requiredInputs = (array) ($rowScope['required_inputs'] ?? []);

        $missing = [];
        foreach ($requiredInputs as $field) {
            $field = (string) $field;
            if ($field === '' || array_key_exists($field, $input)) {
                continue;
            }

            $missing[] = $field;
        }

        if ($missing !== []) {
            return [
                'status' => 'needs_input',
                'message' => 'This skill requires explicit record identifiers before execution.',
                'reason' => 'row_scope.missing_identifier',
                'follow_up_questions' => array_map(
                    static fn (string $field): string => "Provide `{$field}` to continue.",
                    $missing
                ),
                'data' => [
                    'missing_fields' => $missing,
                ],
            ];
        }

        return [
            'status' => 'ok',
            'normalized_input' => $input,
            'data' => [],
        ];
    }
}
