<?php

namespace App\Domain\Marketing\ValueObjects;

final readonly class EmployeeScoreCard
{
    /**
     * @param  array<string, float>  $factorScores
     * @param  string[]  $strengths
     * @param  string[]  $weaknesses
     * @param  array<string, float>  $projectTypeAffinity
     */
    public function __construct(
        public int $userId,
        public string $userName,
        public float $compositeScore,
        public int $rank,
        public array $factorScores,
        public array $strengths,
        public array $weaknesses,
        public string $trend,
        public array $projectTypeAffinity,
        public ?string $periodStart = null,
        public ?string $periodEnd = null,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'composite_score' => round($this->compositeScore, 2),
            'rank' => $this->rank,
            'factor_scores' => $this->factorScores,
            'strengths' => $this->strengths,
            'weaknesses' => $this->weaknesses,
            'trend' => $this->trend,
            'project_type_affinity' => $this->projectTypeAffinity,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
        ];
    }
}
