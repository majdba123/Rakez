<?php

namespace App\Services\AI\Tools;

use App\Models\Contract;
use App\Models\User;
use App\Services\Sales\AI\EmployeeSuccessScorer;

class EmployeeRecommendationTool implements ToolContract
{
    public function __construct(
        private readonly EmployeeSuccessScorer $scorer,
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('sales.team.manage') && ! $user->hasRole('admin')) {
            return ToolResponse::denied('sales.team.manage');
        }

        $projectId = $args['project_id'] ?? null;
        $projectType = $args['project_type'] ?? null;
        $limit = min((int) ($args['limit'] ?? 5), 20);
        $dateStart = $args['date_start'] ?? now()->subMonths(6)->toDateString();
        $dateEnd = $args['date_end'] ?? now()->toDateString();

        $inputs = compact('projectId', 'projectType', 'limit', 'dateStart', 'dateEnd');

        if ($projectId && ! $projectType) {
            $contract = Contract::find($projectId);
            $projectType = $contract?->is_off_plan ? 'on_map' : 'ready';
        }

        if ($projectType) {
            $recommendations = $this->scorer->recommendForProject($projectType, $limit, $dateStart, $dateEnd);
        } else {
            $recommendations = $this->scorer->scoreAll($dateStart, $dateEnd)->take($limit);
        }

        $employees = $recommendations->map(function ($card) use ($projectType) {
            $warnings = $this->getWarnings($card->userId);

            return [
                'user_id' => $card->userId,
                'name' => $card->userName,
                'composite_score' => $card->compositeScore,
                'rank' => $card->rank,
                'strengths' => $card->strengths,
                'weaknesses' => $card->weaknesses,
                'trend' => $card->trend,
                'project_affinity' => $card->projectTypeAffinity[$projectType] ?? null,
                'factor_scores' => $card->factorScores,
                'warnings' => $warnings,
                'recommendation_reason' => $this->generateReason($card, $projectType),
            ];
        })->values()->toArray();

        $teamSuggestion = $this->suggestTeamComposition($recommendations, $limit);

        return ToolResponse::success('tool_employee_recommendation', $inputs, [
            'recommended_employees' => $employees,
            'team_suggestion' => $teamSuggestion,
            'total_scored' => $this->scorer->scoreAll($dateStart, $dateEnd)->count(),
            'period' => ['start' => $dateStart, 'end' => $dateEnd],
        ], [['type' => 'tool', 'title' => 'Employee Recommendation', 'ref' => 'tool_employee_recommendation']], [
            'التقييم مبني على أداء الموظفين الفعلي من الحجوزات والمبيعات',
            'يتم ترتيب الموظفين حسب النتيجة المركبة من 7 عوامل',
        ]);
    }

    private function getWarnings(int $userId): array
    {
        $warnings = [];
        $employee = User::find($userId);

        if (! $employee) {
            return $warnings;
        }

        if (method_exists($employee, 'isInProbation') && $employee->isInProbation()) {
            $warnings[] = ['type' => 'caution', 'message' => 'الموظف في فترة التجربة'];
        }

        $activeReservations = $employee->salesReservations()
            ->whereNull('cancelled_at')
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($activeReservations > 10) {
            $warnings[] = ['type' => 'overload', 'message' => "الموظف لديه {$activeReservations} حجز نشط — قد يكون مثقلاً"];
        }

        return $warnings;
    }

    private function generateReason($card, ?string $projectType): string
    {
        $reasons = [];

        if ($card->compositeScore >= 70) {
            $reasons[] = 'أداء عالي ومتميز في المبيعات';
        } elseif ($card->compositeScore >= 50) {
            $reasons[] = 'أداء جيد ومستقر';
        }

        if ($card->trend === 'improving') {
            $reasons[] = 'الأداء في تحسن مستمر';
        }

        if ($projectType && isset($card->projectTypeAffinity[$projectType]) && $card->projectTypeAffinity[$projectType] > 0.5) {
            $reasons[] = "نجاح مثبت في مشاريع {$projectType}";
        }

        if (! empty($card->strengths)) {
            $reasons[] = 'نقاط القوة: ' . implode('، ', $card->strengths);
        }

        return implode('. ', $reasons) ?: 'موظف متاح للتعيين';
    }

    private function suggestTeamComposition($recommendations, int $limit): array
    {
        if ($recommendations->count() < 2) {
            return ['suggestion' => 'عدد الموظفين المتاح غير كافٍ لتكوين فريق متنوع'];
        }

        $senior = $recommendations->filter(fn ($c) => $c->compositeScore >= 60)->count();
        $junior = $recommendations->filter(fn ($c) => $c->compositeScore < 60)->count();

        return [
            'senior_count' => $senior,
            'junior_count' => $junior,
            'suggestion' => $senior > 0 && $junior > 0
                ? "مزيج متوازن: {$senior} موظفين ذوي خبرة + {$junior} موظفين جدد للتطوير"
                : 'ننصح بإضافة تنوع في مستويات الخبرة لتحسين أداء الفريق',
        ];
    }
}
