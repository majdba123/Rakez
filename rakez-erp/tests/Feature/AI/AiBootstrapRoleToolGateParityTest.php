<?php

namespace Tests\Feature\AI;

use App\Services\AI\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsersWithBootstrapRole;

/**
 * يطابق أدوار الـ bootstrap (كما في الإنتاج) مع {@see ToolRegistry::allowedToolNamesForUser} — بدون OpenAI.
 */
class AiBootstrapRoleToolGateParityTest extends TestCase
{
    use CreatesUsersWithBootstrapRole;
    use RefreshDatabase;

    public function test_marketing_bootstrap_role_excludes_sales_kpi_tool(): void
    {
        $user = $this->createUserWithBootstrapRole('marketing', ['type' => 'marketing']);
        $allowed = app(ToolRegistry::class)->allowedToolNamesForUser($user);

        $this->assertNotContains('tool_kpi_sales', $allowed);
    }

    public function test_sales_bootstrap_role_includes_sales_kpi_tool(): void
    {
        $user = $this->createUserWithBootstrapRole('sales', ['type' => 'sales']);
        $allowed = app(ToolRegistry::class)->allowedToolNamesForUser($user);

        $this->assertContains('tool_kpi_sales', $allowed);
    }
}
