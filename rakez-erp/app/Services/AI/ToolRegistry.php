<?php

namespace App\Services\AI;

use App\Models\User;
use App\Services\AI\Tools\ToolContract;
use Illuminate\Support\Arr;

class ToolRegistry
{
    /** @var array<string, class-string<ToolContract>> */
    private array $handlers = [];

    public function __construct()
    {
        $this->registerDefaultTools();
    }

    private function registerDefaultTools(): void
    {
        $this->register('tool_search_records', \App\Services\AI\Tools\SearchRecordsTool::class);
        $this->register('tool_get_lead_summary', \App\Services\AI\Tools\GetLeadSummaryTool::class);
        $this->register('tool_get_project_summary', \App\Services\AI\Tools\GetProjectSummaryTool::class);
        $this->register('tool_get_contract_status', \App\Services\AI\Tools\GetContractStatusTool::class);
        $this->register('tool_kpi_sales', \App\Services\AI\Tools\KpiSalesTool::class);
        $this->register('tool_explain_access', \App\Services\AI\Tools\ExplainAccessTool::class);
        $this->register('tool_rag_search', \App\Services\AI\Tools\RagSearchTool::class);
        $this->register('tool_campaign_advisor', \App\Services\AI\Tools\CampaignAdvisorTool::class);
        $this->register('tool_hiring_advisor', \App\Services\AI\Tools\HiringAdvisorTool::class);
        $this->register('tool_finance_calculator', \App\Services\AI\Tools\FinanceCalculatorTool::class);
        $this->register('tool_marketing_analytics', \App\Services\AI\Tools\MarketingAnalyticsTool::class);
        $this->register('tool_sales_advisor', \App\Services\AI\Tools\SalesAdvisorTool::class);
        $this->register('tool_smart_distribution', \App\Services\AI\Tools\SmartDistributionAdvisorTool::class);
        $this->register('tool_employee_recommendation', \App\Services\AI\Tools\EmployeeRecommendationTool::class);
        $this->register('tool_campaign_funnel', \App\Services\AI\Tools\CampaignFunnelAnalyticsTool::class);
        $this->register('tool_roas_optimizer', \App\Services\AI\Tools\RoasOptimizerTool::class);
        $this->register('tool_ai_call_status', \App\Services\AI\Tools\AiCallStatusTool::class);
    }

    public function register(string $name, string $handlerClass): void
    {
        $this->handlers[$name] = $handlerClass;
    }

    /**
     * Execute a tool by name.
     *
     * @param  array<string, mixed>  $args
     * @return array{result: mixed, source_refs: array}
     */
    public function execute(User $user, string $toolName, array $args): array
    {
        $handlerClass = $this->handlers[$toolName] ?? null;
        if (! $handlerClass || ! class_exists($handlerClass)) {
            return [
                'result' => ['error' => 'Unknown tool: '.$toolName],
                'source_refs' => [],
            ];
        }
        $handler = app($handlerClass);

        if (! $handler instanceof ToolContract) {
            return [
                'result' => ['error' => 'Invalid tool handler'],
                'source_refs' => [],
            ];
        }

        return $handler($user, $args);
    }

    /**
     * @return array<string>
     */
    public function registeredNames(): array
    {
        return array_keys($this->handlers);
    }
}
