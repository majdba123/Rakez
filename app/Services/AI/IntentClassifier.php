<?php

namespace App\Services\AI;

class IntentClassifier
{
    private CatalogService $catalog;

    public function __construct(CatalogService $catalog)
    {
        $this->catalog = $catalog;
    }

    /**
     * Classify user message intent using rules-first approach.
     *
     * @return array{intent: string, data?: array}
     *   intent: 'catalog_query' | 'tool_query' | 'general_query'
     */
    public function classify(string $message): array
    {
        $normalized = mb_strtolower(trim($message));

        if ($this->isCatalogQuery($normalized)) {
            return ['intent' => 'catalog_query'];
        }

        $toolHint = $this->detectToolHint($normalized);
        if ($toolHint) {
            return ['intent' => 'tool_query', 'data' => ['suggested_tool' => $toolHint]];
        }

        return ['intent' => 'general_query'];
    }

    /** Detect questions about sections, permissions, or what the user can access. */
    private function isCatalogQuery(string $msg): bool
    {
        $patterns = [
            'وش الأقسام',
            'ايش الاقسام',
            'أقسام النظام',
            'اقسام النظام',
            'وش أقدر أوصل',
            'ايش اقدر اوصل',
            'صلاحياتي',
            'صلاحيات المستخدم',
            'وش الصلاحيات',
            'ايش الصلاحيات',
            'what sections',
            'my permissions',
            'what can i access',
            'أقسام متاحة',
            'اقسام متاحة',
            'وش الأقسام المتاحة',
            'وش أقدر أسوي',
        ];

        foreach ($patterns as $pattern) {
            if (mb_strpos($msg, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /** Detect which tool the message is asking about. */
    private function detectToolHint(string $msg): ?string
    {
        $toolPatterns = [
            'tool_campaign_advisor' => ['ميزانية', 'حملة', 'حملات', 'قنوات إعلانية', 'توزيع ميزانية', 'تكلفة الليد', 'إعلانات'],
            'tool_hiring_advisor' => ['توظيف', 'مقابلة', 'مقابلات', 'بناء فريق', 'هيكلة فريق', 'تكلفة موظف', 'أسئلة مقابلة'],
            'tool_finance_calculator' => ['تمويل', 'قسط', 'أقساط', 'عمولة', 'عمولات', 'خطة دفع', 'romi', 'roi', 'تمويل عقاري', 'دفعة أولى'],
            'tool_marketing_analytics' => ['تحليل تسويق', 'مقارنة قنوات', 'أداء الفريق', 'جودة ليدات', 'أداء فريق التسويق'],
            'tool_sales_advisor' => ['إغلاق', 'اعتراض', 'اعتراضات', 'متابعة عملاء', 'تفاوض', 'نسبة إغلاق', 'نصائح بيع', 'نصيحة مبيعات'],
            'tool_kpi_sales' => ['kpi', 'مؤشرات المبيعات', 'أداء المبيعات'],
            'tool_search_records' => ['ابحث عن', 'بحث عن عقد', 'بحث عن ليد'],
        ];

        foreach ($toolPatterns as $tool => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($msg, $keyword) !== false) {
                    return $tool;
                }
            }
        }

        return null;
    }
}
