<?php

namespace Tests\Golden;

use App\Models\User;
use App\Services\AI\CatalogService;
use App\Services\AI\CapabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

abstract class GoldenTestCase extends TestCase
{
    use RefreshDatabase;
    use TestsWithPermissions;

    protected CatalogService $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = new CatalogService(new CapabilityResolver());
    }

    /**
     * Create a user with the given role's permissions from the catalog,
     * using actual Spatie permissions so $user->can() works.
     */
    protected function createUserForRole(string $role): User
    {
        $permissions = $this->catalog->permissionsForRole($role);
        return $this->createUserWithPermissions($permissions, ['type' => $role]);
    }

    /**
     * Get the canonical questions for golden testing.
     */
    protected function canonicalQuestions(): array
    {
        return [
            ['id' => 'Q1', 'text' => 'وش الأقسام اللي أقدر أوصل لها؟', 'type' => 'catalog_query', 'allowed_roles' => ['admin', 'marketing', 'sales', 'hr', 'credit', 'accounting']],
            ['id' => 'Q2', 'text' => 'عندي ميزانية 50 ألف، وش أفضل توزيع للقنوات الإعلانية؟', 'type' => 'tool_query', 'target_tool' => 'tool_campaign_advisor', 'allowed_roles' => ['admin', 'marketing'], 'denied_roles' => ['sales', 'hr', 'credit', 'accounting']],
            ['id' => 'Q3', 'text' => 'وش أسئلة مقابلة مستشار مبيعات؟', 'type' => 'tool_query', 'target_tool' => 'tool_hiring_advisor', 'allowed_roles' => ['admin', 'hr'], 'denied_roles' => ['marketing', 'credit', 'accounting']],
            ['id' => 'Q4', 'text' => 'احسب لي قسط تمويل وحدة بـ مليون ريال', 'type' => 'tool_query', 'target_tool' => 'tool_finance_calculator', 'allowed_roles' => ['admin', 'credit', 'sales', 'accounting'], 'denied_roles' => ['hr']],
            ['id' => 'Q5', 'text' => 'كيف أحسن نسبة الإغلاق عندي؟', 'type' => 'tool_query', 'target_tool' => 'tool_sales_advisor', 'allowed_roles' => ['admin', 'sales'], 'denied_roles' => ['hr', 'credit', 'accounting']],
            ['id' => 'Q6', 'text' => 'قارن لي بين القنوات الإعلانية', 'type' => 'tool_query', 'target_tool' => 'tool_marketing_analytics', 'allowed_roles' => ['admin', 'marketing'], 'denied_roles' => ['hr', 'credit', 'accounting']],
            ['id' => 'Q7', 'text' => 'كيف أوزع العمولات على الفريق؟', 'type' => 'tool_query', 'target_tool' => 'tool_finance_calculator', 'allowed_roles' => ['admin', 'accounting', 'sales'], 'denied_roles' => ['hr']],
            ['id' => 'Q8', 'text' => 'كيف أبني فريق تسويق لـ 3 مشاريع؟', 'type' => 'tool_query', 'target_tool' => 'tool_hiring_advisor', 'allowed_roles' => ['admin', 'hr'], 'denied_roles' => ['credit', 'accounting']],
            ['id' => 'Q9', 'text' => 'وش مراحل التمويل البنكي؟', 'type' => 'general_query', 'allowed_roles' => ['admin', 'credit', 'sales']],
            ['id' => 'Q10', 'text' => 'احسب ROMI لميزانية 100 ألف وبعت 5 وحدات بمليون', 'type' => 'tool_query', 'target_tool' => 'tool_finance_calculator', 'numeric_validation' => true, 'allowed_roles' => ['admin', 'marketing', 'sales']],
            ['id' => 'Q11', 'text' => 'احسب عمولة بيع وحدة بـ 1.5 مليون بنسبة 2.5% مع 3 مستشارين وقائد بنسبة 10%', 'type' => 'tool_query', 'target_tool' => 'tool_finance_calculator', 'numeric_validation' => true, 'allowed_roles' => ['admin', 'accounting', 'sales']],
            ['id' => 'Q12', 'text' => 'وش نصيحتك لمعالجة اعتراض "السعر غالي"؟', 'type' => 'tool_query', 'target_tool' => 'tool_sales_advisor', 'allowed_roles' => ['admin', 'sales'], 'denied_roles' => ['hr', 'credit', 'accounting']],
            ['id' => 'Q13', 'text' => 'وش KPIs مستشار المبيعات؟', 'type' => 'tool_query', 'target_tool' => 'tool_hiring_advisor', 'allowed_roles' => ['admin', 'hr', 'sales']],
            ['id' => 'Q14', 'text' => 'وش استراتيجية المتابعة المثالية للعملاء؟', 'type' => 'tool_query', 'target_tool' => 'tool_sales_advisor', 'allowed_roles' => ['admin', 'sales']],
            ['id' => 'Q15', 'text' => 'احسب لي القسط الشهري لتمويل 800 ألف بفائدة 5.5% لمدة 20 سنة', 'type' => 'tool_query', 'target_tool' => 'tool_finance_calculator', 'numeric_validation' => true, 'allowed_roles' => ['admin', 'credit', 'sales']],
            ['id' => 'Q16', 'text' => 'كيف أقيّم أداء فريق التسويق؟', 'type' => 'tool_query', 'target_tool' => 'tool_marketing_analytics', 'allowed_roles' => ['admin', 'marketing'], 'denied_roles' => ['hr', 'credit']],
            ['id' => 'Q17', 'text' => 'عندي خطة دفع لوحدة بـ 1.2 مليون، دفعة أولى 10%، 24 قسط', 'type' => 'tool_query', 'target_tool' => 'tool_finance_calculator', 'numeric_validation' => true, 'allowed_roles' => ['admin', 'credit', 'sales']],
        ];
    }

    protected function assertNoHallucinatedSections(string $responseText): void
    {
        $fakePatterns = ['قسم التسليم', 'قسم القانون', 'قسم الجودة', 'قسم الصيانة', 'قسم الأمن'];
        foreach ($fakePatterns as $fake) {
            $this->assertStringNotContainsString($fake, $responseText, "Response contains hallucinated section: {$fake}");
        }
    }

    protected function loadSnapshot(string $role): ?array
    {
        $path = __DIR__ . "/snapshots/{$role}.json";
        if (! file_exists($path)) {
            return null;
        }
        return json_decode(file_get_contents($path), true);
    }

    protected function saveSnapshot(string $role, array $data): void
    {
        $path = __DIR__ . "/snapshots/{$role}.json";
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
