<?php

namespace App\Services\AI\Skills\Formatting;

use App\Services\AI\Skills\Contracts\OutputFormatterContract;

class DefaultSkillFormatter implements OutputFormatterContract
{
    public function format(array $definition, array $execution, array $context, array $input): array
    {
        $status = (string) ($execution['status'] ?? 'error');
        $sources = $this->normalizeSources((array) ($execution['sources'] ?? []));

        if (in_array($status, ['denied', 'error', 'needs_input', 'not_found'], true)) {
            return [
                'status' => $status,
                'answer_markdown' => (string) ($execution['message'] ?? 'Unable to complete the requested skill.'),
                'confidence' => $status === 'denied' || $status === 'needs_input' ? 'high' : 'low',
                'sources' => $sources,
                'links' => [],
                'suggested_actions' => (array) ($execution['suggested_actions'] ?? []),
                'follow_up_questions' => (array) ($execution['follow_up_questions'] ?? []),
                'access_notes' => (array) ($execution['access_notes'] ?? [
                    'had_denied_request' => $status === 'denied',
                    'reason' => (string) ($execution['reason'] ?? ''),
                ]),
                'data' => (array) ($execution['data'] ?? []),
                'grounding' => [
                    'has_sources' => count($sources) > 0,
                    'source_count' => count($sources),
                ],
            ];
        }

        $data = (array) ($execution['data'] ?? []);
        $title = (string) ($definition['title'] ?? ($definition['skill_key'] ?? 'Skill Result'));

        $lines = [
            "### {$title}",
            '',
            'The skill was executed successfully using grounded system data.',
        ];

        $scalarCount = 0;
        foreach ($data as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $scalarCount++;
            if ($scalarCount > 8) {
                break;
            }

            $valueText = $value === null ? 'null' : (string) $value;
            $lines[] = "- {$key}: {$valueText}";
        }

        if ($scalarCount === 0) {
            $lines[] = '- Structured output is available under the `data` field.';
        }

        $lines[] = '';
        $lines[] = count($sources) === 0
            ? 'Note: no explicit source references were returned for this result.'
            : 'Source references used: '.count($sources).'.';

        return [
            'status' => 'ok',
            'answer_markdown' => implode("\n", $lines),
            'confidence' => $this->confidenceForSuccess($execution, $sources),
            'sources' => $sources,
            'links' => (array) ($execution['links'] ?? []),
            'suggested_actions' => (array) ($execution['suggested_actions'] ?? []),
            'follow_up_questions' => (array) ($execution['follow_up_questions'] ?? []),
            'access_notes' => (array) ($execution['access_notes'] ?? [
                'had_denied_request' => false,
                'reason' => '',
            ]),
            'data' => $data,
            'grounding' => [
                'has_sources' => count($sources) > 0,
                'source_count' => count($sources),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $execution
     * @param  array<int, array{type:string,title:string,ref:string}>  $sources
     */
    protected function confidenceForSuccess(array $execution, array $sources): string
    {
        if (isset($execution['confidence']) && is_string($execution['confidence']) && $execution['confidence'] !== '') {
            return $execution['confidence'];
        }

        return count($sources) > 0 ? 'high' : 'medium';
    }

    /**
     * @param  array<int, mixed>  $sources
     * @return array<int, array{type:string,title:string,ref:string}>
     */
    protected function normalizeSources(array $sources): array
    {
        $normalized = [];

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }

            $type = (string) ($source['type'] ?? '');
            $title = (string) ($source['title'] ?? '');
            $ref = (string) ($source['ref'] ?? '');

            if ($type === '' || $ref === '') {
                continue;
            }

            $normalized[] = [
                'type' => $type,
                'title' => $title,
                'ref' => $ref,
            ];
        }

        return $normalized;
    }
}
