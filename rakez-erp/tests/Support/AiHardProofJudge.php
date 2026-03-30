<?php

namespace Tests\Support;

final class AiHardProofJudge
{
    /**
     * @param  array<string, mixed>  $evaluation
     * @param  array<string, mixed>  $proof
     */
    public static function hardProofPass(array $evaluation, array $proof): bool
    {
        $corePass = (bool) ($evaluation['technical_pass'] ?? false)
            && (bool) ($evaluation['behavioral_pass'] ?? false)
            && (bool) ($evaluation['security_pass'] ?? false)
            && (bool) ($evaluation['quality_pass'] ?? false);

        $hasDecisionEvidence = (bool) ($proof['decision_evidence'] ?? false);
        $hasTraceEvidence = (bool) ($proof['trace_evidence'] ?? false);

        return $corePass && $hasDecisionEvidence && $hasTraceEvidence;
    }
}

