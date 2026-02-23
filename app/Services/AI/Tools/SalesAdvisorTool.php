<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\NumericGuardrails;

class SalesAdvisorTool implements ToolContract
{
    public function __construct(
        private readonly NumericGuardrails $guardrails
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('sales.dashboard.view')) {
            return ToolResponse::denied('sales.dashboard.view');
        }

        $topic = $args['topic'] ?? 'closing_tips';

        return match ($topic) {
            'closing_tips' => $this->closingTips($args),
            'objection_handling' => $this->objectionHandling($args),
            'follow_up_strategy' => $this->followUpStrategy($args),
            'negotiation' => $this->negotiationTips($args),
            'performance_diagnosis' => $this->performanceDiagnosis($args),
            default => $this->closingTips($args),
        };
    }

    private function closingTips(array $args): array
    {
        $projectType = $args['project_type'] ?? 'general';
        $inputs = ['topic' => 'closing_tips', 'project_type' => $projectType];

        $tips = [
            'general' => [
                'بناء علاقة ثقة أول — لا تبيع مباشرة، اسأل واسمع.',
                'حدد احتياج العميل بالضبط قبل ما تعرض.',
                'استخدم "الخيار المحدود" — "هالوحدة عليها طلب من 3 عملاء".',
                'اعطِ العميل سبب يقرر اليوم — عرض محدود أو دفعة مخفضة.',
                'لا تتكلم عن السعر قبل ما تبني القيمة.',
            ],
            'on_map' => [
                'ابني ثقة بالمطور — اعرض مشاريع سابقة ناجحة.',
                'وضح تحديثات البناء والجدول الزمني بشفافية.',
                'ركز على ميزة السعر المنخفض مقارنة بالجاهز.',
                'استخدم ضمانات المطور والعقود الموثقة.',
                'وضح خطة الدفع المرنة.',
            ],
            'ready' => [
                'رتب زيارة للموقع — الوحدة الجاهزة تبيع نفسها.',
                'ركز على "تقدر تسكن بكرة" — الإلحاح الطبيعي.',
                'وضح خيارات التمويل المتاحة.',
                'قارن بالإيجار — "بدل ما تدفع إيجار، ادفع قسط لملكك".',
            ],
        ];

        return ToolResponse::success('tool_sales_advisor', $inputs, [
            'topic' => 'closing_tips',
            'project_type' => $projectType,
            'tips' => $tips[$projectType] ?? $tips['general'],
        ], [['type' => 'tool', 'title' => 'Sales Advisor', 'ref' => 'tool_sales_advisor']], [
            'النصائح مبنية على أفضل الممارسات بسوق العقار السعودي',
        ]);
    }

    private function objectionHandling(array $args): array
    {
        $inputs = ['topic' => 'objection_handling'];

        $objections = [
            [
                'objection' => 'السعر غالي',
                'response' => 'أفهم إن المبلغ كبير. لكن لو قارناه بالإيجار لمدة 10 سنوات، تدفع أكثر وما تملك شيء. خلني أوريك خطة الدفع.',
                'technique' => 'إعادة تأطير القيمة',
            ],
            [
                'objection' => 'أبي أفكر',
                'response' => 'طبعاً، القرار مهم. بس خلني أوضح لك — هالعرض متاح لنهاية الأسبوع فقط. ممكن نحجز لك الوحدة 48 ساعة بدون التزام؟',
                'technique' => 'الإلحاح المبرر + خيار آمن',
            ],
            [
                'objection' => 'المشروع ما اكتمل بعد',
                'response' => 'فاهم تخوفك. خلني أوريك تحديثات البناء الأخيرة والضمانات. وأكبر ميزة — السعر الحين أقل 20-30% من سعر الجاهز.',
                'technique' => 'معالجة الخوف بالشفافية',
            ],
            [
                'objection' => 'عندي تمويل قائم',
                'response' => 'عندنا حلول مرنة — خطة دفع بدون بنك، أو نساعدك بنقل التمويل. خلني أحولك لقسم الائتمان يشرحون لك الخيارات.',
                'technique' => 'تقديم بدائل',
            ],
            [
                'objection' => 'عندي خيارات ثانية',
                'response' => 'ممتاز إنك تقارن — هذا قرار ذكي. خلني أعطيك مقارنة واضحة بالأرقام عشان تقرر بناءً على معلومات.',
                'technique' => 'الثقة والشفافية',
            ],
        ];

        return ToolResponse::success('tool_sales_advisor', $inputs, [
            'topic' => 'objection_handling',
            'objections' => $objections,
            'golden_rule' => 'لا تجادل العميل أبداً. اعترف باعتراضه، ثم وجه المحادثة للحل.',
        ], [['type' => 'tool', 'title' => 'Objection Handling', 'ref' => 'tool_sales_advisor']]);
    }

    private function followUpStrategy(array $args): array
    {
        $inputs = ['topic' => 'follow_up_strategy'];

        $strategy = [
            'day_1' => [
                'action' => 'اتصال شكر + تأكيد الاهتمام',
                'script' => 'مرحبا [اسم العميل]، أنا [اسمك] من راكز. شكراً على وقتك اليوم. حبيت أتأكد إنك استلمت كل المعلومات. عندك أي سؤال؟',
            ],
            'day_3' => [
                'action' => 'رسالة واتساب + صور/فيديو الوحدة',
                'script' => 'مرحبا، هذي صور إضافية للوحدة اللي شفناها. لو حابب نرتب زيارة ثانية أو عندك أي سؤال، أنا موجود.',
            ],
            'day_7' => [
                'action' => 'اتصال متابعة + تحديث (لو فيه وحدات انحجزت)',
                'script' => 'مرحبا [اسم العميل]، حبيت أحدثك — انحجزت 3 وحدات من آخر زيارتك. الوحدة اللي عجبتك لسا متاحة. تبي نثبتها لك؟',
            ],
            'day_14' => [
                'action' => 'عرض حصري أو دعوة لحدث',
                'script' => 'عندنا عرض خاص هالأسبوع — دفعة مقدمة مخفضة. حبيت أخبرك قبل ما ينتهي.',
            ],
            'day_30' => [
                'action' => 'تسجيل ملاحظات + تقييم الاهتمام',
                'script' => 'لو العميل ما رد على أي متابعة، سجله كـ "بارد" وخفف المتابعة لمرة بالشهر.',
            ],
        ];

        return ToolResponse::success('tool_sales_advisor', $inputs, [
            'topic' => 'follow_up_strategy',
            'schedule' => $strategy,
            'tips' => [
                'أفضل وقت للاتصال: 10 صباحاً أو 5 مساءً.',
                'واتساب أفضل من الاتصال للمتابعات.',
                'لا تتصل أكثر من مرتين بدون رد — حوّل لواتساب.',
                'سجل كل تواصل بالـ CRM.',
            ],
        ], [['type' => 'tool', 'title' => 'Follow-up Strategy', 'ref' => 'tool_sales_advisor']]);
    }

    private function negotiationTips(array $args): array
    {
        $inputs = ['topic' => 'negotiation'];

        return ToolResponse::success('tool_sales_advisor', $inputs, [
            'topic' => 'negotiation',
            'principles' => [
                'لا تعطِ خصم بدون ما تاخذ شيء — "إذا أخذت هالسعر، تقدر توقع اليوم؟"',
                'ابدأ من سعر أعلى عشان يكون عندك مجال.',
                'استخدم "أنا أحاول لك" — خلّ العميل يحس إنك بجهته.',
                'لا تعطِ كل الخصم مرة وحدة — وزعه على مراحل.',
                'الخصم الأخير يكون صغير جداً عشان يحس العميل إنه وصل لآخر حد.',
            ],
            'discount_framework' => [
                ['scenario' => 'عميل جاد يبي يوقع اليوم', 'max_discount' => '3-5%', 'condition' => 'توقيع فوري'],
                ['scenario' => 'عميل يقارن بمشروع ثاني', 'max_discount' => '1-2%', 'condition' => 'ركز على القيمة المضافة'],
                ['scenario' => 'شراء أكثر من وحدة', 'max_discount' => '5-8%', 'condition' => 'عقد واحد لكل الوحدات'],
            ],
            'red_lines' => [
                'لا تعطِ خصم بدون موافقة المدير.',
                'لا تعد بشيء ما تقدر تنفذه.',
                'لا تذكر تكلفة الشركة الفعلية أبداً.',
            ],
        ], [['type' => 'tool', 'title' => 'Negotiation Tips', 'ref' => 'tool_sales_advisor']]);
    }

    private function performanceDiagnosis(array $args): array
    {
        $closeRate = (float) ($args['close_rate'] ?? 0);
        $callsPerDay = (int) ($args['calls_per_day'] ?? 0);
        $visitRate = (float) ($args['visit_rate'] ?? 0);

        $inputs = compact('closeRate', 'callsPerDay', 'visitRate');

        $diagnosis = [];
        $actions = [];

        if ($callsPerDay < 20) {
            $diagnosis[] = 'عدد المكالمات منخفض جداً — الهدف 30-50 مكالمة/يوم.';
            $actions[] = 'حدد أوقات اتصال ثابتة (10-12 و 4-6) وأغلق كل المشتتات.';
        }

        if ($visitRate < 10) {
            $diagnosis[] = 'معدل تحويل المكالمات لزيارات ضعيف — الهدف 15-25%.';
            $actions[] = 'راجع سكريبت المكالمة. لازم يكون فيه عرض واضح يحفز الزيارة.';
        }

        if ($closeRate < 5) {
            $diagnosis[] = 'نسبة الإغلاق أقل من المعيار (5-15%).';
            $actions[] = 'احتمال المشكلة بعملية البيع نفسها. راجع مهارات العرض والإقناع.';
            $actions[] = 'جرّب ترافق مستشار أول بزيارتين وتلاحظ الفرق.';
        }

        if (empty($diagnosis)) {
            $diagnosis[] = 'الأرقام جيدة! ركز على الاستمرارية والتحسين المستمر.';
        }

        $closeRateCheck = $this->guardrails->validateCloseRate($closeRate);

        $response = ToolResponse::success('tool_sales_advisor', $inputs, [
            'topic' => 'performance_diagnosis',
            'diagnosis' => $diagnosis,
            'action_plan' => $actions,
        ], [['type' => 'tool', 'title' => 'Performance Diagnosis', 'ref' => 'tool_sales_advisor']], [
            'التشخيص مبني على المعايير المرجعية للسوق العقاري السعودي',
        ]);

        return ToolResponse::withGuardrails($response, $closeRateCheck);
    }
}
