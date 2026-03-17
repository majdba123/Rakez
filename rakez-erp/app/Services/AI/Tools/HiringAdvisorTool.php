<?php

namespace App\Services\AI\Tools;

use App\Models\User;

class HiringAdvisorTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('hr.view')) {
            return ToolResponse::denied('hr.view');
        }

        $role = $args['role'] ?? null;
        $teamSize = $args['team_size'] ?? null;
        $projectCount = $args['project_count'] ?? null;

        $data = [
            'requested_role' => $role,
            'current_team_size' => $teamSize,
            'project_count' => $projectCount,
        ];

        // Role-specific advice
        $profiles = [
            'sales' => [
                'ideal_profile' => [
                    'experience' => '2-5 سنوات في العقارات السعودية',
                    'skills' => ['التفاوض', 'إدارة العلاقات', 'CRM', 'معرفة أنظمة التمويل'],
                    'certifications' => ['رخصة وساطة عقارية (إيجار/ريجا)'],
                    'salary_range' => '5,000 - 12,000 ريال + عمولات',
                ],
                'interview_questions' => [
                    'كيف تتعامل مع اعتراض العميل على السعر؟',
                    'وصف أكبر صفقة أغلقتها وما التحديات التي واجهتك؟',
                    'كيف تتابع الليدات وتحافظ على معدل تحويل عالي؟',
                    'ما خبرتك بأنظمة التمويل العقاري في السعودية؟',
                ],
                'kpis' => ['معدل التحويل ≥ 8%', 'عدد المكالمات ≥ 30/يوم', 'معدل الزيارات ≥ 15%'],
            ],
            'marketing' => [
                'ideal_profile' => [
                    'experience' => '3+ سنوات تسويق رقمي عقاري',
                    'skills' => ['إدارة حملات Meta/Google/Snap', 'تحليل البيانات', 'Content Creation'],
                    'certifications' => ['Google Ads', 'Meta Blueprint'],
                    'salary_range' => '6,000 - 15,000 ريال',
                ],
                'interview_questions' => [
                    'كيف تحسب ROMI وما معدل العائد المقبول؟',
                    'كيف توزع ميزانية 100K على عدة منصات؟',
                    'ما استراتيجيتك لتقليل CPL مع الحفاظ على الجودة؟',
                ],
                'kpis' => ['CPL ≤ 50 ريال', 'ROMI ≥ 300%', 'Lead Quality Score ≥ 70%'],
            ],
            'marketing_leader' => [
                'ideal_profile' => [
                    'experience' => '5+ سنوات مع خبرة إدارية',
                    'skills' => ['قيادة فريق', 'تخطيط استراتيجي', 'تحليل متقدم', 'إدارة ميزانيات كبيرة'],
                    'salary_range' => '12,000 - 25,000 ريال',
                ],
                'interview_questions' => [
                    'كيف تبني فريق تسويق عقاري من الصفر؟',
                    'كيف تقيم أداء فريقك وتحدد نقاط التحسين؟',
                ],
                'kpis' => ['Team Performance ≥ Target', 'Budget Utilization ≥ 90%'],
            ],
            'hr' => [
                'ideal_profile' => [
                    'experience' => '3+ سنوات موارد بشرية',
                    'skills' => ['نظام العمل السعودي', 'GOSI', 'Mudad', 'التوظيف'],
                    'salary_range' => '5,000 - 12,000 ريال',
                ],
                'interview_questions' => [
                    'كيف تتعامل مع مشاكل الأداء الوظيفي؟',
                    'ما خبرتك بنظام حماية الأجور؟',
                ],
                'kpis' => ['Time to Hire ≤ 30 يوم', 'Employee Retention ≥ 85%'],
            ],
        ];

        $data['profile'] = $profiles[$role] ?? $profiles['sales'];

        // Team sizing recommendations
        if ($projectCount !== null) {
            $recommendedSales = max(2, (int) ceil($projectCount * 2.5));
            $recommendedMarketing = max(1, (int) ceil($projectCount * 0.5));
            $data['team_recommendation'] = [
                'recommended_sales_team' => $recommendedSales,
                'recommended_marketing_team' => $recommendedMarketing,
                'needs_leader' => $recommendedSales >= 5,
                'note' => $teamSize !== null && $teamSize < $recommendedSales
                    ? 'الفريق الحالي أقل من المطلوب — ننصح بالتوظيف'
                    : 'حجم الفريق مناسب',
            ];
        }

        return ToolResponse::success('tool_hiring_advisor', $args, $data, [
            ['type' => 'tool', 'title' => 'Hiring Advisor', 'ref' => 'advisor:hiring'],
        ]);
    }
}
