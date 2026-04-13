<?php

namespace App\Services\AI\Tools;

use App\Models\Lead;
use App\Models\User;

class GetLeadSummaryTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('leads.view')) {
            return ToolResponse::denied('leads.view');
        }

        $leadId = $args['lead_id'] ?? null;
        if (! $leadId) {
            return ToolResponse::invalidArguments('lead_id is required.');
        }

        $lead = Lead::with(['assignedTo', 'project', 'aiCalls'])->find($leadId);

        if (! $lead) {
            return ToolResponse::invalidArguments("Lead #{$leadId} not found.");
        }

        // Check ownership unless user has view_all
        if (! $user->can('leads.view_all') && $lead->assigned_to !== $user->id) {
            return ToolResponse::denied('leads.view_all');
        }

        $data = [
            'id' => $lead->id,
            'name' => $lead->name,
            'contact_info' => $lead->contact_info,
            'source' => $lead->source,
            'lead_status' => $lead->status,
            'lead_score' => $lead->lead_score,
            'campaign_platform' => $lead->campaign_platform,
            'assigned_to' => $lead->assignedTo?->name ?? 'غير معين',
            'project' => $lead->project?->project_name ?? 'غير محدد',
            'ai_qualification_status' => $lead->ai_qualification_status,
            'ai_call_count' => $lead->ai_call_count ?? 0,
            'ai_call_notes' => $lead->ai_call_notes,
            'created_at' => $lead->created_at?->toDateTimeString(),
        ];

        return ToolResponse::success('tool_get_lead_summary', ['lead_id' => $leadId], $data, [
            ['type' => 'record', 'title' => "Lead: {$lead->name}", 'ref' => "lead:{$lead->id}"],
        ]);
    }
}
