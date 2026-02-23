<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AI\CapabilityResolver;
use App\Services\AI\SectionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use OpenAI\Laravel\Facades\OpenAI;

class AiComprehensiveTest extends Command
{
    protected $signature = 'ai:comprehensive-test {--output=build/ai-comprehensive-test-results.txt}';
    protected $description = 'اختبار شامل لفهم المساعد الذكي للنظام ووصوله للبيانات لكل أنواع المستخدمين';

    private array $results = [];
    private float $totalStart;

    private array $roleLabels = [
        'admin' => 'مدير النظام (Admin)',
        'project_management' => 'إدارة المشاريع (Project Management)',
        'editor' => 'المونتاج/التحرير (Editor)',
        'developer' => 'المطور العقاري (Developer)',
        'marketing' => 'التسويق (Marketing)',
        'sales' => 'المبيعات (Sales)',
        'sales_leader' => 'قائد فريق المبيعات (Sales Leader)',
        'hr' => 'الموارد البشرية (HR)',
        'credit' => 'الائتمان (Credit)',
        'accounting' => 'المحاسبة (Accounting)',
        'inventory' => 'المخزون (Inventory)',
    ];

    private array $testQuestions = [
        'general' => [
            'q' => 'وش الأشياء اللي أقدر أسويها بالنظام؟ اشرح لي بالعربي.',
            'section' => 'general',
            'label' => 'فهم عام للنظام',
        ],
        'contracts' => [
            'q' => 'كيف أنشئ عقد جديد؟ وش الخطوات؟',
            'section' => 'contracts',
            'label' => 'العقود',
            'requires' => 'contracts.view',
        ],
        'units' => [
            'q' => 'كيف أعدل بيانات وحدة عقارية؟',
            'section' => 'units',
            'label' => 'الوحدات',
            'requires' => 'units.view',
        ],
        'dashboard' => [
            'q' => 'اشرح لي مؤشرات لوحة التحكم.',
            'section' => 'dashboard',
            'label' => 'لوحة التحكم',
            'requires' => 'dashboard.analytics.view',
        ],
        'marketing' => [
            'q' => 'كيف أشوف أداء فريق التسويق؟',
            'section' => 'marketing_dashboard',
            'label' => 'لوحة التسويق',
            'requires' => 'marketing.dashboard.view',
        ],
        'notifications' => [
            'q' => 'كيف أتعامل مع الإشعارات؟',
            'section' => 'notifications',
            'label' => 'الإشعارات',
            'requires' => 'notifications.view',
        ],
        'campaign_budget' => [
            'q' => 'عندي ميزانية 50 ألف ريال للتسويق بالرياض لمشروع على الخارطة. كيف أوزعها على القنوات وكم ليد أتوقع؟',
            'section' => 'campaign_advisor',
            'label' => 'نصائح الحملات التسويقية',
            'requires' => 'marketing.dashboard.view',
        ],
        'hiring_sales' => [
            'q' => 'أبي أوظف مستشار مبيعات عقارية. وش المهارات المطلوبة ووش أسأله بالمقابلة وكم راتبه؟',
            'section' => 'hiring_advisor',
            'label' => 'نصائح توظيف المبيعات',
            'requires' => 'hr.employees.manage',
        ],
        'hiring_marketing' => [
            'q' => 'أبي أبني فريق تسويق لـ 3 مشاريع. كم شخص أحتاج ووش التخصصات؟',
            'section' => 'hiring_advisor',
            'label' => 'هيكلة فريق التسويق',
            'requires' => 'hr.employees.manage',
        ],
        'mortgage_calc' => [
            'q' => 'وحدة سعرها مليون ريال، دفعة مقدمة 10%، فائدة 5.5% لمدة 25 سنة. كم القسط الشهري وكم الحد الأدنى للراتب؟',
            'section' => 'credit',
            'label' => 'حاسبة التمويل العقاري',
            'requires' => 'credit.financing.manage',
        ],
        'commission_calc' => [
            'q' => 'بعت وحدة بـ 1.5 مليون ريال. العمولة 2.5% وعندي 3 مستشارين وقائد فريق له 10%. كم يطلع لكل واحد؟',
            'section' => 'accounting',
            'label' => 'حاسبة العمولات',
            'requires' => 'accounting.dashboard.view',
        ],
        'sales_closing' => [
            'q' => 'عندي عميل متردد بين مشروعين. كيف أقنعه وأغلق الصفقة؟ وش أفضل استراتيجيات الإغلاق للعقار؟',
            'section' => 'sales',
            'label' => 'نصائح إغلاق المبيعات',
            'requires' => 'sales.dashboard.view',
        ],
        'objection_handling' => [
            'q' => 'العميل يقول السعر غالي ويبي يفكر. كيف أتعامل مع اعتراضاته؟',
            'section' => 'sales',
            'label' => 'معالجة اعتراضات العملاء',
            'requires' => 'sales.dashboard.view',
        ],
        'channel_comparison' => [
            'q' => 'قارن لي بين قوقل وسناب شات وانستقرام وتيك توك للإعلانات العقارية. أيهم أفضل ولمين؟',
            'section' => 'campaign_advisor',
            'label' => 'مقارنة القنوات الإعلانية',
            'requires' => 'marketing.dashboard.view',
        ],
        'payment_plan' => [
            'q' => 'وحدة بـ 800 ألف ريال. أبي خطة دفع بدون بنك، دفعة مقدمة 15% و12 قسط. اعطني الجدول.',
            'section' => 'credit',
            'label' => 'خطط الدفع المرنة',
            'requires' => 'credit.payment_plan.manage',
        ],
        'hr_kpis' => [
            'q' => 'وش أفضل KPIs لقياس أداء فريق التسويق والمبيعات؟ وكيف أقيّم الموظف بشكل عادل؟',
            'section' => 'hr',
            'label' => 'مؤشرات أداء الموظفين',
            'requires' => 'hr.performance.view',
        ],
        'project_roi' => [
            'q' => 'عندي مشروع 200 وحدة متوسط سعر الوحدة مليون. بعت 80 وحدة. صرفت 2 مليون تسويق و500 ألف تشغيل. كم ROI؟',
            'section' => 'accounting',
            'label' => 'عائد الاستثمار للمشاريع',
            'requires' => 'accounting.dashboard.view',
        ],
    ];

    public function handle(): int
    {
        $this->totalStart = microtime(true);
        $outputPath = base_path($this->option('output'));

        $envKey = $this->readRealKeyFromDotEnv();
        if (! $envKey || $envKey === 'test-fake-key-not-used') {
            $this->error('❌ مفتاح OPENAI_API_KEY غير موجود بملف .env');
            return self::FAILURE;
        }

        Config::set('openai.api_key', $envKey);
        Config::set('ai_assistant.enabled', true);
        Config::set('ai_assistant.budgets.per_user_daily_tokens', 0);
        Config::set('ai_assistant.openai.max_output_tokens', 300);

        app()->forgetInstance('openai');
        app()->forgetInstance(\OpenAI\Client::class);

        $this->info('═══════════════════════════════════════════════');
        $this->info('  اختبار شامل للمساعد الذكي - نظام راكز ERP');
        $this->info('═══════════════════════════════════════════════');
        $this->newLine();

        $roles = array_keys($this->roleLabels);

        foreach ($roles as $role) {
            $this->testRole($role);
        }

        $this->testV2Chat($envKey);

        $this->testSectionsAccess();

        $report = $this->buildReport();

        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($outputPath, $report);

        $this->newLine();
        $this->info("✅ تم حفظ النتائج في: {$outputPath}");
        $totalTime = round(microtime(true) - $this->totalStart, 1);
        $this->info("⏱ الوقت الكلي: {$totalTime} ثانية");

        return self::SUCCESS;
    }

    private function testRole(string $role): void
    {
        $label = $this->roleLabels[$role] ?? $role;
        $this->info("──── اختبار دور: {$label} ────");

        $roleMap = config('ai_capabilities.bootstrap_role_map', []);
        $permissions = $roleMap[$role] ?? $roleMap['default'] ?? [];

        $this->results[$role] = [
            'label' => $label,
            'permissions_count' => count($permissions),
            'tests' => [],
        ];

        foreach ($this->testQuestions as $key => $test) {
            $required = $test['requires'] ?? null;

            if ($required && ! in_array($required, $permissions)) {
                $this->results[$role]['tests'][$key] = [
                    'label' => $test['label'],
                    'status' => 'محظور',
                    'reason' => "ما عنده صلاحية: {$required}",
                    'response' => null,
                    'latency_ms' => 0,
                ];
                $this->line("  ⛔ {$test['label']}: محظور (ما عنده صلاحية {$required})");
                continue;
            }

            $start = microtime(true);
            try {
                $response = $this->callAskEndpoint($test['q'], $test['section'], $permissions);
                $latencyMs = (int) round((microtime(true) - $start) * 1000);
                $message = $response['message'] ?? '';
                $hasContent = strlen($message) > 10;

                $this->results[$role]['tests'][$key] = [
                    'label' => $test['label'],
                    'status' => $hasContent ? 'نجح ✅' : 'فاضي ⚠️',
                    'response' => $message,
                    'latency_ms' => $latencyMs,
                    'tokens' => $response['tokens'] ?? null,
                ];

                $preview = mb_substr($message, 0, 60);
                $this->line("  ✅ {$test['label']}: {$latencyMs}ms - \"{$preview}...\"");
            } catch (\Throwable $e) {
                $latencyMs = (int) round((microtime(true) - $start) * 1000);
                $this->results[$role]['tests'][$key] = [
                    'label' => $test['label'],
                    'status' => 'فشل ❌',
                    'reason' => $e->getMessage(),
                    'response' => null,
                    'latency_ms' => $latencyMs,
                ];
                $this->error("  ❌ {$test['label']}: {$e->getMessage()}");
            }
        }

        $this->newLine();
    }

    private function testV2Chat(string $apiKey): void
    {
        $this->info('──── اختبار المساعد v2 (الأدوات + JSON) ────');

        $start = microtime(true);
        try {
            $response = OpenAI::responses()->create([
                'model' => config('ai_assistant.v2.openai.model', 'gpt-4.1-mini'),
                'instructions' => 'أنت مساعد نظام راكز ERP. رد بالعربي السعودي بشكل مختصر.',
                'input' => [
                    ['role' => 'user', 'content' => 'وش الأقسام الموجودة بالنظام؟ اذكرها بالعربي.'],
                ],
                'max_output_tokens' => 400,
            ]);

            $text = $response->outputText ?? '';
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            $this->results['v2_chat'] = [
                'label' => 'اختبار v2 مباشر',
                'status' => strlen($text) > 10 ? 'نجح ✅' : 'فاضي ⚠️',
                'response' => $text,
                'latency_ms' => $latencyMs,
                'tokens' => [
                    'input' => $response->usage?->inputTokens,
                    'output' => $response->usage?->outputTokens,
                    'total' => $response->usage?->totalTokens,
                ],
            ];

            $preview = mb_substr($text, 0, 80);
            $this->line("  ✅ رد v2: {$latencyMs}ms - \"{$preview}...\"");
        } catch (\Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $start) * 1000);
            $this->results['v2_chat'] = [
                'label' => 'اختبار v2 مباشر',
                'status' => 'فشل ❌',
                'reason' => $e->getMessage(),
                'latency_ms' => $latencyMs,
            ];
            $this->error("  ❌ v2: {$e->getMessage()}");
        }

        $this->newLine();
    }

    private function testSectionsAccess(): void
    {
        $this->info('──── اختبار وصول الأقسام لكل دور ────');

        $sections = config('ai_sections', []);
        $roleMap = config('ai_capabilities.bootstrap_role_map', []);

        $this->results['sections_matrix'] = [];

        foreach ($this->roleLabels as $role => $label) {
            $permissions = $roleMap[$role] ?? $roleMap['default'] ?? [];
            $accessible = [];

            foreach ($sections as $sectionKey => $sectionConfig) {
                $required = $sectionConfig['required_capabilities'] ?? [];
                if (empty($required) || empty(array_diff($required, $permissions))) {
                    $accessible[] = $sectionConfig['label'] ?? $sectionKey;
                }
            }

            $this->results['sections_matrix'][$role] = [
                'label' => $label,
                'accessible_sections' => $accessible,
                'count' => count($accessible),
            ];

            $sectionList = implode('، ', $accessible);
            $this->line("  {$label}: {$sectionList}");
        }

        $this->newLine();
    }

    private function callAskEndpoint(string $question, string $section, array $permissions): array
    {
        $client = app(\App\Services\AI\OpenAIResponsesClient::class);

        $capDescriptions = [];
        $definitions = config('ai_capabilities.definitions', []);
        foreach ($permissions as $perm) {
            if (isset($definitions[$perm])) {
                $capDescriptions[] = $perm . ': ' . $definitions[$perm];
            }
        }

        $instructions = implode("\n", [
            'SYSTEM RULES:',
            'أنت مساعد نظام راكز ERP. ساعد المستخدم يفهم النظام ويشتغل أسرع.',
            'رد بالعربي السعودي.',
            'كن مختصر وواضح. استخدم خطوات لما تشرح.',
            'لا تخترع بيانات.',
            'User capabilities:',
            '- ' . implode("\n- ", $capDescriptions),
        ]);

        $response = $client->createResponse(
            $instructions,
            [['role' => 'user', 'content' => $question]],
            ['section' => $section, 'session_id' => 'comprehensive-test-' . uniqid()]
        );

        $text = '';
        if (isset($response->output) && is_array($response->output)) {
            foreach ($response->output as $output) {
                if (($output->type ?? '') === 'message' && ($output->role ?? '') === 'assistant') {
                    foreach ($output->content ?? [] as $content) {
                        if (($content->type ?? '') === 'output_text') {
                            $text = $content->text ?? '';
                        }
                    }
                }
            }
        }
        if ($text === '') {
            $text = $response->outputText ?? '';
        }

        return [
            'message' => $text,
            'tokens' => [
                'input' => $response->usage?->inputTokens,
                'output' => $response->usage?->outputTokens,
                'total' => $response->usage?->totalTokens,
            ],
        ];
    }

    private function buildReport(): string
    {
        $totalTime = round(microtime(true) - $this->totalStart, 1);
        $date = now()->format('Y-m-d H:i:s');

        $lines = [];
        $lines[] = '╔══════════════════════════════════════════════════════════════╗';
        $lines[] = '║    تقرير الاختبار الشامل للمساعد الذكي - نظام راكز ERP     ║';
        $lines[] = '╚══════════════════════════════════════════════════════════════╝';
        $lines[] = '';
        $lines[] = "📅 التاريخ: {$date}";
        $lines[] = "⏱ الوقت الكلي: {$totalTime} ثانية";
        $lines[] = "🤖 النموذج: " . config('ai_assistant.openai.model', 'gpt-4.1-mini');
        $lines[] = '';

        $lines[] = '═══════════════════════════════════════════════════════════════';
        $lines[] = '  📊 ملخص النتائج';
        $lines[] = '═══════════════════════════════════════════════════════════════';
        $lines[] = '';

        $totalTests = 0;
        $passed = 0;
        $blocked = 0;
        $failed = 0;

        foreach ($this->roleLabels as $role => $label) {
            if (! isset($this->results[$role])) {
                continue;
            }
            foreach ($this->results[$role]['tests'] as $test) {
                $totalTests++;
                if (str_contains($test['status'], '✅')) {
                    $passed++;
                } elseif ($test['status'] === 'محظور') {
                    $blocked++;
                } else {
                    $failed++;
                }
            }
        }

        $lines[] = "  إجمالي الاختبارات: {$totalTests}";
        $lines[] = "  ✅ نجح: {$passed}";
        $lines[] = "  ⛔ محظور (متوقع): {$blocked}";
        $lines[] = "  ❌ فشل: {$failed}";
        $lines[] = '';

        // Per-role details
        foreach ($this->roleLabels as $role => $label) {
            if (! isset($this->results[$role])) {
                continue;
            }

            $roleData = $this->results[$role];
            $lines[] = '═══════════════════════════════════════════════════════════════';
            $lines[] = "  🔑 {$label}";
            $lines[] = "  عدد الصلاحيات: {$roleData['permissions_count']}";
            $lines[] = '═══════════════════════════════════════════════════════════════';
            $lines[] = '';

            foreach ($roleData['tests'] as $key => $test) {
                $lines[] = "  ── {$test['label']} ──";
                $lines[] = "  الحالة: {$test['status']}";

                if (isset($test['reason'])) {
                    $lines[] = "  السبب: {$test['reason']}";
                }

                if (isset($test['latency_ms']) && $test['latency_ms'] > 0) {
                    $lines[] = "  وقت الاستجابة: {$test['latency_ms']}ms";
                }

                if (isset($test['tokens']['total'])) {
                    $lines[] = "  التوكنز: {$test['tokens']['total']}";
                }

                if (! empty($test['response'])) {
                    $lines[] = '  الرد:';
                    $responseLines = explode("\n", $test['response']);
                    foreach ($responseLines as $rl) {
                        $lines[] = '    ' . $rl;
                    }
                }

                $lines[] = '';
            }
        }

        // V2 test
        if (isset($this->results['v2_chat'])) {
            $v2 = $this->results['v2_chat'];
            $lines[] = '═══════════════════════════════════════════════════════════════';
            $lines[] = '  🤖 اختبار المساعد v2 (الأدوات + JSON)';
            $lines[] = '═══════════════════════════════════════════════════════════════';
            $lines[] = '';
            $lines[] = "  الحالة: {$v2['status']}";
            $lines[] = "  وقت الاستجابة: {$v2['latency_ms']}ms";

            if (isset($v2['tokens']['total'])) {
                $lines[] = "  التوكنز: {$v2['tokens']['total']}";
            }

            if (! empty($v2['response'])) {
                $lines[] = '  الرد:';
                foreach (explode("\n", $v2['response']) as $rl) {
                    $lines[] = '    ' . $rl;
                }
            }

            if (isset($v2['reason'])) {
                $lines[] = "  سبب الفشل: {$v2['reason']}";
            }

            $lines[] = '';
        }

        // Sections matrix
        if (isset($this->results['sections_matrix'])) {
            $lines[] = '═══════════════════════════════════════════════════════════════';
            $lines[] = '  📋 مصفوفة وصول الأقسام';
            $lines[] = '═══════════════════════════════════════════════════════════════';
            $lines[] = '';

            foreach ($this->results['sections_matrix'] as $role => $data) {
                $sectionList = implode('، ', $data['accessible_sections']);
                $lines[] = "  {$data['label']}:";
                $lines[] = "    الأقسام المتاحة ({$data['count']}): {$sectionList}";
                $lines[] = '';
            }
        }

        $lines[] = '═══════════════════════════════════════════════════════════════';
        $lines[] = '  نهاية التقرير';
        $lines[] = '═══════════════════════════════════════════════════════════════';

        return implode("\n", $lines);
    }

    private function readRealKeyFromDotEnv(): ?string
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'OPENAI_API_KEY=')) {
                $value = substr($line, strlen('OPENAI_API_KEY='));
                $value = trim($value, '"\'');
                return $value !== '' ? $value : null;
            }
        }

        return null;
    }
}
