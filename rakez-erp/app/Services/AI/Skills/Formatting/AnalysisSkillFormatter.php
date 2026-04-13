<?php

namespace App\Services\AI\Skills\Formatting;

class AnalysisSkillFormatter extends DefaultSkillFormatter
{
    public function format(array $definition, array $execution, array $context, array $input): array
    {
        $formatted = parent::format($definition, $execution, $context, $input);

        if (($formatted['status'] ?? 'error') !== 'ok') {
            return $formatted;
        }

        $data = (array) ($formatted['data'] ?? []);
        $title = (string) ($definition['title'] ?? ($definition['skill_key'] ?? 'Analysis'));
        $businessGoal = trim((string) ($definition['business_goal'] ?? ''));
        $summaryLines = [
            "### {$title}",
        ];

        if ($businessGoal !== '') {
            $summaryLines[] = '';
            $summaryLines[] = $businessGoal;
        }

        $highlights = $this->buildHighlights($data);
        if ($highlights !== []) {
            $summaryLines[] = '';
            foreach ($highlights as $highlight) {
                $summaryLines[] = "- {$highlight}";
            }
        }

        $sourceCount = count((array) ($formatted['sources'] ?? []));
        $summaryLines[] = '';
        $summaryLines[] = $sourceCount > 0
            ? "Grounded in {$sourceCount} system source(s)."
            : 'Grounding is limited because the execution returned no explicit sources.';

        $formatted['answer_markdown'] = implode("\n", $summaryLines);
        $formatted['confidence'] = $sourceCount > 0 ? 'high' : 'medium';

        return $formatted;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function buildHighlights(array $data): array
    {
        $highlights = [];

        if (isset($data['confirmation_rate'])) {
            $highlights[] = 'Confirmation rate: '.$data['confirmation_rate'].'%.';
        }

        if (isset($data['total_reservations'])) {
            $highlights[] = 'Total reservations in scope: '.$data['total_reservations'].'.';
        }

        if (isset($data['total_leads'])) {
            $highlights[] = 'Total leads in scope: '.$data['total_leads'].'.';
        }

        if (isset($data['avg_cpl'])) {
            $highlights[] = 'Average CPL: '.$data['avg_cpl'].'.';
        }

        if (isset($data['status'])) {
            $highlights[] = 'Current status: '.$data['status'].'.';
        }

        if (isset($data['reservations_count'])) {
            $highlights[] = 'Reservations linked to the project: '.$data['reservations_count'].'.';
        }

        return array_slice($highlights, 0, 5);
    }
}
