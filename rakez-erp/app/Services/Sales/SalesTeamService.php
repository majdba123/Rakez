<?php

namespace App\Services\Sales;

use App\Models\SalesReservation;
use App\Models\SalesTeamMemberRating;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesTeamService
{
    private const RATING_MIN = 1;
    private const RATING_MAX = 5;

    /** أوزان نوع الوحدة للترشيح: فيلا أفضل ثم شقة ثم مكتب ثم محل. */
    public const UNIT_TYPE_WEIGHTS = [
        'فيلا' => 100,
        'villa' => 100,
        'فيلا سكنية' => 95,
        'شقة' => 70,
        'apartment' => 70,
        'شقة سكنية' => 70,
        'مكتب' => 50,
        'office' => 50,
        'محل' => 40,
        'shop' => 40,
        'store' => 40,
    ];

    /** أوزان معايير الترشيح: حجم المبيعات، نسبة الإقفال، جودة الوحدة، الأداء الأخير، تقييم المدير. */
    private const WEIGHT_VOLUME = 0.30;
    private const WEIGHT_CONVERSION = 0.25;
    private const WEIGHT_UNIT_QUALITY = 0.25;
    private const WEIGHT_RECENCY = 0.10;
    private const WEIGHT_LEADER_RATING = 0.10;

    private const RECENCY_DAYS = 90;
    private const DEFAULT_UNIT_WEIGHT = 30;

    /**
     * أعضاء الفريق لمدير معيّن (نفس team_id، نوع sales، باستثناء المدير).
     */
    public function getTeamMembers(User $leader): Collection
    {
        if (!$leader->team_id) {
            return collect([]);
        }

        return User::where('team_id', $leader->team_id)
            ->where('type', 'sales')
            ->where('id', '!=', $leader->id)
            ->with(['team'])
            ->get();
    }

    /**
     * تقييم و/أو تعليق مدير المبيعات على عضو الفريق.
     */
    public function rateMember(User $leader, int $memberId, ?int $rating = null, ?string $comment = null): SalesTeamMemberRating
    {
        $member = $this->ensureMemberInLeaderTeam($leader, $memberId);
        if ($rating !== null && ($rating < self::RATING_MIN || $rating > self::RATING_MAX)) {
            throw new \InvalidArgumentException('التقييم يجب أن يكون بين 1 و 5');
        }

        $record = SalesTeamMemberRating::firstOrNew([
            'leader_id' => $leader->id,
            'member_id' => $memberId,
        ]);

        if ($rating !== null) {
            $record->rating = $rating;
        }
        if ($comment !== null) {
            $record->comment = $comment;
        }
        $record->save();

        return $record;
    }

    /**
     * إخراج عضو من الفريق (إلغاء الانتماء).
     */
    public function removeMemberFromTeam(User $leader, int $memberId): void
    {
        $member = $this->ensureMemberInLeaderTeam($leader, $memberId);
        $member->update(['team_id' => null]);
    }

    /**
     * ترشيح أعضاء الفريق: من يبيع أكثر، نسبة إقفال أعلى، نوع وحدة أفضل (فيلا ثم شقة...)، أداء حديث، وتقييم المدير.
     * يرجع مصفوفة جاهزة للـ API مع نقاط وأسباب الترشيح.
     */
    public function getRecommendations(User $leader): Collection
    {
        $members = $this->getTeamMembers($leader);
        if ($members->isEmpty()) {
            return collect([]);
        }

        $memberIds = $members->pluck('id')->all();
        $stats = $this->getReservationStatsForMembers($memberIds);
        $ratings = $this->getLeaderRatingsKeyedByMember($leader->id, $memberIds);
        $normalized = $this->normalizeStatsForScoring($stats);
        $maxConfirmed = $this->maxOf($stats, 'confirmed');
        $maxRecent = $this->maxOf($stats, 'confirmed_recent_90');
        $bestConversion = $this->bestConversionPercent($stats);
        $bestUnitScore = $this->bestUnitQualityScore($stats);

        $scored = $members->map(function (User $user) use ($stats, $ratings, $normalized, $maxConfirmed, $maxRecent, $bestConversion, $bestUnitScore) {
            $s = $stats[$user->id] ?? $this->defaultMemberStats();
            $rating = $ratings->get($user->id);
            $norm = $normalized[$user->id] ?? [];
            $score = $this->computeRecommendationScore($s, $norm, $rating?->rating);
            $highlights = $this->buildRecommendationHighlights($s, $rating, $maxConfirmed, $maxRecent, $bestConversion, $bestUnitScore);

            return [
                'user' => $user,
                'confirmed_count' => $s['confirmed'],
                'total_reservations' => $s['total'],
                'confirmed_percent' => $this->safePercent($s['confirmed'], $s['total']),
                'confirmed_recent_90' => $s['confirmed_recent_90'],
                'unit_type_avg_score' => $this->safeAvg($s['unit_type_score_sum'], $s['unit_type_count']),
                'recommendation_score' => round($score, 1),
                'recommendation_highlights' => $highlights,
                'leader_rating' => $rating?->rating,
                'leader_rating_comment' => $rating?->comment,
            ];
        });

        return $scored->sortByDesc('recommendation_score')->values();
    }

    /**
     * أعضاء الفريق مع التقييم وعدد الحجوزات المؤكدة (للعرض في القائمة).
     */
    public function getTeamMembersWithRatings(User $leader): Collection
    {
        $members = $this->getTeamMembers($leader);
        if ($members->isEmpty()) {
            return collect([]);
        }

        $memberIds = $members->pluck('id')->all();
        $stats = $this->getReservationStatsForMembers($memberIds);
        $ratings = $this->getLeaderRatingsKeyedByMember($leader->id, $memberIds);

        return $members->map(function (User $user) use ($stats, $ratings) {
            $s = $stats[$user->id] ?? $this->defaultMemberStats();
            $rating = $ratings->get($user->id);
            return [
                'user' => $user,
                'leader_rating' => $rating?->rating,
                'leader_rating_comment' => $rating?->comment,
                'confirmed_reservations_count' => $s['confirmed'],
            ];
        });
    }

    /**
     * شكل موحّد لعضو الفريق للـ API (قائمة أعضاء أو ترشيحات).
     */
    public function memberToApiShape(array $item, bool $includeRecommendationDetails = false): array
    {
        if (!isset($item['user']) || !$item['user'] instanceof User) {
            throw new \InvalidArgumentException('Item must contain a valid user.');
        }
        $user = $item['user'];
        $base = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'team_id' => $user->team_id,
            'leader_rating' => $item['leader_rating'] ?? null,
            'leader_rating_comment' => $item['leader_rating_comment'] ?? null,
            'confirmed_reservations_count' => $item['confirmed_reservations_count'] ?? $item['confirmed_count'] ?? 0,
        ];
        if ($includeRecommendationDetails) {
            $base['total_reservations'] = $item['total_reservations'] ?? 0;
            $base['confirmed_percent'] = $item['confirmed_percent'] ?? 0;
            $base['unit_type_avg_score'] = $item['unit_type_avg_score'] ?? 0;
            $base['recommendation_score'] = $item['recommendation_score'] ?? 0;
            $base['recommendation_highlights'] = $item['recommendation_highlights'] ?? [];
            $base['confirmed_recent_90'] = $item['confirmed_recent_90'] ?? 0;
        }
        return $base;
    }

    // —— Private: بيانات مشتركة (لا تكرار استعلامات) ——

    private function ensureMemberInLeaderTeam(User $leader, int $memberId): User
    {
        if (!$leader->team_id) {
            throw new \RuntimeException('أنت غير منتمٍ لفريق');
        }
        $member = User::where('id', $memberId)->where('team_id', $leader->team_id)->first();
        if (!$member) {
            throw new \RuntimeException('العضو غير موجود في فريقك');
        }
        return $member;
    }

    private function getReservationStatsForMembers(array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $out = [];
        foreach ($memberIds as $id) {
            $out[$id] = $this->defaultMemberStats();
        }

        $recentFrom = Carbon::now()->subDays(self::RECENCY_DAYS)->startOfDay();

        $reservations = SalesReservation::whereIn('marketing_employee_id', $memberIds)
            ->whereIn('status', ['under_negotiation', 'confirmed'])
            ->with('contractUnit:id,unit_type')
            ->get();

        foreach ($reservations as $r) {
            $mid = $r->marketing_employee_id;
            if (!isset($out[$mid])) {
                continue;
            }
            $out[$mid]['total']++;
            if ($r->status === 'confirmed') {
                $out[$mid]['confirmed']++;
                if ($r->confirmed_at && $r->confirmed_at->gte($recentFrom)) {
                    $out[$mid]['confirmed_recent_90']++;
                }
            }
            $unitType = $r->contractUnit?->unit_type;
            if ($unitType !== null && $unitType !== '') {
                $out[$mid]['unit_type_score_sum'] += $this->getUnitTypeWeight($unitType);
                $out[$mid]['unit_type_count']++;
            }
        }

        return $out;
    }

    private function getLeaderRatingsKeyedByMember(int $leaderId, array $memberIds): Collection
    {
        if (empty($memberIds)) {
            return collect([]);
        }
        return SalesTeamMemberRating::where('leader_id', $leaderId)
            ->whereIn('member_id', $memberIds)
            ->get()
            ->keyBy('member_id');
    }

    private function defaultMemberStats(): array
    {
        return [
            'total' => 0,
            'confirmed' => 0,
            'confirmed_recent_90' => 0,
            'unit_type_score_sum' => 0,
            'unit_type_count' => 0,
        ];
    }

    // —— Private: خوارزمية الترشيح ——

    private function normalizeStatsForScoring(array $stats): array
    {
        $maxConfirmed = $this->maxOf($stats, 'confirmed');
        $maxRecent = $this->maxOf($stats, 'confirmed_recent_90');
        $maxUnitScore = 0;
        foreach ($stats as $s) {
            if ($s['unit_type_count'] > 0) {
                $avg = $s['unit_type_score_sum'] / $s['unit_type_count'];
                $maxUnitScore = max($maxUnitScore, $avg);
            }
        }
        $maxUnitScore = $maxUnitScore ?: 1;

        $normalized = [];
        foreach ($stats as $memberId => $s) {
            $unitScore = $this->safeAvg($s['unit_type_score_sum'], $s['unit_type_count']);
            $normalized[$memberId] = [
                'volume' => $maxConfirmed > 0 ? ($s['confirmed'] / $maxConfirmed) * 100 : 0,
                'conversion' => $this->safePercent($s['confirmed'], $s['total']),
                'recency' => $maxRecent > 0 ? ($s['confirmed_recent_90'] / $maxRecent) * 100 : 0,
                'unit_quality' => $maxUnitScore > 0 ? ($unitScore / $maxUnitScore) * 100 : 0,
            ];
        }
        return $normalized;
    }

    private function computeRecommendationScore(array $s, array $norm, ?int $leaderRating): float
    {
        $volume = $norm['volume'] ?? 0;
        $conversion = $norm['conversion'] ?? 0;
        $unitQuality = $norm['unit_quality'] ?? 0;
        $recency = $norm['recency'] ?? 0;
        $ratingScore = $leaderRating !== null
            ? (($leaderRating - self::RATING_MIN) / (self::RATING_MAX - self::RATING_MIN)) * 100
            : 50;

        return $volume * self::WEIGHT_VOLUME
            + $conversion * self::WEIGHT_CONVERSION
            + $unitQuality * self::WEIGHT_UNIT_QUALITY
            + $recency * self::WEIGHT_RECENCY
            + $ratingScore * self::WEIGHT_LEADER_RATING;
    }

    /**
     * أسباب الترشيح بالعربية (لماذا ظهر هذا الموظف في الأعلى).
     */
    private function buildRecommendationHighlights(
        array $s,
        ?SalesTeamMemberRating $rating,
        int $maxConfirmed,
        int $maxRecent,
        float $bestConversion,
        float $bestUnitScore
    ): array {
        $highlights = [];
        $conv = $this->safePercent($s['confirmed'], $s['total']);
        $unitScore = $this->safeAvg($s['unit_type_score_sum'], $s['unit_type_count']);

        if ($s['confirmed'] > 0 && $maxConfirmed > 0 && $s['confirmed'] >= $maxConfirmed) {
            $highlights[] = 'أعلى عدد حجوزات مؤكدة';
        }
        if ($s['total'] >= 3 && $bestConversion > 0 && $conv >= $bestConversion) {
            $highlights[] = 'أعلى نسبة إقفال';
        }
        if ($s['unit_type_count'] > 0 && $bestUnitScore > 0 && $unitScore >= $bestUnitScore * 0.9) {
            $highlights[] = 'يتخصص في وحدات راقية (فيلا/شقة)';
        }
        if ($s['confirmed_recent_90'] > 0 && $maxRecent > 0 && $s['confirmed_recent_90'] >= $maxRecent) {
            $highlights[] = 'أداء قوي في آخر ' . self::RECENCY_DAYS . ' يوم';
        }
        if ($rating !== null && $rating->rating !== null && (int) $rating->rating >= 4) {
            $highlights[] = 'تقييم المدير: ' . $rating->rating . ' نجوم';
        }

        return $highlights;
    }

    private function maxOf(array $stats, string $key): int
    {
        $values = array_column($stats, $key);
        return $values !== [] ? (int) max($values) : 0;
    }

    private function bestConversionPercent(array $stats): float
    {
        $best = 0.0;
        foreach ($stats as $s) {
            if ($s['total'] > 0) {
                $pct = ($s['confirmed'] / $s['total']) * 100;
                $best = max($best, $pct);
            }
        }
        return round($best, 1);
    }

    private function bestUnitQualityScore(array $stats): float
    {
        $best = 0.0;
        foreach ($stats as $s) {
            if ($s['unit_type_count'] > 0) {
                $avg = $s['unit_type_score_sum'] / $s['unit_type_count'];
                $best = max($best, $avg);
            }
        }
        return $best;
    }

    private function safePercent(int $num, int $den): float
    {
        return $den > 0 ? round(($num / $den) * 100, 1) : 0.0;
    }

    private function safeAvg(int $sum, int $count): float
    {
        return $count > 0 ? round($sum / $count, 1) : 0.0;
    }

    private function getUnitTypeWeight(string $unitType): int
    {
        $key = mb_strtolower(trim($unitType));
        foreach (self::UNIT_TYPE_WEIGHTS as $label => $weight) {
            if ($key === mb_strtolower($label) || str_contains($key, mb_strtolower($label))) {
                return $weight;
            }
        }
        return self::DEFAULT_UNIT_WEIGHT;
    }
}
