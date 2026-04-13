<?php

namespace App\Services\AI\Tools;

use App\Models\AiCall;
use App\Models\Lead;
use App\Models\User;

class AiCallStatusTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('ai-calls.manage')) {
            return ToolResponse::denied('ai-calls.manage');
        }

        $action = $args['action'] ?? null;
        if (! is_string($action) || $action === '') {
            return ToolResponse::invalidArguments('action is required.');
        }

        return match ($action) {
            'lead_calls' => $this->getLeadCalls($user, $args),
            'call_details' => $this->getCallDetails($user, $args),
            'call_stats' => $this->getCallStats(),
            default => ToolResponse::unsupportedOperation(
                "Unsupported action '{$action}'. Allowed: lead_calls, call_details, call_stats."
            ),
        };
    }

    private function getLeadCalls(User $user, array $args): array
    {
        if (! $user->can('leads.view')) {
            return ToolResponse::denied('leads.view');
        }

        $leadId = isset($args['lead_id']) ? (int) $args['lead_id'] : null;
        $inputs = ['action' => 'lead_calls', 'lead_id' => $leadId];

        if (! $leadId) {
            return ToolResponse::invalidArguments('lead_id is required for lead_calls.');
        }

        $lead = Lead::find($leadId);
        if (! $lead) {
            return ToolResponse::invalidArguments("Lead #{$leadId} not found.");
        }

        if (! $this->userCanViewLead($user, $lead)) {
            return ToolResponse::denied('leads.view_all');
        }

        $calls = AiCall::where('lead_id', $leadId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $callData = $calls->map(fn (AiCall $c) => [
            'id' => $c->id,
            'status' => $c->status,
            'duration_seconds' => $c->duration_seconds,
            'questions_asked' => $c->total_questions_asked,
            'questions_answered' => $c->total_questions_answered,
            'summary' => $c->call_summary,
            'date' => $c->created_at?->toDateString(),
        ])->toArray();

        return ToolResponse::success('tool_ai_call_status', $inputs, [
            'lead_name' => $lead->name,
            'lead_status' => $lead->status,
            'ai_qualification' => $lead->ai_qualification_status,
            'total_calls' => count($callData),
            'calls' => $callData,
        ], [['type' => 'record', 'title' => "AI Calls for Lead #{$leadId}", 'ref' => "lead:{$leadId}"]]);
    }

    private function getCallDetails(User $user, array $args): array
    {
        if (! $user->can('leads.view')) {
            return ToolResponse::denied('leads.view');
        }

        $callId = isset($args['call_id']) ? (int) $args['call_id'] : null;
        $inputs = ['action' => 'call_details', 'call_id' => $callId];

        if (! $callId) {
            return ToolResponse::invalidArguments('call_id is required for call_details.');
        }

        $call = AiCall::with('messages')->find($callId);
        if (! $call) {
            return ToolResponse::invalidArguments("Call #{$callId} not found.");
        }

        if (! $call->lead_id) {
            if (! $user->can('leads.view_all')) {
                return ToolResponse::denied('leads.view_all');
            }
        } else {
            $lead = Lead::find($call->lead_id);
            if ($lead && ! $this->userCanViewLead($user, $lead)) {
                return ToolResponse::denied('leads.view_all');
            }
        }

        $transcript = $call->messages->map(fn ($m) => [
            'role' => $m->role,
            'content' => mb_substr($m->content, 0, 300),
            'question' => $m->question_key,
        ])->toArray();

        return ToolResponse::success('tool_ai_call_status', $inputs, [
            'call_id' => $call->id,
            'customer_name' => $call->customer_name,
            'call_status' => $call->status,
            'duration_seconds' => $call->duration_seconds,
            'summary' => $call->call_summary,
            'sentiment_score' => $call->sentiment_score,
            'questions_asked' => $call->total_questions_asked,
            'questions_answered' => $call->total_questions_answered,
            'transcript' => $transcript,
        ], [['type' => 'record', 'title' => "AI Call #{$callId}", 'ref' => "ai_call:{$callId}"]]);
    }

    private function getCallStats(): array
    {
        $inputs = ['action' => 'call_stats'];

        $total = AiCall::count();
        $completed = AiCall::where('status', 'completed')->count();
        $failed = AiCall::where('status', 'failed')->count();
        $noAnswer = AiCall::where('status', 'no_answer')->count();
        $avgDuration = AiCall::where('status', 'completed')->avg('duration_seconds');

        return ToolResponse::success('tool_ai_call_status', $inputs, [
            'total_calls' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'no_answer' => $noAnswer,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 1).'%' : '0%',
            'avg_duration_seconds' => round($avgDuration ?? 0),
        ], [['type' => 'tool', 'title' => 'AI Call Statistics', 'ref' => 'tool_ai_call_status']]);
    }

    private function userCanViewLead(User $user, Lead $lead): bool
    {
        if ($user->can('leads.view_all')) {
            return true;
        }

        return (int) $lead->assigned_to === (int) $user->id;
    }
}
