<?php

namespace App\Services\AI\Tools;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class GetLeadSummaryTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        $leadId = (int) Arr::get($args, 'lead_id', 0);
        if ($leadId <= 0) {
            return ['result' => ['error' => 'Invalid lead_id'], 'source_refs' => []];
        }
        $lead = Lead::find($leadId);
        if (! $lead) {
            return ['result' => ['error' => 'Lead not found'], 'source_refs' => []];
        }
        if (! Gate::forUser($user)->allows('view', $lead)) {
            return [
                'result' => ['error' => 'Access denied', 'allowed' => false],
                'source_refs' => [],
            ];
        }
        $summary = sprintf(
            'Lead #%d: %s. Contact: %s. Source: %s. Status: %s.',
            $lead->id,
            $lead->name,
            mb_substr($lead->contact_info ?? '', 0, 100),
            $lead->source ?? '—',
            $lead->status ?? '—'
        );

        return [
            'result' => ['summary' => $summary, 'lead_id' => $lead->id, 'name' => $lead->name, 'status' => $lead->status],
            'source_refs' => [['type' => 'record', 'title' => 'Lead: '.$lead->name, 'ref' => "lead/{$lead->id}"]],
        ];
    }
}
