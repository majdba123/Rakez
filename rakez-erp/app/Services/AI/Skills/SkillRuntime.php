<?php

namespace App\Services\AI\Skills;

use App\Models\User;
use App\Services\AI\CapabilityResolver;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\SectionRegistry;
use App\Services\AI\Skills\Context\SectionContextBuilderRegistry;
use App\Services\AI\Skills\Contracts\OutputFormatterContract;
use App\Services\AI\Skills\Contracts\SkillHandlerContract;
use App\Services\AI\Skills\Scope\RowScopeResolverRegistry;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class SkillRuntime
{
    public function __construct(
        private readonly SkillRegistry $skillRegistry,
        private readonly CapabilityResolver $capabilityResolver,
        private readonly SectionRegistry $sectionRegistry,
        private readonly SectionContextBuilderRegistry $contextBuilders,
        private readonly RowScopeResolverRegistry $rowScopeResolvers,
        private readonly SkillRedactor $redactor,
        private readonly SkillAuditService $auditService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function execute(User $user, string $skillKey, array $input = [], array $options = []): array
    {
        $definition = $this->skillRegistry->find($skillKey);

        if (! $definition) {
            throw new AiAssistantException('Requested skill is not available.', 'ai_validation_failed', 422);
        }

        $correlationId = (string) ($options['correlation_id'] ?? Str::uuid()->toString());

        if (! $user->can('use-ai-assistant')) {
            return $this->finalize(
                $user,
                $definition,
                $input,
                [
                    'status' => 'denied',
                    'answer_markdown' => 'You do not have permission to use the AI assistant.',
                    'confidence' => 'high',
                    'sources' => [],
                    'links' => [],
                    'suggested_actions' => [],
                    'follow_up_questions' => [],
                    'access_notes' => [
                        'had_denied_request' => true,
                        'reason' => 'skill_gate.use_ai_assistant',
                    ],
                    'data' => [],
                ],
                $correlationId,
            );
        }

        if (! $this->skillRegistry->isEnabled($definition)) {
            return $this->finalize(
                $user,
                $definition,
                $input,
                $this->deniedOutput('This skill is currently disabled.', 'skill_feature_flag.disabled'),
                $correlationId,
            );
        }

        $capabilities = $this->capabilityResolver->resolve($user);
        $sectionKey = (string) ($definition['section_key'] ?? 'general');

        if (! $this->passesSectionGate($sectionKey, $capabilities)) {
            return $this->finalize(
                $user,
                $definition,
                $input,
                $this->deniedOutput('Access to this section is not allowed for your account.', 'section_gate.capabilities'),
                $correlationId,
            );
        }

        if (! $this->skillRegistry->hasRequiredPermissions($user, $definition)) {
            return $this->finalize(
                $user,
                $definition,
                $input,
                $this->deniedOutput('You do not have permission to execute this skill.', 'skill_gate.permissions'),
                $correlationId,
            );
        }

        if (! $this->skillRegistry->hasRequiredCapabilities($definition, $capabilities)) {
            return $this->finalize(
                $user,
                $definition,
                $input,
                $this->deniedOutput('Skill capability requirements are not satisfied.', 'skill_gate.capabilities'),
                $correlationId,
            );
        }

        $inputValidation = $this->validateInput($definition, $input);
        if ($inputValidation !== null) {
            return $this->finalize($user, $definition, $input, $inputValidation, $correlationId);
        }

        $rowScopeResolution = $this->resolveRowScope($user, $definition, $input);
        if (($rowScopeResolution['status'] ?? 'error') !== 'ok') {
            return $this->finalize($user, $definition, $input, $rowScopeResolution, $correlationId);
        }

        $normalizedInput = (array) ($rowScopeResolution['normalized_input'] ?? $input);
        $context = $this->buildContext($user, $definition, $capabilities, $normalizedInput);
        if (($context['__denied__'] ?? false) === true) {
            return $this->finalize(
                $user,
                $definition,
                $normalizedInput,
                $this->deniedOutput('Access to context data is denied for this request.', 'context_policy.denied'),
                $correlationId,
            );
        }

        $context['row_scope'] = array_merge(
            (array) ($context['row_scope'] ?? []),
            (array) ($rowScopeResolution['data'] ?? [])
        );

        $handler = $this->resolveHandler($definition);
        $execution = $handler->execute($user, $definition, $normalizedInput, $context);

        $formatter = $this->resolveFormatter($definition);
        $formatted = $formatter->format($definition, $execution, $context, $normalizedInput);

        $profile = (string) ($definition['redaction']['profile'] ?? 'none');
        $redacted = $this->redactor->apply($formatted, $profile);

        return $this->finalize($user, $definition, $normalizedInput, $redacted, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, string>  $capabilities
     */
    private function passesSectionGate(string $sectionKey, array $capabilities): bool
    {
        $section = $this->sectionRegistry->find($sectionKey);
        if (! $section) {
            return false;
        }

        $required = (array) ($section['required_capabilities'] ?? []);

        return empty(array_diff($required, $capabilities));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    private function validateInput(array $definition, array $input): ?array
    {
        $rules = (array) ($definition['input_schema'] ?? []);
        if ($rules === []) {
            return null;
        }

        $validator = Validator::make($input, $rules);
        if (! $validator->fails()) {
            return null;
        }

        $errors = $validator->errors()->toArray();
        $followUps = [];
        foreach ($errors as $field => $messages) {
            $followUps[] = "Please provide a valid value for `{$field}`.";
            if (count($followUps) >= 3) {
                break;
            }
        }

        return [
            'status' => 'needs_input',
            'answer_markdown' => 'Skill input is incomplete or invalid.',
            'confidence' => 'high',
            'sources' => [],
            'links' => [],
            'suggested_actions' => [],
            'follow_up_questions' => $followUps,
            'access_notes' => [
                'had_denied_request' => false,
                'reason' => 'skill_input.validation_failed',
            ],
            'data' => [
                'errors' => $errors,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    private function resolveRowScope(User $user, array $definition, array $input): array
    {
        $rowScope = (array) ($definition['row_scope'] ?? []);
        $mode = (string) ($rowScope['mode'] ?? 'none');

        if ($mode === 'none') {
            return [
                'status' => 'ok',
                'normalized_input' => $input,
                'data' => [],
            ];
        }

        $resolution = $this->rowScopeResolvers->resolve($definition)->resolve($user, $definition, $input);
        $status = (string) ($resolution['status'] ?? 'error');

        if ($status === 'ok') {
            return [
                'status' => 'ok',
                'normalized_input' => (array) ($resolution['normalized_input'] ?? $input),
                'data' => (array) ($resolution['data'] ?? []),
            ];
        }

        return [
            'status' => $status,
            'answer_markdown' => (string) ($resolution['message'] ?? 'Row scope validation failed for this skill request.'),
            'confidence' => $status === 'needs_input' || $status === 'denied' ? 'high' : 'medium',
            'sources' => [],
            'links' => [],
            'suggested_actions' => [],
            'follow_up_questions' => (array) ($resolution['follow_up_questions'] ?? []),
            'access_notes' => [
                'had_denied_request' => $status === 'denied',
                'reason' => (string) ($resolution['reason'] ?? 'row_scope.validation_failed'),
            ],
            'data' => (array) ($resolution['data'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, string>  $capabilities
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function buildContext(User $user, array $definition, array $capabilities, array $input): array
    {
        if (($definition['type'] ?? null) === 'draft') {
            return [];
        }

        $sectionKey = (string) ($definition['section_key'] ?? 'general');
        $builder = $this->contextBuilders->resolve($sectionKey);

        try {
            return $builder->build($user, $capabilities, $input);
        } catch (HttpResponseException $e) {
            $status = $e->getResponse()?->getStatusCode();
            if ($status === 403) {
                return ['__denied__' => true];
            }

            return ['__error__' => true];
        } catch (\Throwable) {
            return ['__error__' => true];
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function resolveHandler(array $definition): SkillHandlerContract
    {
        $handlerClass = (string) ($definition['handler'] ?? '');
        if ($handlerClass === '' || ! class_exists($handlerClass)) {
            throw new AiAssistantException('Skill handler is not configured.', 'ai_validation_failed', 422);
        }

        $handler = app($handlerClass);
        if (! $handler instanceof SkillHandlerContract) {
            throw new AiAssistantException('Skill handler contract is invalid.', 'ai_validation_failed', 422);
        }

        return $handler;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function resolveFormatter(array $definition): OutputFormatterContract
    {
        $formatterClass = (string) ($definition['formatter'] ?? '');
        if ($formatterClass === '' || ! class_exists($formatterClass)) {
            throw new AiAssistantException('Skill formatter is not configured.', 'ai_validation_failed', 422);
        }

        $formatter = app($formatterClass);
        if (! $formatter instanceof OutputFormatterContract) {
            throw new AiAssistantException('Skill formatter contract is invalid.', 'ai_validation_failed', 422);
        }

        return $formatter;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     * @return array<string, mixed>
     */
    private function finalize(User $user, array $definition, array $input, array $output, string $correlationId): array
    {
        $status = (string) ($output['status'] ?? 'error');

        $result = [
            'answer_markdown' => (string) ($output['answer_markdown'] ?? 'No response generated.'),
            'confidence' => (string) ($output['confidence'] ?? $this->defaultConfidence($status)),
            'sources' => (array) ($output['sources'] ?? []),
            'links' => (array) ($output['links'] ?? []),
            'grounding' => (array) ($output['grounding'] ?? [
                'has_sources' => count((array) ($output['sources'] ?? [])) > 0,
                'source_count' => count((array) ($output['sources'] ?? [])),
            ]),
            'suggested_actions' => (array) ($output['suggested_actions'] ?? []),
            'follow_up_questions' => (array) ($output['follow_up_questions'] ?? []),
            'access_notes' => (array) ($output['access_notes'] ?? [
                'had_denied_request' => $status === 'denied',
                'reason' => '',
            ]),
            'skill' => [
                'key' => (string) ($definition['skill_key'] ?? ''),
                'section' => (string) ($definition['section_key'] ?? 'general'),
                'type' => (string) ($definition['type'] ?? 'read'),
                'risk_level' => (string) ($definition['risk_level'] ?? 'low'),
                'status' => $status,
            ],
            'data' => (array) ($output['data'] ?? []),
        ];

        $this->auditService->record($user, $definition, $status, $input, $result, $correlationId);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function deniedOutput(string $message, string $reason): array
    {
        return [
            'status' => 'denied',
            'answer_markdown' => $message,
            'confidence' => 'high',
            'sources' => [],
            'links' => [],
            'suggested_actions' => [],
            'follow_up_questions' => [],
            'access_notes' => [
                'had_denied_request' => true,
                'reason' => $reason,
            ],
            'data' => [],
        ];
    }

    private function defaultConfidence(string $status): string
    {
        return match ($status) {
            'ok' => 'medium',
            'ready', 'denied', 'needs_input' => 'high',
            default => 'low',
        };
    }
}
