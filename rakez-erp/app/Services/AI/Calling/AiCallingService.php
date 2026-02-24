<?php

namespace App\Services\AI\Calling;

use App\Jobs\InitiateAiCallJob;
use App\Models\AiCall;
use App\Models\AiCallScript;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AiCallingService
{
    /**
     * Initiate a single AI call to a lead or customer.
     */
    public function initiateCall(User $user, int $targetId, string $targetType, ?int $scriptId = null, bool $dispatch = true): AiCall
    {
        if (! config('ai_calling.enabled')) {
            throw new InvalidArgumentException('AI Calling is disabled.');
        }

        $this->checkConcurrencyLimit();

        $target = $this->resolveTarget($targetId, $targetType);

        $script = $scriptId
            ? AiCallScript::where('id', $scriptId)->active()->firstOrFail()
            : AiCallScript::active()->forTarget($targetType)->first();

        if (! $script) {
            throw new InvalidArgumentException('No active script found for target type: ' . $targetType);
        }

        $previousAttempts = AiCall::where('lead_id', $targetId)
            ->where('customer_type', $targetType)
            ->count();

        $maxAttempts = config('ai_calling.call.max_call_attempts', 3);
        if ($previousAttempts >= $maxAttempts) {
            throw new InvalidArgumentException("Maximum call attempts ({$maxAttempts}) reached for this target.");
        }

        $call = AiCall::create([
            'lead_id' => $targetId,
            'customer_type' => $targetType,
            'customer_name' => $target['name'],
            'phone_number' => $target['phone'],
            'script_id' => $script->id,
            'status' => 'pending',
            'direction' => 'outbound',
            'attempt_number' => $previousAttempts + 1,
            'initiated_by' => $user->id,
        ]);

        if ($dispatch) {
            InitiateAiCallJob::dispatch($call->id);
        }

        Log::info('AI call initiated', [
            'ai_call_id' => $call->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'user_id' => $user->id,
        ]);

        return $call;
    }

    /**
     * Initiate bulk calls for multiple targets.
     *
     * @return array{queued: int, skipped: int, errors: array}
     */
    public function initiateBulkCalls(User $user, array $targetIds, string $targetType, int $scriptId): array
    {
        if (! config('ai_calling.enabled')) {
            throw new InvalidArgumentException('AI Calling is disabled.');
        }

        $maxBatch = config('ai_calling.bulk.max_per_batch', 50);
        if (count($targetIds) > $maxBatch) {
            throw new InvalidArgumentException("Maximum {$maxBatch} calls per batch.");
        }

        $delay = config('ai_calling.bulk.delay_between_calls_seconds', 10);
        $queued = 0;
        $skipped = 0;
        $errors = [];

        foreach ($targetIds as $index => $targetId) {
            try {
                $call = $this->initiateCall($user, $targetId, $targetType, $scriptId, dispatch: false);

                $jobDelay = ($index > 0 && $delay > 0)
                    ? now()->addSeconds($delay * $index)
                    : null;

                InitiateAiCallJob::dispatch($call->id)->delay($jobDelay);

                $queued++;
            } catch (InvalidArgumentException $e) {
                $skipped++;
                $errors[] = ['target_id' => $targetId, 'reason' => $e->getMessage()];
            }
        }

        Log::info('Bulk AI calls initiated', [
            'queued' => $queued,
            'skipped' => $skipped,
            'user_id' => $user->id,
        ]);

        return compact('queued', 'skipped', 'errors');
    }

    /**
     * Retry a failed or no-answer call.
     */
    public function retryCall(int $callId): AiCall
    {
        $original = AiCall::findOrFail($callId);

        if (! in_array($original->status, ['failed', 'no_answer', 'busy'])) {
            throw new InvalidArgumentException('Only failed, no-answer, or busy calls can be retried.');
        }

        $maxAttempts = config('ai_calling.call.max_call_attempts', 3);
        if ($original->attempt_number >= $maxAttempts) {
            throw new InvalidArgumentException("Maximum call attempts ({$maxAttempts}) reached.");
        }

        $newCall = AiCall::create([
            'lead_id' => $original->lead_id,
            'customer_type' => $original->customer_type,
            'customer_name' => $original->customer_name,
            'phone_number' => $original->phone_number,
            'script_id' => $original->script_id,
            'status' => 'pending',
            'direction' => 'outbound',
            'attempt_number' => $original->attempt_number + 1,
            'initiated_by' => $original->initiated_by,
        ]);

        InitiateAiCallJob::dispatch($newCall->id);

        return $newCall;
    }

    /**
     * Get paginated call history with filters.
     */
    public function getCallHistory(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = AiCall::with(['script:id,name', 'initiator:id,name'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer_type'])) {
            $query->where('customer_type', $filters['customer_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['lead_id'])) {
            $query->where('lead_id', $filters['lead_id']);
        }

        if (! empty($filters['initiated_by'])) {
            $query->where('initiated_by', $filters['initiated_by']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get the full transcript for a call.
     */
    public function getCallTranscript(int $callId): array
    {
        $call = AiCall::with(['messages', 'script:id,name'])->findOrFail($callId);

        return [
            'call' => [
                'id' => $call->id,
                'customer_name' => $call->customer_name,
                'phone_number' => $call->phone_number,
                'status' => $call->status,
                'duration_seconds' => $call->duration_seconds,
                'started_at' => $call->started_at?->toIso8601String(),
                'ended_at' => $call->ended_at?->toIso8601String(),
                'total_questions_asked' => $call->total_questions_asked,
                'total_questions_answered' => $call->total_questions_answered,
                'call_summary' => $call->call_summary,
                'script_name' => $call->script?->name,
            ],
            'messages' => $call->messages->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
                'question_key' => $msg->question_key,
                'timestamp_in_call' => $msg->timestamp_in_call,
                'created_at' => $msg->created_at?->toIso8601String(),
            ])->toArray(),
        ];
    }

    /**
     * Get call analytics for dashboards.
     */
    public function getCallAnalytics(User $user, array $filters = []): array
    {
        $query = AiCall::query();

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $failed = (clone $query)->where('status', 'failed')->count();
        $noAnswer = (clone $query)->where('status', 'no_answer')->count();
        $avgDuration = (clone $query)->where('status', 'completed')->avg('duration_seconds');

        $avgQuestionsAsked = (clone $query)->where('status', 'completed')->avg('total_questions_asked');
        $avgQuestionsAnswered = (clone $query)->where('status', 'completed')->avg('total_questions_answered');

        $byStatus = (clone $query)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $dailyCalls = (clone $query)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'completed' then 1 else 0 end) as completed")
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->limit(30)
            ->get()
            ->toArray();

        return [
            'total_calls' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'no_answer' => $noAnswer,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'avg_duration_seconds' => round($avgDuration ?? 0),
            'avg_questions_asked' => round($avgQuestionsAsked ?? 0, 1),
            'avg_questions_answered' => round($avgQuestionsAnswered ?? 0, 1),
            'answer_rate' => ($avgQuestionsAsked ?? 0) > 0
                ? round((($avgQuestionsAnswered ?? 0) / $avgQuestionsAsked) * 100, 1)
                : 0,
            'by_status' => $byStatus,
            'daily_calls' => $dailyCalls,
        ];
    }

    /**
     * Resolve target (lead or customer) to name + phone.
     *
     * @return array{name: string, phone: string}
     */
    private function resolveTarget(int $targetId, string $targetType): array
    {
        if (! in_array($targetType, ['lead', 'customer'])) {
            throw new InvalidArgumentException("Invalid target type: {$targetType}");
        }

        $lead = Lead::findOrFail($targetId);
        $phone = $lead->contact_info;

        if (empty($phone)) {
            throw new InvalidArgumentException('Target has no contact information.');
        }

        if (! preg_match('/^[\+]?[0-9\s\-\(\)]{7,20}$/', $phone)) {
            throw new InvalidArgumentException('Target contact info is not a valid phone number: ' . $phone);
        }

        return [
            'name' => $lead->name ?? 'Unknown',
            'phone' => $phone,
        ];
    }

    private function checkConcurrencyLimit(): void
    {
        $maxConcurrent = config('ai_calling.call.max_concurrent_calls', 5);
        $activeCalls = AiCall::whereIn('status', ['pending', 'ringing', 'in_progress'])->count();

        if ($activeCalls >= $maxConcurrent) {
            throw new InvalidArgumentException(
                "Maximum concurrent calls ({$maxConcurrent}) reached. Please wait for active calls to finish."
            );
        }
    }
}
