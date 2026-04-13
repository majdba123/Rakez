<?php

namespace App\Services\AI\Skills\Formatting;

class DraftSkillFormatter extends DefaultSkillFormatter
{
    public function format(array $definition, array $execution, array $context, array $input): array
    {
        $status = (string) ($execution['status'] ?? 'error');
        $sources = $this->normalizeSources((array) ($execution['sources'] ?? []));

        if (in_array($status, ['denied', 'error', 'not_found'], true)) {
            return parent::format($definition, $execution, $context, $input);
        }

        $data = (array) ($execution['data'] ?? []);
        $missingFields = (array) data_get($data, 'validation_preview.missing_fields', []);
        $message = $status === 'ready'
            ? 'Draft payload is ready for manual review.'
            : 'Draft payload needs more input before it can be reviewed.';

        $lines = [
            '### '.(string) ($definition['title'] ?? ($definition['skill_key'] ?? 'Draft Skill')),
            '',
            $message,
        ];

        if ($missingFields !== []) {
            $lines[] = '';
            foreach ($missingFields as $field) {
                if (is_string($field) && $field !== '') {
                    $lines[] = "- Missing: `{$field}`";
                }
            }
        }

        $handoffPath = data_get($data, 'flow.handoff.path');
        if (is_string($handoffPath) && $handoffPath !== '') {
            $lines[] = '';
            $lines[] = 'Manual handoff path: `'.$handoffPath.'`';
        }

        return [
            'status' => $status === 'ready' ? 'ready' : 'needs_input',
            'answer_markdown' => implode("\n", $lines),
            'confidence' => 'high',
            'sources' => $sources,
            'links' => [],
            'suggested_actions' => [],
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
}
