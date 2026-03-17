<?php

namespace App\Services\AI\Tools;

use App\Models\User;

class SalesAdvisorTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('use-ai-assistant')) {
            return ToolResponse::denied('use-ai-assistant');
        }

        $topic = $args['topic'] ?? 'closing_tips';
        $projectType = $args['project_type'] ?? 'general';
        $closeRate = $args['close_rate'] ?? null;
        $callsPerDay = $args['calls_per_day'] ?? null;
        $visitRate = $args['visit_rate'] ?? null;

        $data = match ($topic) {
            'closing_tips' => $this->closingTips($projectType),
            'objection_handling' => $this->objectionHandling($projectType),
            'follow_up_strategy' => $this->followUpStrategy(),
            'negotiation' => $this->negotiationTips($projectType),
            'performance_diagnosis' => $this->performanceDiagnosis($closeRate, $callsPerDay, $visitRate),
            default => $this->closingTips($projectType),
        };

        $data['topic'] = $topic;
        $data['project_type'] = $projectType;

        return ToolResponse::success('tool_sales_advisor', $args, $data, [
            ['type' => 'tool', 'title' => 'Sales Advisor', 'ref' => 'advisor:sales'],
        ]);
    }

    private function closingTips(string $projectType): array
    {
        $tips = [
            'general' => [
                'استخدم أسلوب "الندرة" — الوحدات المتبقية محدودة',
                'اطرح عرض محدود بوقت لتسريع القرار',
                'ركز على القيمة مقابل المنافسين بدلاً من السعر',
                'اطلب الإغلاق مباشرة بعد معالجة آخر اعتراض',
            ],
            'on_map' => [
                'ركز على ميزة السعر المنخفض مقارنة بالجاهز',
                'اعرض خطة الدفع المرنة بالتفصيل',
                'قدم ضمانات المطور وسمعته',
                'اشرح عوائد الاستثمار المتوقعة عند التسليم',
            ],
            'ready' => [
                'رتب زيارة ميدانية فورية',
                'ركز على الجاهزية الفورية للسكن أو الإيجار',
                'اعرض التشطيبات والمرافق بالصور والفيديو',
                'قدم مقارنة أسعار الإيجار في المنطقة',
            ],
        ];

        return ['tips' => $tips[$projectType] ?? $tips['general']];
    }

    private function objectionHandling(string $projectType): array
    {
        return [
            'common_objections' => [
                [
                    'objection' => 'السعر مرتفع',
                    'response' => 'خلني أوضح لك القيمة اللي تحصلها — لو قارنا بالسوق...',
                    'technique' => 'إعادة تأطير القيمة',
                ],
                [
                    'objection' => 'أحتاج وقت أفكر',
                    'response' => 'طبعاً، بس خلني أوضح لك أن العرض الحالي صالح لمدة...',
                    'technique' => 'خلق إلحاح مشروع',
                ],
                [
                    'objection' => 'الموقع بعيد',
                    'response' => 'المنطقة قادمة بقوة — المشاريع الحكومية القريبة...',
                    'technique' => 'تسليط الضوء على النمو المستقبلي',
                ],
                [
                    'objection' => 'ما عندي سيولة كافية',
                    'response' => 'عندنا خطط تمويل مرنة وتنسيق مباشر مع البنوك...',
                    'technique' => 'تقديم حلول تمويلية',
                ],
            ],
        ];
    }

    private function followUpStrategy(): array
    {
        return [
            'strategy' => [
                ['day' => 1, 'action' => 'رسالة شكر + ملخص العرض', 'channel' => 'WhatsApp'],
                ['day' => 3, 'action' => 'مكالمة متابعة + الرد على استفسارات', 'channel' => 'Phone'],
                ['day' => 7, 'action' => 'إرسال عرض خاص محدود', 'channel' => 'WhatsApp'],
                ['day' => 14, 'action' => 'دعوة لزيارة الموقع', 'channel' => 'Phone'],
                ['day' => 21, 'action' => 'مشاركة قصص نجاح عملاء', 'channel' => 'WhatsApp'],
                ['day' => 30, 'action' => 'عرض أخير + آخر فرصة', 'channel' => 'Phone'],
            ],
            'tips' => [
                'لا تتجاوز 3 محاولات بدون رد — انتقل لقناة أخرى',
                'سجل كل تواصل في النظام',
                'خصص الرسالة حسب اهتمامات العميل',
            ],
        ];
    }

    private function negotiationTips(string $projectType): array
    {
        return [
            'principles' => [
                'لا تبدأ بأقل سعر — اترك مساحة للتفاوض',
                'اسأل عن ميزانية العميل أولاً',
                'قدم خيارات (وحدات مختلفة) بدلاً من خصم مباشر',
                'استخدم "الحزمة" — أضف خدمات بدلاً من تخفيض السعر',
            ],
            'max_discount_guide' => [
                'standard' => 'حتى 3%',
                'bulk_purchase' => 'حتى 7%',
                'cash_payment' => 'حتى 5%',
            ],
        ];
    }

    private function performanceDiagnosis(?float $closeRate, ?int $callsPerDay, ?float $visitRate): array
    {
        $issues = [];
        $recommendations = [];

        if ($closeRate !== null && $closeRate < 5) {
            $issues[] = 'معدل الإغلاق منخفض جداً';
            $recommendations[] = 'تدريب على تقنيات الإغلاق ومعالجة الاعتراضات';
        }

        if ($callsPerDay !== null && $callsPerDay < 20) {
            $issues[] = 'عدد المكالمات اليومية أقل من المطلوب';
            $recommendations[] = 'زيادة المكالمات إلى 30+ يومياً باستخدام قوائم مرتبة';
        }

        if ($visitRate !== null && $visitRate < 10) {
            $issues[] = 'معدل تحويل المكالمات إلى زيارات منخفض';
            $recommendations[] = 'تحسين سكريبت المكالمات وتقديم حوافز للزيارة';
        }

        return [
            'current_metrics' => [
                'close_rate' => $closeRate,
                'calls_per_day' => $callsPerDay,
                'visit_rate' => $visitRate,
            ],
            'benchmarks' => [
                'close_rate' => '5-15%',
                'calls_per_day' => '30+',
                'visit_rate' => '15-25%',
            ],
            'issues_found' => $issues,
            'recommendations' => $recommendations,
            'overall_score' => empty($issues) ? 'جيد' : (count($issues) >= 2 ? 'يحتاج تحسين عاجل' : 'يحتاج تحسين'),
        ];
    }
}
