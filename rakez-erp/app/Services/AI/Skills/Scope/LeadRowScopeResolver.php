<?php

namespace App\Services\AI\Skills\Scope;

use App\Models\Lead;
use App\Models\User;
use App\Services\AI\Skills\Scope\Contracts\RowScopeResolverContract;

class LeadRowScopeResolver implements RowScopeResolverContract
{
    public function resolve(User $user, array $definition, array $input): array
    {
        $leadId = isset($input['lead_id']) ? (int) $input['lead_id'] : 0;
        if ($leadId < 1) {
            return [
                'status' => 'needs_input',
                'message' => 'This skill requires an explicit `lead_id` before execution.',
                'reason' => 'row_scope.lead_id_required',
                'follow_up_questions' => ['Provide `lead_id` to continue.'],
                'data' => [
                    'missing_fields' => ['lead_id'],
                ],
            ];
        }

        $lead = Lead::query()->find($leadId);
        if (! $lead) {
            return [
                'status' => 'not_found',
                'message' => 'The requested lead could not be found within your accessible scope.',
                'reason' => 'row_scope.lead_not_found',
                'data' => [
                    'lead_id' => $leadId,
                ],
            ];
        }

        if (! $user->can('leads.view_all') && (int) $lead->assigned_to !== (int) $user->id) {
            return [
                'status' => 'denied',
                'message' => 'The requested lead is outside your accessible scope.',
                'reason' => 'row_scope.lead_forbidden',
                'data' => [
                    'lead_id' => $leadId,
                ],
            ];
        }

        return [
            'status' => 'ok',
            'normalized_input' => $input,
            'data' => [
                'record_type' => 'lead',
                'record_id' => $leadId,
            ],
        ];
    }
}
