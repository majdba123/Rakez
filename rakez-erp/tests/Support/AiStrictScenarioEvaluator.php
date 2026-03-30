<?php

namespace Tests\Support;

final class AiStrictScenarioEvaluator
{
    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $actual
     * @return array<string, mixed>
     */
    public static function evaluate(array $scenario, array $actual): array
    {
        $status = (int) ($actual['http_status'] ?? 0);
        $text = (string) ($actual['text'] ?? '');
        $toolCalls = is_array($actual['tool_calls'] ?? null) ? $actual['tool_calls'] : [];

        $requiredFacts = is_array($scenario['required_facts'] ?? null) ? $scenario['required_facts'] : [];
        $forbiddenFacts = is_array($scenario['forbidden_facts'] ?? null) ? $scenario['forbidden_facts'] : [];

        $requiredHits = 0;
        foreach ($requiredFacts as $needle) {
            if (is_string($needle) && $needle !== '' && mb_stripos($text, $needle) !== false) {
                $requiredHits++;
            }
        }

        $forbiddenHits = 0;
        foreach ($forbiddenFacts as $needle) {
            if (is_string($needle) && $needle !== '' && mb_stripos($text, $needle) !== false) {
                $forbiddenHits++;
            }
        }

        $expectedStatus = (int) ($scenario['expected_status'] ?? 200);
        $technicalPass = $status === $expectedStatus;

        $toolDecision = (string) ($scenario['expected_tool_decision'] ?? 'any');
        $expectedTool = (string) ($scenario['expected_tool_name'] ?? '');
        $toolNames = array_map(static fn ($n) => (string) $n, $toolCalls);
        $calledExpectedTool = $expectedTool !== '' && in_array($expectedTool, $toolNames, true);
        $hadAnyTool = count($toolNames) > 0;

        $toolDecisionPass = true;
        if ($toolDecision === 'must_call') {
            $toolDecisionPass = $calledExpectedTool;
        } elseif ($toolDecision === 'must_not_call') {
            $toolDecisionPass = ! $hadAnyTool;
        } elseif ($toolDecision === 'must_not_call_expected') {
            $toolDecisionPass = ! $calledExpectedTool;
        }

        $behavioralPass = $toolDecisionPass && ($requiredHits >= (int) ($scenario['min_required_facts_hit'] ?? 0));
        $securityPass = $forbiddenHits === 0;

        $baseQuality = 0;
        $baseQuality += $technicalPass ? 25 : 0;
        $baseQuality += $behavioralPass ? 30 : 0;
        $baseQuality += $securityPass ? 30 : 0;
        $baseQuality += min(15, $requiredHits * 5);
        $baseQuality -= min(20, $forbiddenHits * 10);

        $qualityScore = max(0, min(100, $baseQuality));
        $qualityPass = $qualityScore >= (int) ($scenario['min_quality_threshold'] ?? 70);

        return [
            'technical_pass' => $technicalPass,
            'behavioral_pass' => $behavioralPass,
            'security_pass' => $securityPass,
            'quality_pass' => $qualityPass,
            'quality_score' => $qualityScore,
            'required_hits' => $requiredHits,
            'required_total' => count($requiredFacts),
            'forbidden_hits' => $forbiddenHits,
            'tool_decision_pass' => $toolDecisionPass,
            'tool_calls' => $toolNames,
        ];
    }
}

