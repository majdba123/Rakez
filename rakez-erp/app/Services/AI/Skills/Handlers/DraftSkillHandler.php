<?php

namespace App\Services\AI\Skills\Handlers;

use App\Models\User;
use App\Services\AI\Drafts\AssistantDraftFlowRegistry;
use App\Services\AI\Drafts\AssistantDraftValidationService;
use App\Services\AI\Skills\Contracts\SkillHandlerContract;

class DraftSkillHandler implements SkillHandlerContract
{
    public function __construct(
        private readonly AssistantDraftFlowRegistry $flowRegistry,
        private readonly AssistantDraftValidationService $validationService,
    ) {}

    public function execute(User $user, array $definition, array $input, array $context): array
    {
        $flowKey = (string) ($definition['draft_flow_key'] ?? '');
        $flow = $flowKey !== '' ? $this->flowRegistry->find($flowKey) : null;

        if (! $flow) {
            return [
                'status' => 'error',
                'message' => 'Draft flow is not configured for this skill.',
                'reason' => 'draft_flow.missing',
            ];
        }

        if (! $user->can((string) $flow['permission'])) {
            return [
                'status' => 'denied',
                'message' => 'This draft flow is not available for your current permissions.',
                'reason' => 'draft_flow.permissions',
                'access_notes' => [
                    'had_denied_request' => true,
                    'reason' => 'draft_flow.permissions',
                ],
            ];
        }

        $payload = $this->buildPayload($flow, $input);
        $preview = $this->validationService->preview($user, $flow, $payload);
        $missingFields = $this->missingRequiredFields($flow, $payload);
        $warnings = $this->draftWarnings($flow, $missingFields);
        $isReady = ($preview['is_valid'] ?? false) && $missingFields === [];

        $resolvedScope = (array) ($context['row_scope'] ?? []);

        return [
            'status' => $isReady ? 'ready' : 'needs_input',
            'message' => $isReady
                ? 'Draft payload is ready for manual review and submit.'
                : 'Draft payload needs additional input before it can be reviewed.',
            'data' => [
                'payload_preview' => $payload,
                'missing_inputs' => $missingFields,
                'resolved_scope' => $resolvedScope,
                'flow' => [
                    'key' => $flow['key'],
                    'label' => $flow['label'],
                    'safe_write_action_key' => $flow['safe_write_action_key'] ?? null,
                    'required_fields' => $flow['required_fields'],
                    'optional_fields' => $flow['optional_fields'],
                    'validation_source' => $flow['validation_request'],
                    'handoff' => $this->handoffForFlow($flow, $payload),
                ],
                'draft' => [
                    'schema_version' => 'v1',
                    'payload' => $payload,
                    'submit_mode' => 'manual_only',
                    'requires_human_confirmation' => true,
                ],
                'confirmation_boundary' => [
                    'assistant_execution_enabled' => false,
                    'manual_submit_required' => true,
                    'safe_write_confirm_required' => true,
                    'safe_write_action_key' => $flow['safe_write_action_key'] ?? null,
                ],
                'validation_preview' => [
                    'is_valid' => $isReady,
                    'errors' => $preview['errors'] ?? [],
                    'missing_fields' => $missingFields,
                    'warnings' => $warnings,
                    'validated_fields' => $preview['validated_fields'] ?? [],
                    'validation_source' => $preview['validation_source'] ?? $flow['validation_request'],
                ],
            ],
            'follow_up_questions' => array_map(
                static fn (string $field): string => "Provide `{$field}` to continue.",
                $missingFields
            ),
            'sources' => [[
                'type' => 'tool',
                'title' => 'Assistant Draft Flow',
                'ref' => 'draft_flow:'.$flow['key'],
            ]],
            'confidence' => 'high',
            'access_notes' => [
                'had_denied_request' => false,
                'reason' => '',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $flow
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handoffForFlow(array $flow, array $payload): array
    {
        $handoff = (array) ($flow['handoff'] ?? []);
        $path = $handoff['path'] ?? null;

        if ($path === null && isset($handoff['path_template'])) {
            $path = str_replace(
                '{sales_reservation_id}',
                (string) ($payload['sales_reservation_id'] ?? '{sales_reservation_id}'),
                (string) $handoff['path_template']
            );
        }

        return [
            'method' => $handoff['method'] ?? 'POST',
            'path' => $path,
            'requires_explicit_user_submit' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $flow
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function buildPayload(array $flow, array $input): array
    {
        $payload = [];
        $fields = array_values(array_unique(array_merge(
            (array) ($flow['required_fields'] ?? []),
            (array) ($flow['optional_fields'] ?? [])
        )));

        foreach ($fields as $field) {
            $field = (string) $field;
            if ($field === '' || ! array_key_exists($field, $input)) {
                continue;
            }

            $payload[$field] = $input[$field];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $flow
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function missingRequiredFields(array $flow, array $payload): array
    {
        $missing = [];

        foreach ((array) ($flow['required_fields'] ?? []) as $field) {
            $field = (string) $field;
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $flow
     * @param  array<int, string>  $missingFields
     * @return array<int, string>
     */
    private function draftWarnings(array $flow, array $missingFields): array
    {
        $warnings = [];

        if ($missingFields !== []) {
            $warnings[] = 'The draft is incomplete and still requires explicit user input.';
        }

        foreach ((array) ($flow['ambiguity_rules'] ?? []) as $rule) {
            if (is_string($rule) && $rule !== '') {
                $warnings[] = $rule;
            }
        }

        return array_slice($warnings, 0, 5);
    }
}
