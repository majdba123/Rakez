<?php

namespace App\Services\AI\Drafts;

use App\Models\User;
use App\Services\AI\AiTextClientManager;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\PromptVersionManager;
use JsonException;

class AssistantDraftUnderstandingService
{
    public function __construct(
        private readonly AiTextClientManager $textClient,
        private readonly AssistantDraftFlowRegistry $flowRegistry,
        private readonly PromptVersionManager $promptVersionManager,
    ) {}

    /**
     * @param  array<int, string>  $allowedFlowKeys
     * @return array<string, mixed>
     */
    public function understand(User $user, string $message, array $allowedFlowKeys, ?string $requestedFlow = null, ?string $provider = null): array
    {
        $definitions = array_values(array_filter(
            array_map(fn (string $key) => $this->flowRegistry->find($key), $allowedFlowKeys)
        ));

        $promptVersion = $this->promptVersionManager->resolve(
            'assistant.draft_understanding',
            $this->buildInstructions($definitions, $requestedFlow),
            $user->id
        );
        $instructions = $promptVersion['content'];
        $response = $this->textClient->createResponse($instructions, [
            ['role' => 'user', 'content' => $message],
        ], [
            'user_id' => $user->id,
            'section' => 'general',
            'service' => 'assistant.draft.understanding',
        ], [
            'model' => $provider === 'anthropic'
                ? config('anthropic.model', 'claude-3-5-sonnet-latest')
                : config('ai_assistant.v2.openai.model', 'gpt-4.1-mini'),
            'temperature' => 0.0,
            'max_output_tokens' => 1200,
            'truncation' => config('ai_assistant.v2.openai.truncation_strategy', 'auto'),
            'response_schema_name' => 'assistant_draft_understanding',
            'response_schema' => $this->outputSchema($allowedFlowKeys),
            'response_schema_strict' => true,
        ], $provider);

        $outputText = $response->text;

        try {
            $parsed = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AiAssistantException('Assistant draft parsing failed.', 'assistant_draft_parse_failed', 500);
        }

        if (! is_array($parsed) || ! isset($parsed['outcome'])) {
            throw new AiAssistantException('Assistant draft understanding was invalid.', 'assistant_draft_parse_failed', 500);
        }

        return $parsed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     */
    private function buildInstructions(array $definitions, ?string $requestedFlow): string
    {
        $lines = [
            'You prepare assistant-assisted form drafts only.',
            'Never submit, save, confirm, approve, reject, modify status, or write to the database.',
            'Your only job is to classify the user request into an allowed draft flow, extract slots, and refuse blocked writes.',
            'If the request asks for auto-submit, hidden writes, direct financial writes, direct contract/project status changes, approvals, rejections, or bulk actions, set outcome=refused.',
            'If the request asks for contract creation/modification, payment/deposit/commission writes, approvals, rejections, project/contract status changes, or bulk actions, set outcome=refused.',
            'If information is missing, set outcome=needs_input and list the missing fields.',
            'If the request is outside the supported flows, set outcome=unsupported.',
            'Do not invent ids. Use raw references when the user mentions names or labels.',
            'Do not treat a guessed person, project, contract, reservation, or marketer as resolved. Leave ids null unless the user explicitly gave an id.',
            'If the user asks for ambiguous actions like "do it", "submit it", or "approve it", refuse rather than guessing the target write.',
            'Prefer explicit missing_fields over optimistic classification when a safe draft still lacks key data.',
        ];

        if ($requestedFlow) {
            $lines[] = "The caller hinted flow '{$requestedFlow}'. Prefer it when the request fits.";
        }

        $lines[] = 'Supported flows:';
        foreach ($definitions as $definition) {
            $lines[] = sprintf(
                '- %s: %s. Required fields: %s. Optional fields: %s.',
                $definition['key'],
                $definition['description'],
                implode(', ', $definition['required_fields']),
                implode(', ', $definition['optional_fields'])
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $allowedFlowKeys
     * @return array<string, mixed>
     */
    private function outputSchema(array $allowedFlowKeys): array
    {
        $allowedEnums = array_values($allowedFlowKeys);
        $allowedEnums[] = null;

        return [
            'type' => 'object',
            'properties' => [
                'outcome' => [
                    'type' => 'string',
                    'enum' => ['draft_supported', 'needs_input', 'refused', 'unsupported'],
                ],
                'flow_key' => [
                    'type' => ['string', 'null'],
                    'enum' => $allowedEnums,
                ],
                'confidence' => [
                    'type' => 'string',
                    'enum' => ['high', 'medium', 'low'],
                ],
                'explanation' => ['type' => 'string'],
                'missing_fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'ambiguities' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'refusal_reason' => [
                    'type' => ['string', 'null'],
                ],
                'raw_slots' => [
                    'type' => 'object',
                    'properties' => [
                        'task_name' => ['type' => ['string', 'null']],
                        'section' => ['type' => ['string', 'null']],
                        'due_at' => ['type' => ['string', 'null']],
                        'lead_name' => ['type' => ['string', 'null']],
                        'lead_contact_info' => ['type' => ['string', 'null']],
                        'lead_source' => ['type' => ['string', 'null']],
                        'lead_status' => ['type' => ['string', 'null']],
                        'lead_notes' => ['type' => ['string', 'null']],
                        'assigned_to_reference' => ['type' => ['string', 'null']],
                        'assigned_to' => ['type' => ['integer', 'null']],
                        'team_id' => ['type' => ['integer', 'null']],
                        'contract_reference' => ['type' => ['string', 'null']],
                        'contract_id' => ['type' => ['integer', 'null']],
                        'project_reference' => ['type' => ['string', 'null']],
                        'project_id' => ['type' => ['integer', 'null']],
                        'marketing_project_id' => ['type' => ['integer', 'null']],
                        'marketer_reference' => ['type' => ['string', 'null']],
                        'marketer_id' => ['type' => ['integer', 'null']],
                        'participating_marketers_count' => ['type' => ['integer', 'null']],
                        'design_link' => ['type' => ['string', 'null']],
                        'design_number' => ['type' => ['string', 'null']],
                        'design_description' => ['type' => ['string', 'null']],
                        'status' => ['type' => ['string', 'null']],
                        'reservation_reference' => ['type' => ['string', 'null']],
                        'sales_reservation_id' => ['type' => ['integer', 'null']],
                        'action_type' => ['type' => ['string', 'null']],
                        'notes' => ['type' => ['string', 'null']],
                    ],
                    'required' => [
                        'task_name',
                        'section',
                        'due_at',
                        'lead_name',
                        'lead_contact_info',
                        'lead_source',
                        'lead_status',
                        'lead_notes',
                        'assigned_to_reference',
                        'assigned_to',
                        'team_id',
                        'contract_reference',
                        'contract_id',
                        'project_reference',
                        'project_id',
                        'marketing_project_id',
                        'marketer_reference',
                        'marketer_id',
                        'participating_marketers_count',
                        'design_link',
                        'design_number',
                        'design_description',
                        'status',
                        'reservation_reference',
                        'sales_reservation_id',
                        'action_type',
                        'notes',
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'required' => [
                'outcome',
                'flow_key',
                'confidence',
                'explanation',
                'missing_fields',
                'ambiguities',
                'refusal_reason',
                'raw_slots',
            ],
            'additionalProperties' => false,
        ];
    }
}
