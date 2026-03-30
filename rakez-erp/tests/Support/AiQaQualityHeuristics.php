<?php

namespace Tests\Support;

/**
 * Heuristic checks for AI reply quality (API responses) — not a substitute for human QA.
 */
final class AiQaQualityHeuristics
{
    /** @var list<string> */
    private const GENERIC_FILLER_PATTERNS = [
        '/\bas an ai\b/i',
        '/\bI cannot provide\b.*\bbut\b/i',
        '/\bفي إطار عام\b/u',
        '/\bبشكل عام جداً\b/u',
    ];

    /**
     * @return array{score: int, flags: list<string>}
     */
    public static function evaluateToolsChatPayload(array $data): array
    {
        $flags = [];
        $score = 0;
        $md = isset($data['answer_markdown']) && is_string($data['answer_markdown'])
            ? trim($data['answer_markdown'])
            : '';

        if ($md === '') {
            $flags[] = 'empty_answer_markdown';

            return ['score' => 0, 'flags' => $flags];
        }

        $score += 2;
        if (mb_strlen($md) >= 40) {
            $score += 1;
        } elseif (preg_match('/\b\d{1,6}\b/', $md)) {
            // Short but possibly a direct numeric answer (e.g. "42")
            $score += 1;
        } else {
            $flags[] = 'very_short_answer';
        }

        $conf = $data['confidence'] ?? null;
        if (in_array($conf, ['high', 'medium', 'low'], true)) {
            $score += 1;
        } else {
            $flags[] = 'missing_or_invalid_confidence';
        }

        foreach (self::GENERIC_FILLER_PATTERNS as $re) {
            if (preg_match($re, $md)) {
                $flags[] = 'possible_generic_filler';
                $score -= 1;
                break;
            }
        }

        $access = $data['access_notes'] ?? null;
        if (is_array($access) && array_key_exists('had_denied_request', $access)) {
            $score += 1;
        } else {
            $flags[] = 'missing_access_notes_shape';
        }

        return ['score' => max(0, min(10, $score)), 'flags' => $flags];
    }

    /**
     * @param  list<string>  $mustContainOneOf
     */
    public static function replyContainsOneOf(string $text, array $mustContainOneOf): bool
    {
        foreach ($mustContainOneOf as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function classifyOutcome(int $httpStatus, bool $successJson, int $qualityScore, array $flags): array
    {
        if ($httpStatus >= 500) {
            return ['9', 'فشل تكاملي'];
        }
        if ($httpStatus === 401 || $httpStatus === 403) {
            return ['6', 'فشل صلاحيات'];
        }
        if ($httpStatus >= 400) {
            return ['1', 'نجاح تقني فقط'];
        }
        if (! $successJson) {
            return ['9', 'فشل تكاملي'];
        }
        if ($qualityScore >= 8 && $flags === []) {
            return ['4', 'نجاح ممتاز عالي الجودة'];
        }
        if ($qualityScore >= 5) {
            return ['3', 'نجاح جيد'];
        }
        if ($qualityScore >= 3) {
            return ['2', 'نجاح وظيفي محدود'];
        }

        return ['5', 'فشل جودة'];
    }
}
