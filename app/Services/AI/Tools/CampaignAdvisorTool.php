<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\NumericGuardrails;

class CampaignAdvisorTool implements ToolContract
{
    public function __construct(
        private readonly NumericGuardrails $guardrails
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('marketing.dashboard.view') && ! $user->can('marketing.budgets.manage')) {
            return ToolResponse::denied('marketing.dashboard.view');
        }

        $budget = (float) ($args['budget'] ?? 0);
        $channel = $args['channel'] ?? 'mixed';
        $goal = $args['goal'] ?? 'leads';
        $region = $args['region'] ?? 'الرياض';
        $projectType = $args['project_type'] ?? 'on_map';

        $inputs = compact('budget', 'channel', 'goal', 'region', 'projectType');
        $benchmarks = $this->getMarketBenchmarks($channel, $region, $projectType);

        $estimatedLeads = $budget > 0 ? (int) floor($budget / $benchmarks['avg_cpl']) : 0;
        $estimatedBookings = (int) floor($estimatedLeads * $benchmarks['avg_conversion_rate']);
        $estimatedRevenue = $estimatedBookings * $benchmarks['avg_unit_price'];
        $estimatedRoi = $budget > 0 ? round((($estimatedRevenue - $budget) / $budget) * 100, 1) : 0;

        $recommendations = $this->generateRecommendations($channel, $budget, $benchmarks, $projectType);

        $assumptions = [
            "تكلفة الليد المقدرة: {$benchmarks['avg_cpl']} ريال (متوسط السوق)",
            "نسبة التحويل: " . ($benchmarks['avg_conversion_rate'] * 100) . '%',
            'الأرقام تقديرية بناءً على معايير السوق السعودي',
        ];

        $response = ToolResponse::success('tool_campaign_advisor', $inputs, [
            'benchmarks' => $benchmarks,
            'estimates' => [
                'estimated_leads' => $estimatedLeads,
                'estimated_bookings' => $estimatedBookings,
                'estimated_revenue_sar' => $estimatedRevenue,
                'estimated_romi_percent' => $estimatedRoi,
            ],
            'recommendations' => $recommendations,
            'budget_distribution' => $this->suggestBudgetDistribution($budget, $channel),
        ], [['type' => 'tool', 'title' => 'Campaign Advisor', 'ref' => 'tool_campaign_advisor']], $assumptions);

        $cplCheck = $this->guardrails->validateCPL($benchmarks['avg_cpl'], $channel, $region);
        $romiCheck = $this->guardrails->validateROI($estimatedRoi, 'romi');

        return ToolResponse::withGuardrails($response, $cplCheck, $romiCheck);
    }

    private function getMarketBenchmarks(string $channel, string $region, string $projectType): array
    {
        $cplMap = [
            'google' => ['الرياض' => 45, 'جدة' => 40, 'الدمام' => 35, 'default' => 38],
            'snapchat' => ['الرياض' => 25, 'جدة' => 22, 'الدمام' => 18, 'default' => 22],
            'instagram' => ['الرياض' => 35, 'جدة' => 30, 'الدمام' => 25, 'default' => 28],
            'tiktok' => ['الرياض' => 20, 'جدة' => 18, 'الدمام' => 15, 'default' => 18],
            'mixed' => ['الرياض' => 32, 'جدة' => 28, 'الدمام' => 22, 'default' => 27],
        ];

        $conversionMap = [
            'on_map' => 0.06,
            'ready' => 0.10,
            'exclusive' => 0.12,
        ];

        $priceMap = [
            'on_map' => 850000,
            'ready' => 1200000,
            'exclusive' => 1500000,
        ];

        $channelData = $cplMap[$channel] ?? $cplMap['mixed'];
        $avgCpl = $channelData[$region] ?? $channelData['default'];

        return [
            'avg_cpl' => $avgCpl,
            'avg_conversion_rate' => $conversionMap[$projectType] ?? 0.08,
            'avg_unit_price' => $priceMap[$projectType] ?? 1000000,
            'market_avg_cpl_range' => '15-150 ريال',
            'good_conversion_rate' => '5-15%',
        ];
    }

    private function generateRecommendations(string $channel, float $budget, array $benchmarks, string $projectType): array
    {
        $recs = [];

        if ($budget < 5000) {
            $recs[] = 'الميزانية منخفضة. ننصح بالتركيز على قناة واحدة بدل التشتت.';
            $recs[] = 'سناب شات أو تيك توك أفضل للميزانيات الصغيرة بسبب تكلفة الليد المنخفضة.';
        } elseif ($budget < 30000) {
            $recs[] = 'ميزانية متوسطة. ننصح بقناتين كحد أقصى مع التركيز على الأداء.';
        } else {
            $recs[] = 'ميزانية جيدة. وزع على 3-4 قنوات واستخدم A/B testing لتحسين الأداء.';
        }

        if ($channel === 'google') {
            $recs[] = 'Google Ads ممتاز للعملاء اللي عندهم نية شراء حقيقية. ركز على كلمات مثل "شقق للبيع" + اسم المنطقة.';
        } elseif ($channel === 'snapchat') {
            $recs[] = 'سناب شات ممتاز للوعي وجمع الليدات بتكلفة منخفضة. استخدم عروض حصرية لتحفيز التسجيل.';
        } elseif ($channel === 'tiktok') {
            $recs[] = 'تيك توك أرخص قناة حالياً. استخدم فيديوهات جولة بالمشروع مدتها 15-30 ثانية.';
        }

        if ($projectType === 'on_map') {
            $recs[] = 'مشاريع على الخارطة تحتاج محتوى يبني ثقة (تحديثات بناء، ضمانات المطور).';
        } elseif ($projectType === 'ready') {
            $recs[] = 'مشاريع جاهزة تبيع أسرع. ركز على الصور الحقيقية وجولات الفيديو.';
        }

        return $recs;
    }

    private function suggestBudgetDistribution(float $budget, string $channel): array
    {
        if ($channel !== 'mixed' || $budget < 10000) {
            return [];
        }

        return [
            ['channel' => 'Google Ads', 'percent' => 35, 'amount' => round($budget * 0.35)],
            ['channel' => 'Snapchat', 'percent' => 25, 'amount' => round($budget * 0.25)],
            ['channel' => 'Instagram', 'percent' => 20, 'amount' => round($budget * 0.20)],
            ['channel' => 'TikTok', 'percent' => 15, 'amount' => round($budget * 0.15)],
            ['channel' => 'محتوى وإنتاج', 'percent' => 5, 'amount' => round($budget * 0.05)],
        ];
    }
}
