<?php

namespace App\Services\AI\Drafts;

use App\Models\AIConversation;
use App\Models\User;
use App\Services\AI\AiAuditService;
use App\Services\AI\Exceptions\AiBudgetExceededException;
use Carbon\Carbon;

class AssistantDraftService
{
    public function __construct(
        private readonly AssistantDraftFlowRegistry $flowRegistry,
        private readonly AssistantDraftUnderstandingService $understandingService,
        private readonly AssistantDraftValidationService $validationService,
        private readonly AssistantDraftSchemaFactory $schemaFactory,
        private readonly AiAuditService $auditService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function prepare(User $user, string $message, ?string $requestedFlow = null, ?string $provider = null): array
    {
        $this->ensureWithinBudget($user);

        $availableFlowKeys = $this->flowRegistry->supportedKeysForUser($user);

        if ($requestedFlow && ! in_array($requestedFlow, $availableFlowKeys, true)) {
            return $this->auditAndReturn($user, $message, $requestedFlow, [
                'status' => 'refused',
                'flow' => null,
                'refusal' => [
                    'category' => 'not_allowed',
                    'message' => 'This draft flow is not available for your current permissions.',
                ],
            ]);
        }

        $understanding = $this->understandingService->understand($user, $message, $availableFlowKeys, $requestedFlow, $provider);

        if (($understanding['outcome'] ?? null) === 'refused') {
            return $this->auditAndReturn($user, $message, $requestedFlow, [
                'status' => 'refused',
                'flow' => null,
                'understanding' => $this->understandingSummary($understanding),
                'refusal' => [
                    'category' => 'blocked_write',
                    'message' => $understanding['refusal_reason'] ?: 'This request is intentionally blocked in draft-only mode.',
                ],
            ]);
        }

        $flowKey = $understanding['flow_key'] ?? null;
        $flow = is_string($flowKey) ? $this->flowRegistry->find($flowKey) : null;

        if (! $flow) {
            return $this->auditAndReturn($user, $message, $requestedFlow, [
                'status' => 'needs_input',
                'flow' => null,
                'understanding' => $this->understandingSummary($understanding),
                'refusal' => null,
                'validation_preview' => [
                    'is_valid' => false,
                    'errors' => [],
                    'missing_fields' => $understanding['missing_fields'] ?? [],
                    'warnings' => ['The assistant could not map this request to a supported low-risk draft flow.'],
                ],
            ]);
        }

        $schema = $this->schemaFactory->build($flow, (array) ($understanding['raw_slots'] ?? []));
        $preview = $this->validationService->preview($user, $flow, $schema['payload']);

        $missingFields = array_values(array_unique(array_merge(
            $understanding['missing_fields'] ?? [],
            $this->missingRequiredFields($flow, $schema['payload'])
        )));

        $isReady = empty($missingFields)
            && empty($schema['entity_confirmation']['ambiguous'])
            && empty($schema['entity_confirmation']['unresolved'])
            && ($preview['is_valid'] ?? false);

        return $this->auditAndReturn($user, $message, $requestedFlow, [
            'status' => $isReady ? 'ready' : 'needs_input',
            'flow' => [
                'key' => $flow['key'],
                'label' => $flow['label'],
                'safe_write_action_key' => $flow['safe_write_action_key'] ?? null,
                'required_fields' => $flow['required_fields'],
                'optional_fields' => $flow['optional_fields'],
                'validation_source' => $flow['validation_request'],
                'handoff' => $this->handoffForFlow($flow, $schema['payload']),
                'entity_confirmation_rules' => $flow['entity_rules'],
                'ambiguity_handling' => $flow['ambiguity_rules'],
                'refusal_cases' => $flow['refusal_cases'],
            ],
            'understanding' => $this->understandingSummary($understanding),
            'draft' => [
                'schema_version' => 'v1',
                'schema' => [
                    'flow_key' => $flow['key'],
                    'safe_write_action_key' => $flow['safe_write_action_key'] ?? null,
                    'validation_source' => $flow['validation_request'],
                    'fields' => [
                        'required' => $flow['required_fields'],
                        'optional' => $flow['optional_fields'],
                    ],
                ],
                'payload' => $schema['payload'],
                'raw_slots' => $schema['raw_slots'],
                'submit_mode' => 'manual_only',
                'requires_human_confirmation' => true,
            ],
            'confirmation_boundary' => [
                'assistant_execution_enabled' => false,
                'manual_submit_required' => true,
                'safe_write_confirm_required' => true,
                'safe_write_action_key' => $flow['safe_write_action_key'] ?? null,
                'safe_write_stage' => 'confirm',
                'normal_handoff' => $this->handoffForFlow($flow, $schema['payload']),
                'forbidden_auto_submit' => true,
            ],
            'entity_confirmation' => $schema['entity_confirmation'],
            'validation_preview' => [
                'is_valid' => ($preview['is_valid'] ?? false) && empty($missingFields),
                'errors' => $preview['errors'] ?? [],
                'missing_fields' => $missingFields,
                'warnings' => $schema['warnings'],
                'validated_fields' => $preview['validated_fields'] ?? [],
                'validation_source' => $preview['validation_source'] ?? $flow['validation_request'],
            ],
            'refusal' => null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(User $user): array
    {
        return [
            'supported_flows' => $this->flowRegistry->listForUser($user),
            'blocked_flows' => $this->flowRegistry->blockedFlows(),
            'submit_mode' => 'manual_only',
            'assistant_execution_enabled' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $understanding
     * @return array<string, mixed>
     */
    private function understandingSummary(array $understanding): array
    {
        return [
            'outcome' => $understanding['outcome'] ?? 'unsupported',
            'flow_key' => $understanding['flow_key'] ?? null,
            'confidence' => $understanding['confidence'] ?? 'low',
            'explanation' => $understanding['explanation'] ?? '',
            'ambiguities' => $understanding['ambiguities'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $flow
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handoffForFlow(array $flow, array $payload): array
    {
        $handoff = $flow['handoff'];
        $path = $handoff['path'] ?? null;

        if ($path === null && isset($handoff['path_template'])) {
            $path = str_replace(
                '{sales_reservation_id}',
                (string) ($payload['sales_reservation_id'] ?? '{sales_reservation_id}'),
                $handoff['path_template']
            );
        }

        return [
            'method' => $handoff['method'],
            'path' => $path,
            'requires_explicit_user_submit' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function missingRequiredFields(array $flow, array $payload): array
    {
        $missing = [];

        foreach ($flow['required_fields'] as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function auditAndReturn(User $user, string $message, ?string $requestedFlow, array $data): array
    {
        $audit = $this->auditService->record($user, 'assistant_draft_prepare', 'assistant_draft', null, [
            'message' => $message,
            'requested_flow' => $requestedFlow,
        ], [
            'status' => $data['status'] ?? 'unknown',
            'flow_key' => $data['flow']['key'] ?? null,
            'validation_preview' => $data['validation_preview']['is_valid'] ?? null,
        ]);

        $data['audit_entry_id'] = $audit->id;

        return $data;
    }

    private function ensureWithinBudget(User $user): void
    {
        $limit = (int) config('ai_assistant.budgets.per_user_daily_tokens', 0);
        if ($limit <= 0) {
            return;
        }

        $start = Carbon::now()->startOfDay();
        $used = (int) AIConversation::query()
            ->where('user_id', $user->id)
            ->whereNotNull('total_tokens')
            ->where('created_at', '>=', $start)
            ->sum('total_tokens');

        if ($used >= $limit) {
            throw new AiBudgetExceededException($limit, $used);
        }
    }
}
