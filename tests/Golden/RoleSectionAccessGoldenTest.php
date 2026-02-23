<?php

namespace Tests\Golden;

use App\Services\AI\Tools\CampaignAdvisorTool;
use App\Services\AI\Tools\FinanceCalculatorTool;
use App\Services\AI\Tools\HiringAdvisorTool;
use App\Services\AI\Tools\MarketingAnalyticsTool;
use App\Services\AI\Tools\SalesAdvisorTool;
use App\Services\AI\NumericGuardrails;
use PHPUnit\Framework\Attributes\DataProvider;

class RoleSectionAccessGoldenTest extends GoldenTestCase
{
    #[DataProvider('roleProvider')]
    public function test_role_has_expected_sections(string $role): void
    {
        $snapshot = $this->loadSnapshot($role);
        $this->assertNotNull($snapshot, "Snapshot for role '{$role}' not found.");

        $catalogSections = $this->catalog->sectionsForRole($role);
        $expectedAllowed = $snapshot['allowed_sections'] ?? [];

        foreach ($expectedAllowed as $section) {
            $this->assertArrayHasKey($section, $catalogSections, "Role '{$role}' should have section '{$section}'.");
        }

        $expectedDenied = $snapshot['denied_sections'] ?? [];
        foreach ($expectedDenied as $section) {
            $this->assertArrayNotHasKey($section, $catalogSections, "Role '{$role}' should NOT have section '{$section}'.");
        }
    }

    #[DataProvider('roleProvider')]
    public function test_role_sections_are_all_valid_catalog_entries(string $role): void
    {
        $catalogSections = $this->catalog->sectionsForRole($role);

        foreach (array_keys($catalogSections) as $sectionKey) {
            $this->assertTrue($this->catalog->isSectionValid($sectionKey), "Role '{$role}' has invalid section '{$sectionKey}'.");
        }
    }

    #[DataProvider('deniedToolRoleProvider')]
    public function test_tool_denies_unauthorized_role(string $toolClass, string $role): void
    {
        $user = $this->createUserForRole($role);
        $tool = $this->makeTool($toolClass);
        $result = $tool($user, []);

        $this->assertArrayHasKey('result', $result);
        $this->assertFalse($result['result']['allowed'] ?? true, "Tool should deny role '{$role}'.");
    }

    #[DataProvider('allowedToolRoleProvider')]
    public function test_tool_allows_authorized_role(string $toolClass, string $role): void
    {
        $user = $this->createUserForRole($role);
        $tool = $this->makeTool($toolClass);
        $result = $tool($user, $this->defaultArgsFor($toolClass));

        $this->assertArrayHasKey('result', $result);
        $this->assertFalse(
            isset($result['result']['allowed']) && $result['result']['allowed'] === false,
            "Tool {$toolClass} should allow role '{$role}' but denied."
        );
    }

    public static function roleProvider(): array
    {
        return [
            'admin' => ['admin'],
            'marketing' => ['marketing'],
            'sales' => ['sales'],
            'hr' => ['hr'],
            'credit' => ['credit'],
            'accounting' => ['accounting'],
        ];
    }

    public static function deniedToolRoleProvider(): array
    {
        return [
            'campaign_advisor_hr' => [CampaignAdvisorTool::class, 'hr'],
            'campaign_advisor_credit' => [CampaignAdvisorTool::class, 'credit'],
            'campaign_advisor_accounting' => [CampaignAdvisorTool::class, 'accounting'],
            'hiring_advisor_marketing' => [HiringAdvisorTool::class, 'marketing'],
            'hiring_advisor_credit' => [HiringAdvisorTool::class, 'credit'],
            'hiring_advisor_accounting' => [HiringAdvisorTool::class, 'accounting'],
            'sales_advisor_hr' => [SalesAdvisorTool::class, 'hr'],
            'sales_advisor_credit' => [SalesAdvisorTool::class, 'credit'],
            'sales_advisor_accounting' => [SalesAdvisorTool::class, 'accounting'],
            'marketing_analytics_hr' => [MarketingAnalyticsTool::class, 'hr'],
            'marketing_analytics_credit' => [MarketingAnalyticsTool::class, 'credit'],
        ];
    }

    public static function allowedToolRoleProvider(): array
    {
        return [
            'campaign_advisor_admin' => [CampaignAdvisorTool::class, 'admin'],
            'campaign_advisor_marketing' => [CampaignAdvisorTool::class, 'marketing'],
            'hiring_advisor_admin' => [HiringAdvisorTool::class, 'admin'],
            'hiring_advisor_hr' => [HiringAdvisorTool::class, 'hr'],
            'finance_calculator_admin' => [FinanceCalculatorTool::class, 'admin'],
            'finance_calculator_credit' => [FinanceCalculatorTool::class, 'credit'],
            'sales_advisor_admin' => [SalesAdvisorTool::class, 'admin'],
            'sales_advisor_sales' => [SalesAdvisorTool::class, 'sales'],
            'marketing_analytics_admin' => [MarketingAnalyticsTool::class, 'admin'],
            'marketing_analytics_marketing' => [MarketingAnalyticsTool::class, 'marketing'],
        ];
    }

    private function makeTool(string $toolClass): object
    {
        $guardrails = new NumericGuardrails();
        return match ($toolClass) {
            CampaignAdvisorTool::class => new CampaignAdvisorTool($guardrails),
            FinanceCalculatorTool::class => new FinanceCalculatorTool($guardrails),
            SalesAdvisorTool::class => new SalesAdvisorTool($guardrails),
            MarketingAnalyticsTool::class => new MarketingAnalyticsTool(),
            HiringAdvisorTool::class => new HiringAdvisorTool(),
        };
    }

    private function defaultArgsFor(string $toolClass): array
    {
        return match ($toolClass) {
            FinanceCalculatorTool::class => ['calculation_type' => 'mortgage', 'unit_price' => 1000000],
            CampaignAdvisorTool::class => ['budget' => 50000],
            SalesAdvisorTool::class => ['topic' => 'closing_tips'],
            MarketingAnalyticsTool::class => ['report_type' => 'channel_comparison'],
            HiringAdvisorTool::class => ['role' => 'sales'],
            default => [],
        };
    }
}
