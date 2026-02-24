<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiCall;
use App\Models\AiCallScript;
use App\Services\AI\Calling\AiCallingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiCallController extends Controller
{
    public function __construct(
        private readonly AiCallingService $callingService,
    ) {}

    /**
     * POST /api/ai/calls/initiate
     * Initiate a single AI call.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_id' => 'required|integer',
            'target_type' => 'required|in:lead,customer',
            'script_id' => 'nullable|integer|exists:ai_call_scripts,id',
        ]);

        try {
            $call = $this->callingService->initiateCall(
                $request->user(),
                $validated['target_id'],
                $validated['target_type'],
                $validated['script_id'] ?? null,
            );

            return response()->json([
                'message' => 'تم إنشاء المكالمة بنجاح',
                'call' => $this->formatCall($call),
            ], 201);
        } catch (Throwable $e) {
            Log::error('AI call initiation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'فشل في إنشاء المكالمة',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/ai/calls/bulk
     * Initiate bulk AI calls.
     */
    public function bulkInitiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_ids' => 'required|array|min:1',
            'target_ids.*' => 'integer',
            'target_type' => 'required|in:lead,customer',
            'script_id' => 'required|integer|exists:ai_call_scripts,id',
        ]);

        try {
            $result = $this->callingService->initiateBulkCalls(
                $request->user(),
                $validated['target_ids'],
                $validated['target_type'],
                $validated['script_id'],
            );

            return response()->json([
                'message' => "تم جدولة {$result['queued']} مكالمة",
                'queued' => $result['queued'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'فشل في جدولة المكالمات',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/ai/calls
     * List calls with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'status', 'customer_type', 'date_from', 'date_to',
            'lead_id', 'initiated_by', 'per_page',
        ]);

        $calls = $this->callingService->getCallHistory($request->user(), $filters);

        return response()->json([
            'data' => collect($calls->items())->map(fn ($call) => $this->formatCall($call)),
            'meta' => [
                'current_page' => $calls->currentPage(),
                'last_page' => $calls->lastPage(),
                'per_page' => $calls->perPage(),
                'total' => $calls->total(),
            ],
        ]);
    }

    /**
     * GET /api/ai/calls/analytics
     * Call analytics dashboard.
     */
    public function analytics(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to']);
        $analytics = $this->callingService->getCallAnalytics($request->user(), $filters);

        return response()->json(['data' => $analytics]);
    }

    /**
     * GET /api/ai/calls/{id}
     * Show a single call with details.
     */
    public function show(int $id): JsonResponse
    {
        $call = AiCall::with(['script:id,name', 'initiator:id,name', 'messages'])->findOrFail($id);

        return response()->json([
            'data' => $this->formatCallDetailed($call),
        ]);
    }

    /**
     * GET /api/ai/calls/{id}/transcript
     * Full call transcript.
     */
    public function transcript(int $id): JsonResponse
    {
        $transcript = $this->callingService->getCallTranscript($id);

        return response()->json(['data' => $transcript]);
    }

    /**
     * POST /api/ai/calls/{id}/retry
     * Retry a failed call.
     */
    public function retry(int $id): JsonResponse
    {
        try {
            $call = $this->callingService->retryCall($id);

            return response()->json([
                'message' => 'تم إعادة جدولة المكالمة',
                'call' => $this->formatCall($call),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'فشل في إعادة المكالمة',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/ai/calls/scripts
     * List available call scripts.
     */
    public function scripts(Request $request): JsonResponse
    {
        $scripts = AiCallScript::query()
            ->when($request->get('active_only', true), fn ($q) => $q->active())
            ->when($request->get('target_type'), fn ($q, $t) => $q->forTarget($t))
            ->orderBy('name')
            ->get()
            ->map(fn (AiCallScript $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'target_type' => $s->target_type,
                'language' => $s->language,
                'question_count' => $s->getQuestionCount(),
                'is_active' => $s->is_active,
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $scripts]);
    }

    /**
     * POST /api/ai/calls/scripts
     * Create a new call script.
     */
    public function storeScript(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'target_type' => 'required|in:lead,customer,both',
            'language' => 'nullable|string|max:5',
            'questions' => 'required|array|min:1',
            'questions.*.key' => 'required|string',
            'questions.*.text_ar' => 'required|string',
            'questions.*.text_en' => 'nullable|string',
            'questions.*.required' => 'nullable|boolean',
            'questions.*.type' => 'nullable|string|in:text,number,yes_no,choice',
            'greeting_text' => 'required|string',
            'closing_text' => 'required|string',
            'max_retries_per_question' => 'nullable|integer|min:0|max:5',
        ]);

        $script = AiCallScript::create(array_merge($validated, [
            'language' => $validated['language'] ?? 'ar',
            'max_retries_per_question' => $validated['max_retries_per_question'] ?? 2,
            'is_active' => true,
        ]));

        return response()->json([
            'message' => 'تم إنشاء السكريبت بنجاح',
            'data' => $script,
        ], 201);
    }

    /**
     * PUT /api/ai/calls/scripts/{id}
     * Update a call script.
     */
    public function updateScript(Request $request, int $id): JsonResponse
    {
        $script = AiCallScript::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'target_type' => 'sometimes|in:lead,customer,both',
            'language' => 'sometimes|string|max:5',
            'questions' => 'sometimes|array|min:1',
            'questions.*.key' => 'required_with:questions|string',
            'questions.*.text_ar' => 'required_with:questions|string',
            'questions.*.text_en' => 'nullable|string',
            'questions.*.required' => 'nullable|boolean',
            'questions.*.type' => 'nullable|string|in:text,number,yes_no,choice',
            'greeting_text' => 'sometimes|string',
            'closing_text' => 'sometimes|string',
            'max_retries_per_question' => 'sometimes|integer|min:0|max:5',
            'is_active' => 'sometimes|boolean',
        ]);

        $script->update($validated);

        return response()->json([
            'message' => 'تم تحديث السكريبت',
            'data' => $script->fresh(),
        ]);
    }

    /**
     * DELETE /api/ai/calls/scripts/{id}
     */
    public function deleteScript(int $id): JsonResponse
    {
        $script = AiCallScript::findOrFail($id);

        $activeCallsCount = AiCall::where('script_id', $id)
            ->whereIn('status', ['pending', 'ringing', 'in_progress'])
            ->count();

        if ($activeCallsCount > 0) {
            return response()->json([
                'message' => 'لا يمكن حذف السكريبت لأن فيه مكالمات نشطة تستخدمه',
            ], 422);
        }

        $script->update(['is_active' => false]);

        return response()->json(['message' => 'تم تعطيل السكريبت']);
    }

    private function formatCall(AiCall $call): array
    {
        return [
            'id' => $call->id,
            'lead_id' => $call->lead_id,
            'customer_type' => $call->customer_type,
            'customer_name' => $call->customer_name,
            'phone_number' => $call->phone_number,
            'status' => $call->status,
            'direction' => $call->direction,
            'duration_seconds' => $call->duration_seconds,
            'total_questions_asked' => $call->total_questions_asked,
            'total_questions_answered' => $call->total_questions_answered,
            'attempt_number' => $call->attempt_number,
            'script_name' => $call->script?->name,
            'initiated_by_name' => $call->initiator?->name,
            'started_at' => $call->started_at?->toIso8601String(),
            'ended_at' => $call->ended_at?->toIso8601String(),
            'created_at' => $call->created_at?->toIso8601String(),
        ];
    }

    private function formatCallDetailed(AiCall $call): array
    {
        return array_merge($this->formatCall($call), [
            'call_summary' => $call->call_summary,
            'sentiment_score' => $call->sentiment_score,
            'twilio_call_sid' => $call->twilio_call_sid,
            'messages' => $call->messages->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
                'question_key' => $msg->question_key,
                'timestamp_in_call' => $msg->timestamp_in_call,
                'created_at' => $msg->created_at?->toIso8601String(),
            ])->toArray(),
        ]);
    }
}
