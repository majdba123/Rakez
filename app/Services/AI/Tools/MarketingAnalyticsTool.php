<?php

namespace App\Services\AI\Tools;

use App\Models\Contract;
use App\Models\Lead;
use App\Models\MarketingTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MarketingAnalyticsTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('marketing.dashboard.view') && ! $user->can('marketing.reports.view')) {
            return ToolResponse::denied('marketing.dashboard.view');
        }

        $reportType = $args['report_type'] ?? 'overview';

        return match ($reportType) {
            'channel_comparison' => $this->channelComparison($user, $args),
            'team_performance' => $this->teamPerformance($user, $args),
            'lead_quality' => $this->leadQualityAnalysis($user, $args),
            default => $this->overview($user, $args),
        };
    }

    private function overview(User $user, array $args): array
    {
        $dateFrom = $args['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $args['date_to'] ?? now()->toDateString();

        $inputs = ['report_type' => 'overview', 'date_from' => $dateFrom, 'date_to' => $dateTo];

        $totalLeads = Lead::query()
            ->when(Gate::forUser($user)->allows('viewAny', Lead::class), fn ($q) => $q)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        $totalTasks = MarketingTask::query()
            ->when(Gate::forUser($user)->allows('viewAny', MarketingTask::class), fn ($q) => $q)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        $completedTasks = MarketingTask::query()
            ->when(Gate::forUser($user)->allows('viewAny', MarketingTask::class), fn ($q) => $q)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'completed')
            ->count();

        $taskCompletionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        $insights = [];
        if ($taskCompletionRate < 70) {
            $insights[] = 'معدل إنجاز المهام أقل من 70% — ننصح بمراجعة توزيع المهام وأعباء العمل.';
        }
        if ($totalLeads > 0 && $totalLeads < 50) {
            $insights[] = 'عدد الليدات منخفض. ننصح بزيادة الميزانية أو تجربة قنوات جديدة.';
        }

        return ToolResponse::success('tool_marketing_analytics', $inputs, [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_leads' => $totalLeads,
            'total_marketing_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'task_completion_rate' => $taskCompletionRate . '%',
            'insights' => $insights,
        ], [['type' => 'tool', 'title' => 'Marketing Analytics', 'ref' => 'tool_marketing_analytics']], [
            'البيانات من قاعدة البيانات للفترة المحددة',
        ]);
    }

    private function channelComparison(User $user, array $args): array
    {
        $inputs = ['report_type' => 'channel_comparison'];

        $channels = [
            'google' => ['name' => 'Google Ads', 'avg_cpl_sar' => 45, 'avg_conversion' => '8-12%', 'best_for' => 'عملاء بنية شراء عالية', 'tip' => 'ركز على كلمات البحث اللي فيها نية شراء مثل "شقق للبيع بالرياض"'],
            'snapchat' => ['name' => 'Snapchat', 'avg_cpl_sar' => 22, 'avg_conversion' => '4-7%', 'best_for' => 'وعي وجمع ليدات بحجم كبير', 'tip' => 'استخدم عروض حصرية وفلاتر جغرافية'],
            'instagram' => ['name' => 'Instagram', 'avg_cpl_sar' => 32, 'avg_conversion' => '5-9%', 'best_for' => 'مشاريع فاخرة ومحتوى بصري', 'tip' => 'ركز على Reels وStories مع CTA واضح'],
            'tiktok' => ['name' => 'TikTok', 'avg_cpl_sar' => 18, 'avg_conversion' => '3-6%', 'best_for' => 'وعي بتكلفة منخفضة جداً', 'tip' => 'فيديوهات جولة بالمشروع 15-30 ثانية'],
            'twitter' => ['name' => 'Twitter/X', 'avg_cpl_sar' => 55, 'avg_conversion' => '2-5%', 'best_for' => 'PR وبناء سمعة', 'tip' => 'أفضل للمحتوى التعليمي وليس الإعلانات المباشرة'],
        ];

        return ToolResponse::success('tool_marketing_analytics', $inputs, [
            'channel_comparison' => $channels,
            'recommendation' => 'للميزانيات المحدودة: سناب شات + تيك توك. للجودة العالية: قوقل + انستقرام. للتغطية الشاملة: مزيج من الأربع.',
        ], [['type' => 'tool', 'title' => 'Channel Comparison', 'ref' => 'tool_marketing_analytics']], [
            'الأرقام متوسطات السوق السعودي — النتائج الفعلية تختلف حسب المشروع والمنطقة',
        ]);
    }

    private function teamPerformance(User $user, array $args): array
    {
        if (! $user->can('marketing.teams.manage')) {
            return ToolResponse::denied('marketing.teams.manage');
        }

        $inputs = ['report_type' => 'team_performance'];

        $kpiFramework = [
            'daily_kpis' => [
                ['name' => 'مهام منجزة', 'target' => '3-5 مهام/يوم', 'weight' => '20%'],
                ['name' => 'محتوى منتج', 'target' => '2-3 قطع/يوم', 'weight' => '15%'],
            ],
            'weekly_kpis' => [
                ['name' => 'ليدات مولدة', 'target' => 'حسب الميزانية', 'weight' => '25%'],
                ['name' => 'تكلفة الليد', 'target' => 'أقل من المعيار', 'weight' => '20%'],
            ],
            'monthly_kpis' => [
                ['name' => 'ROI الحملات', 'target' => '> 300%', 'weight' => '30%'],
                ['name' => 'معدل التحويل', 'target' => '> 10%', 'weight' => '20%'],
                ['name' => 'رضا الفريق المبيعات', 'target' => '> 80%', 'weight' => '10%'],
            ],
            'evaluation_tips' => [
                'قيّم كل موظف بناءً على KPIs محددة وليس انطباعات شخصية.',
                'قارن أداء الموظف بنفسه (الشهر الحالي vs السابق) وليس فقط بالفريق.',
                'اعطِ فرصة تحسين قبل اتخاذ قرارات.',
            ],
        ];

        return ToolResponse::success('tool_marketing_analytics', $inputs, [
            'kpi_framework' => $kpiFramework,
        ], [['type' => 'tool', 'title' => 'Team Performance', 'ref' => 'tool_marketing_analytics']], [
            'إطار KPIs عام — خصصه حسب حجم الفريق والمشاريع',
        ]);
    }

    private function leadQualityAnalysis(User $user, array $args): array
    {
        $inputs = ['report_type' => 'lead_quality'];

        $qualityFramework = [
            'lead_scoring_criteria' => [
                ['criteria' => 'رد على الاتصال', 'score' => '+20', 'note' => 'ليد رد = مهتم أكثر'],
                ['criteria' => 'حدد ميزانية', 'score' => '+25', 'note' => 'عنده قدرة شرائية'],
                ['criteria' => 'حدد منطقة', 'score' => '+15', 'note' => 'يعرف وش يبي'],
                ['criteria' => 'طلب زيارة', 'score' => '+30', 'note' => 'نية شراء عالية جداً'],
                ['criteria' => 'ليد متكرر', 'score' => '+10', 'note' => 'رجع يسأل = مهتم'],
            ],
            'quality_tiers' => [
                ['tier' => 'ساخن (Hot)', 'score' => '70-100', 'action' => 'اتصل خلال 30 دقيقة'],
                ['tier' => 'دافي (Warm)', 'score' => '40-69', 'action' => 'اتصل خلال 4 ساعات'],
                ['tier' => 'بارد (Cold)', 'score' => '0-39', 'action' => 'ضعه بقائمة المتابعة الأسبوعية'],
            ],
        ];

        return ToolResponse::success('tool_marketing_analytics', $inputs, [
            'lead_quality_framework' => $qualityFramework,
        ], [['type' => 'tool', 'title' => 'Lead Quality', 'ref' => 'tool_marketing_analytics']], [
            'إطار تسجيل الليدات — طبقه بالـ CRM لأفضل نتائج',
        ]);
    }
}
