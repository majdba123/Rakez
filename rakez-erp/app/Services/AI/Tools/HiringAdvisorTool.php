<?php

namespace App\Services\AI\Tools;

use App\Models\User;

/**
 * Static hiring guidance only — not derived from HR records or payroll.
 */
class HiringAdvisorTool implements ToolContract
{
    private const ALLOWED_ROLES = ['sales', 'marketing', 'marketing_leader', 'hr'];

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('hr.dashboard.view')) {
            return ToolResponse::denied('hr.dashboard.view');
        }

        $role = $args['role'] ?? null;
        if (! is_string($role) || $role === '' || ! in_array($role, self::ALLOWED_ROLES, true)) {
            return ToolResponse::invalidArguments(
                'role must be one of: '.implode(', ', self::ALLOWED_ROLES).'.'
            );
        }

        $profiles = [
            'sales' => [
                'focus_areas' => ['تجربة العقارات السعودية', 'التفاوض', 'متابعة العملاء', 'أنظمة التمويل'],
                'interview_questions' => [
                    'كيف تتعامل مع اعتراض العميل على السعر؟',
                    'وصف صفقة سابقة والتحديات التي واجهتها.',
                    'كيف تتابع العملاء المحتملين؟',
                ],
            ],
            'marketing' => [
                'focus_areas' => ['حملات رقمية', 'تحليل أداء', 'إدارة ميزانيات إعلانية'],
                'interview_questions' => [
                    'كيف تقيس فعالية الحملة؟',
                    'كيف توزّع ميزانية على عدة منصات؟',
                ],
            ],
            'marketing_leader' => [
                'focus_areas' => ['قيادة فريق', 'تخطيط', 'مراجعة الأداء'],
                'interview_questions' => [
                    'كيف تبني فريق تسويق من الصفر؟',
                    'كيف تقيّم أداء الفريق؟',
                ],
            ],
            'hr' => [
                'focus_areas' => ['التوظيف', 'السياسات', 'الامتثال'],
                'interview_questions' => [
                    'كيف تتعامل مع حالات الأداء المنخفض؟',
                    'ما خبرتك في إجراءات التوظيف؟',
                ],
            ],
        ];

        $data = [
            'tool_kind' => 'static_knowledge',
            'role' => $role,
            'profile' => $profiles[$role],
            'warnings' => [
                'This output is generic guidance only. It is not sourced from employee records, payroll, or ERP HR metrics.',
                'Do not treat salary ranges, KPI targets, or team sizing as system truth.',
            ],
            'assumptions' => ['No repository-backed HR performance data was queried.'],
            'missing_data' => [
                'erp_hr_metrics',
                'compensation_bands',
                'role_specific_kpis',
            ],
            'summary' => 'Illustrative interview prompts and focus areas for hiring discussions.',
        ];

        return ToolResponse::success('tool_hiring_advisor', $args, $data, [
            ['type' => 'tool', 'title' => 'Hiring guidance (static)', 'ref' => 'advisor:hiring:static'],
        ], [
            'Non-ERP knowledge tool; label clearly in assistant answers.',
        ], 'static_knowledge');
    }
}
