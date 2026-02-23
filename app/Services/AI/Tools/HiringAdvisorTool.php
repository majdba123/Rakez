<?php

namespace App\Services\AI\Tools;

use App\Models\User;

class HiringAdvisorTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('hr.employees.manage') && ! $user->can('hr.teams.manage') && ! $user->can('sales.team.manage')) {
            return ToolResponse::denied('hr.employees.manage');
        }

        $role = $args['role'] ?? 'sales';
        $teamSize = (int) ($args['team_size'] ?? 5);
        $projectCount = (int) ($args['project_count'] ?? 1);

        $inputs = compact('role', 'teamSize', 'projectCount');

        $profile = $this->getIdealProfile($role);
        $interviewQuestions = $this->getInterviewQuestions($role);
        $costEstimate = $this->estimateEmployeeCost($role);
        $teamStructure = $this->suggestTeamStructure($role, $teamSize, $projectCount);
        $kpis = $this->getSuggestedKPIs($role);

        $assumptions = [
            'التكاليف تقديرية بناءً على متوسطات السوق السعودي 2024-2025',
            'الرواتب تشمل: أساسي + سكن + نقل + تأمين + تأمينات اجتماعية',
            'هيكلة الفريق مبنية على معايير صناعة التطوير العقاري',
        ];

        return ToolResponse::success('tool_hiring_advisor', $inputs, [
            'role' => $role,
            'ideal_profile' => $profile,
            'interview_questions' => $interviewQuestions,
            'cost_estimate_monthly_sar' => $costEstimate,
            'suggested_team_structure' => $teamStructure,
            'suggested_kpis' => $kpis,
            'retention_tips' => $this->getRetentionTips($role),
        ], [['type' => 'tool', 'title' => 'Hiring Advisor', 'ref' => 'tool_hiring_advisor']], $assumptions);
    }

    private function getIdealProfile(string $role): array
    {
        $profiles = [
            'sales' => [
                'title' => 'مستشار مبيعات عقارية',
                'experience' => '1-3 سنوات بالمبيعات العقارية',
                'skills' => ['مهارات تواصل ممتازة', 'إقناع وتفاوض', 'معرفة بالسوق العقاري المحلي', 'إجادة CRM', 'لغة إنجليزية أساسية'],
                'personality' => ['طموح', 'صبور', 'يتحمل الضغط', 'اجتماعي', 'منظم'],
                'red_flags' => ['تنقل كثير بين الشركات (أقل من 6 شهور)', 'ما عنده خبرة بالعقار أبداً', 'ما يعرف المنطقة'],
            ],
            'marketing' => [
                'title' => 'أخصائي تسويق رقمي عقاري',
                'experience' => '2-4 سنوات بالتسويق الرقمي',
                'skills' => ['إدارة حملات Google/Meta/Snap', 'تحليل البيانات', 'كتابة محتوى إعلاني', 'تصوير وتصميم أساسي', 'Excel/Google Sheets متقدم'],
                'personality' => ['تحليلي', 'مبدع', 'سريع التعلم', 'يتابع الترندات'],
                'red_flags' => ['ما يقدر يعرض نتائج حملات سابقة', 'ما يعرف يحسب ROI', 'اعتماد كامل على وكالة'],
            ],
            'marketing_leader' => [
                'title' => 'مدير تسويق',
                'experience' => '4-7 سنوات + خبرة إدارة فريق',
                'skills' => ['تخطيط استراتيجي', 'إدارة ميزانيات', 'تحليل بيانات متقدم', 'إدارة فرق', 'علاقات مع وكالات إعلانية'],
                'personality' => ['قيادي', 'استراتيجي', 'يركز على النتائج', 'يطور فريقه'],
                'red_flags' => ['ما عنده خبرة بالعقار', 'ما يقدر يشرح استراتيجية واضحة', 'ما يعرف الأرقام'],
            ],
            'hr' => [
                'title' => 'أخصائي موارد بشرية',
                'experience' => '2-5 سنوات',
                'skills' => ['نظام العمل السعودي', 'إدارة الرواتب', 'التوظيف', 'التأمينات الاجتماعية', 'HRIS Systems'],
                'personality' => ['دقيق', 'سري', 'منظم', 'صبور'],
                'red_flags' => ['ما يعرف نظام العمل', 'ما عنده خبرة بالتأمينات', 'ضعيف بالتوثيق'],
            ],
        ];

        return $profiles[$role] ?? $profiles['sales'];
    }

    private function getInterviewQuestions(string $role): array
    {
        $questions = [
            'sales' => [
                'كم صفقة عقارية أغلقتها بالسنة الماضية؟ وكم كان متوسط قيمتها؟',
                'اعطني مثال على عميل صعب وكيف تعاملت معاه؟',
                'وش تعرف عن السوق العقاري بالمنطقة؟ كم متوسط سعر المتر؟',
                'كيف تتابع عملاءك؟ وش الأدوات اللي تستخدمها؟',
                'لو عندك عميل متردد بين مشروعين، كيف تساعده يقرر؟',
                'وش الفرق بين البيع على الخارطة والجاهز من ناحية استراتيجية البيع؟',
            ],
            'marketing' => [
                'اعطني مثال على حملة ناجحة سويتها. كم كانت الميزانية وكم الليدات؟',
                'كيف تحسب تكلفة الليد وROI؟',
                'وش أفضل قناة إعلانية للعقار بالسعودية حالياً ليش؟',
                'كيف تتعامل مع حملة أداؤها ضعيف بعد أسبوع؟',
                'وش تعرف عن تسويق المشاريع على الخارطة؟',
            ],
            'marketing_leader' => [
                'كيف تبني خطة تسويقية لمشروع عقاري جديد؟',
                'كيف توزع ميزانية 500 ألف ريال على القنوات المختلفة؟',
                'اعطني مثال على فريق بنيته وطورته.',
                'كيف تقيس أداء فريقك؟ وش المعايير؟',
                'كيف تتعامل مع مطور عقاري ما يبي يصرف على التسويق؟',
            ],
            'hr' => [
                'كيف تتعامل مع موظف أداؤه ضعيف لمدة 3 شهور؟',
                'وش خطوات فصل موظف حسب نظام العمل السعودي؟',
                'كيف تحسب مكافأة نهاية الخدمة؟',
                'وش تجربتك مع التأمينات الاجتماعية ومدد؟',
            ],
        ];

        return $questions[$role] ?? $questions['sales'];
    }

    private function estimateEmployeeCost(string $role): array
    {
        $costs = [
            'sales' => ['base_salary' => 5000, 'housing' => 1500, 'transport' => 500, 'insurance' => 400, 'gosi' => 600, 'commission_avg' => 3000, 'total' => 11000],
            'marketing' => ['base_salary' => 7000, 'housing' => 2000, 'transport' => 500, 'insurance' => 400, 'gosi' => 800, 'commission_avg' => 0, 'total' => 10700],
            'marketing_leader' => ['base_salary' => 15000, 'housing' => 3000, 'transport' => 1000, 'insurance' => 500, 'gosi' => 1500, 'commission_avg' => 2000, 'total' => 23000],
            'hr' => ['base_salary' => 8000, 'housing' => 2000, 'transport' => 500, 'insurance' => 400, 'gosi' => 900, 'commission_avg' => 0, 'total' => 11800],
        ];

        return $costs[$role] ?? $costs['sales'];
    }

    private function suggestTeamStructure(string $role, int $teamSize, int $projectCount): array
    {
        if ($role === 'sales') {
            $leadersNeeded = max(1, (int) ceil($teamSize / 5));
            return [
                'total_agents' => $teamSize,
                'team_leaders' => $leadersNeeded,
                'agents_per_leader' => (int) ceil($teamSize / $leadersNeeded),
                'agents_per_project' => $projectCount > 0 ? (int) ceil($teamSize / $projectCount) : $teamSize,
                'note' => "كل قائد فريق يدير 4-6 مستشارين. مع {$projectCount} مشاريع يفضل توزيع الفريق بالتساوي.",
            ];
        }

        if ($role === 'marketing') {
            return [
                'content_creator' => max(1, (int) ceil($projectCount / 2)),
                'ads_specialist' => max(1, (int) ceil($projectCount / 3)),
                'social_media' => 1,
                'team_leader' => $teamSize > 3 ? 1 : 0,
                'note' => 'فريق التسويق المثالي يجمع بين متخصص إعلانات + صانع محتوى + مدير سوشال ميديا.',
            ];
        }

        return ['note' => "فريق من {$teamSize} أشخاص لـ {$projectCount} مشاريع."];
    }

    private function getSuggestedKPIs(string $role): array
    {
        $kpis = [
            'sales' => [
                ['name' => 'عدد المكالمات اليومية', 'target' => '30-50 مكالمة', 'weight' => '15%'],
                ['name' => 'عدد الزيارات المحجوزة', 'target' => '3-5 يومياً', 'weight' => '20%'],
                ['name' => 'عدد الحجوزات الشهرية', 'target' => '4-8 حجوزات', 'weight' => '30%'],
                ['name' => 'نسبة الإغلاق', 'target' => '8-15%', 'weight' => '20%'],
                ['name' => 'قيمة المبيعات', 'target' => 'حسب المشروع', 'weight' => '15%'],
            ],
            'marketing' => [
                ['name' => 'تكلفة الليد', 'target' => 'أقل من 40 ريال', 'weight' => '25%'],
                ['name' => 'عدد الليدات الشهرية', 'target' => 'حسب الميزانية', 'weight' => '25%'],
                ['name' => 'معدل التحويل للزيارة', 'target' => '15-25%', 'weight' => '20%'],
                ['name' => 'ROI الحملات', 'target' => 'أكثر من 300%', 'weight' => '20%'],
                ['name' => 'جودة المحتوى', 'target' => 'تقييم شهري', 'weight' => '10%'],
            ],
        ];

        return $kpis[$role] ?? [];
    }

    private function getRetentionTips(string $role): array
    {
        $tips = [
            'sales' => [
                'نظام عمولات واضح وعادل — أهم عامل لبقاء مستشار المبيعات.',
                'مسار وظيفي واضح: مستشار → مستشار أول → قائد فريق.',
                'تدريب مستمر على المشاريع الجديدة ومهارات البيع.',
                'بيئة عمل إيجابية وتقدير الإنجازات (موظف الشهر).',
                'مرونة بالجدول خصوصاً بالويكند.',
            ],
            'marketing' => [
                'أدوات ومنصات حديثة — الماركتير الشاطر يبي يشتغل بأحدث الأدوات.',
                'ميزانية تجريبية للابتكار والتجربة.',
                'تطوير مهني (كورسات مدفوعة، مؤتمرات).',
                'تقدير النتائج المحققة علنياً.',
            ],
        ];

        return $tips[$role] ?? $tips['sales'];
    }
}
