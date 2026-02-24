<?php

namespace App\Services\Sales\AI;

use App\Domain\Marketing\ValueObjects\EmployeeScoreCard;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Deposit;
use App\Models\Lead;
use App\Models\SalesReservation;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeSuccessScorer
{
    private const WEIGHTS = [
        'lead_to_reservation' => 0.25,
        'reservation_to_confirmed' => 0.20,
        'confirmed_to_sold' => 0.20,
        'avg_deal_value' => 0.10,
        'commission_collection' => 0.10,
        'target_achievement' => 0.10,
        'client_satisfaction' => 0.05,
    ];

    private const FACTOR_LABELS = [
        'lead_to_reservation' => 'تحويل العملاء المحتملين إلى حجوزات',
        'reservation_to_confirmed' => 'تأكيد الحجوزات',
        'confirmed_to_sold' => 'إتمام البيع (نقل الملكية)',
        'avg_deal_value' => 'متوسط قيمة الصفقة',
        'commission_collection' => 'تحصيل العمولات',
        'target_achievement' => 'تحقيق الأهداف',
        'client_satisfaction' => 'رضا العملاء (نسبة الاسترجاع)',
    ];

    /**
     * Score all active sales employees and return ranked list.
     *
     * @return Collection<int, EmployeeScoreCard>
     */
    public function scoreAll(?string $periodStart = null, ?string $periodEnd = null): Collection
    {
        $employees = User::where('type', 'sales')
            ->whereNull('deleted_at')
            ->get();

        $scores = $employees->map(fn (User $emp) => $this->scoreEmployee($emp, $periodStart, $periodEnd))
            ->filter(fn (?EmployeeScoreCard $card) => $card !== null)
            ->sortByDesc('compositeScore')
            ->values();

        $ranked = $scores->map(function (EmployeeScoreCard $card, int $index) {
            return new EmployeeScoreCard(
                userId: $card->userId,
                userName: $card->userName,
                compositeScore: $card->compositeScore,
                rank: $index + 1,
                factorScores: $card->factorScores,
                strengths: $card->strengths,
                weaknesses: $card->weaknesses,
                trend: $card->trend,
                projectTypeAffinity: $card->projectTypeAffinity,
                periodStart: $card->periodStart,
                periodEnd: $card->periodEnd,
            );
        });

        return $ranked;
    }

    /**
     * Score a single employee.
     */
    public function scoreEmployee(User $employee, ?string $periodStart = null, ?string $periodEnd = null): ?EmployeeScoreCard
    {
        $reservations = SalesReservation::where('marketing_employee_id', $employee->id);
        if ($periodStart) {
            $reservations->where('created_at', '>=', $periodStart);
        }
        if ($periodEnd) {
            $reservations->where('created_at', '<=', $periodEnd);
        }
        $allReservations = $reservations->get();

        if ($allReservations->isEmpty()) {
            return null;
        }

        $factors = $this->calculateFactors($employee, $allReservations, $periodStart, $periodEnd);
        $composite = $this->computeComposite($factors);
        [$strengths, $weaknesses] = $this->identifyStrengthsWeaknesses($factors);
        $trend = $this->computeTrend($employee);
        $affinity = $this->computeProjectTypeAffinity($employee);

        return new EmployeeScoreCard(
            userId: $employee->id,
            userName: $employee->name,
            compositeScore: $composite,
            rank: 0,
            factorScores: $factors,
            strengths: $strengths,
            weaknesses: $weaknesses,
            trend: $trend,
            projectTypeAffinity: $affinity,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
        );
    }

    /**
     * Get top N employees recommended for a project type.
     *
     * @return Collection<int, EmployeeScoreCard>
     */
    public function recommendForProject(string $projectType, int $limit = 5, ?string $periodStart = null, ?string $periodEnd = null): Collection
    {
        $all = $this->scoreAll($periodStart, $periodEnd);

        return $all->sortByDesc(function (EmployeeScoreCard $card) use ($projectType) {
            $affinityBonus = $card->projectTypeAffinity[$projectType] ?? 0;
            return $card->compositeScore + ($affinityBonus * 10);
        })->take($limit)->values();
    }

    private function calculateFactors(User $employee, Collection $reservations, ?string $periodStart, ?string $periodEnd): array
    {
        $leadQuery = Lead::where('assigned_to', $employee->id);
        if ($periodStart) {
            $leadQuery->where('created_at', '>=', $periodStart);
        }
        if ($periodEnd) {
            $leadQuery->where('created_at', '<=', $periodEnd);
        }
        $totalLeads = $leadQuery->count();

        $totalReservations = $reservations->count();
        $confirmed = $reservations->where('status', 'confirmed')->count();
        $cancelled = $reservations->where('status', 'cancelled')->count();

        $soldCount = SalesReservation::where('marketing_employee_id', $employee->id)
            ->whereHas('contractUnit', fn ($q) => $q->where('status', 'sold'))
            ->when($periodStart, fn ($q) => $q->where('created_at', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('created_at', '<=', $periodEnd))
            ->count();

        $leadToReservation = $totalLeads > 0
            ? min(($totalReservations / $totalLeads) * 100, 100)
            : ($totalReservations > 0 ? 50 : 0);

        $reservationToConfirmed = $totalReservations > 0
            ? ($confirmed / $totalReservations) * 100
            : 0;

        $confirmedToSold = $confirmed > 0
            ? ($soldCount / $confirmed) * 100
            : 0;

        $avgDealValue = $this->computeAvgDealValueScore($employee, $periodStart, $periodEnd);
        $commissionCollection = $this->computeCommissionCollectionRate($employee, $periodStart, $periodEnd);
        $targetAchievement = $this->computeTargetAchievementRate($employee, $periodStart, $periodEnd);

        $refundRate = $this->computeRefundRate($employee, $periodStart, $periodEnd);
        $clientSatisfaction = max(0, 100 - ($refundRate * 2));

        return [
            'lead_to_reservation' => round($leadToReservation, 2),
            'reservation_to_confirmed' => round($reservationToConfirmed, 2),
            'confirmed_to_sold' => round($confirmedToSold, 2),
            'avg_deal_value' => round($avgDealValue, 2),
            'commission_collection' => round($commissionCollection, 2),
            'target_achievement' => round($targetAchievement, 2),
            'client_satisfaction' => round($clientSatisfaction, 2),
        ];
    }

    private function computeComposite(array $factors): float
    {
        $score = 0;
        foreach (self::WEIGHTS as $factor => $weight) {
            $score += ($factors[$factor] ?? 0) * $weight;
        }

        return round(min($score, 100), 2);
    }

    private function identifyStrengthsWeaknesses(array $factors): array
    {
        arsort($factors);
        $keys = array_keys($factors);

        $strengths = array_map(
            fn (string $k) => self::FACTOR_LABELS[$k] ?? $k,
            array_slice($keys, 0, 2)
        );

        $weaknesses = array_map(
            fn (string $k) => self::FACTOR_LABELS[$k] ?? $k,
            array_slice($keys, -2)
        );

        return [$strengths, $weaknesses];
    }

    private function computeTrend(User $employee): string
    {
        $threeMonthsAgo = now()->subMonths(3)->toDateString();
        $sixMonthsAgo = now()->subMonths(6)->toDateString();

        $recentCount = SalesReservation::where('marketing_employee_id', $employee->id)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', $threeMonthsAgo)
            ->count();

        $olderCount = SalesReservation::where('marketing_employee_id', $employee->id)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', $sixMonthsAgo)
            ->where('created_at', '<', $threeMonthsAgo)
            ->count();

        if ($olderCount === 0) {
            return $recentCount > 0 ? 'improving' : 'stable';
        }

        $changeRate = ($recentCount - $olderCount) / $olderCount;

        if ($changeRate > 0.15) {
            return 'improving';
        }
        if ($changeRate < -0.15) {
            return 'declining';
        }

        return 'stable';
    }

    private function computeProjectTypeAffinity(User $employee): array
    {
        $reservations = SalesReservation::where('marketing_employee_id', $employee->id)
            ->where('status', 'confirmed')
            ->with('contract')
            ->get();

        $affinity = [];
        $grouped = $reservations->groupBy(fn ($r) => $r->contract?->is_off_plan ? 'on_map' : 'ready');

        foreach ($grouped as $type => $items) {
            $total = SalesReservation::where('marketing_employee_id', $employee->id)
                ->whereHas('contract', function ($q) use ($type) {
                    if ($type === 'on_map') {
                        $q->where('is_off_plan', true);
                    } else {
                        $q->where('is_off_plan', false);
                    }
                })
                ->count();

            $affinity[$type] = $total > 0 ? round($items->count() / max($total, 1), 2) : 0;
        }

        return $affinity;
    }

    private function computeAvgDealValueScore(User $employee, ?string $periodStart, ?string $periodEnd): float
    {
        $query = Commission::whereHas('salesReservation', function ($q) use ($employee) {
            $q->where('marketing_employee_id', $employee->id);
        });
        if ($periodStart) {
            $query->where('created_at', '>=', $periodStart);
        }
        if ($periodEnd) {
            $query->where('created_at', '<=', $periodEnd);
        }

        $avg = $query->avg('final_selling_price');
        if (! $avg) {
            return 0;
        }

        $globalAvg = Commission::when($periodStart, fn ($q) => $q->where('created_at', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('created_at', '<=', $periodEnd))
            ->avg('final_selling_price');

        if (! $globalAvg || $globalAvg == 0) {
            return 50;
        }

        return min(($avg / $globalAvg) * 50, 100);
    }

    private function computeCommissionCollectionRate(User $employee, ?string $periodStart, ?string $periodEnd): float
    {
        $query = CommissionDistribution::where('user_id', $employee->id);
        if ($periodStart) {
            $query->where('created_at', '>=', $periodStart);
        }
        if ($periodEnd) {
            $query->where('created_at', '<=', $periodEnd);
        }

        $total = $query->count();
        $paid = (clone $query)->where('status', 'paid')->count();

        return $total > 0 ? ($paid / $total) * 100 : 0;
    }

    private function computeTargetAchievementRate(User $employee, ?string $periodStart, ?string $periodEnd): float
    {
        $query = SalesTarget::where('marketer_id', $employee->id);
        if ($periodStart) {
            $query->where('start_date', '>=', $periodStart);
        }
        if ($periodEnd) {
            $query->where('end_date', '<=', $periodEnd);
        }

        $total = $query->count();
        $completed = (clone $query)->where('status', 'completed')->count();

        return $total > 0 ? ($completed / $total) * 100 : 0;
    }

    private function computeRefundRate(User $employee, ?string $periodStart, ?string $periodEnd): float
    {
        $reservationIds = SalesReservation::where('marketing_employee_id', $employee->id)
            ->when($periodStart, fn ($q) => $q->where('created_at', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('created_at', '<=', $periodEnd))
            ->pluck('id');

        if ($reservationIds->isEmpty()) {
            return 0;
        }

        $totalDeposits = Deposit::whereIn('sales_reservation_id', $reservationIds)->count();
        $refunded = Deposit::whereIn('sales_reservation_id', $reservationIds)
            ->where('status', 'refunded')
            ->count();

        return $totalDeposits > 0 ? ($refunded / $totalDeposits) * 100 : 0;
    }
}
