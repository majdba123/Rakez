<?php

namespace Tests\Unit\AI;

use App\Models\Contract;
use App\Models\User;
use App\Services\AI\ContextBuilder;
use App\Services\AI\ContextValidator;
use App\Services\AI\SectionRegistry;
use App\Services\Dashboard\ProjectManagementDashboardService;
use App\Services\Marketing\MarketingDashboardService;
use App\Services\Marketing\MarketingProjectService;
use App\Services\Marketing\MarketingTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private ContextBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ContextBuilder(
            new ProjectManagementDashboardService(),
            new MarketingDashboardService(),
            new MarketingProjectService(),
            new MarketingTaskService(app(\App\Services\Marketing\MarketingNotificationService::class)),
            new ContextValidator(new SectionRegistry()),
            new SectionRegistry()
        );
    }

    public function test_build_includes_user_info(): void
    {
        $user = User::factory()->create();

        $result = $this->builder->build($user, null, [], []);

        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($user->id, $result['user']['id']);
        $this->assertEquals($user->name, $result['user']['name']);
        $this->assertEquals($user->type, $result['user']['type']);
    }

    public function test_build_includes_section_key(): void
    {
        $user = User::factory()->create();

        $result = $this->builder->build($user, 'contracts', [], []);

        $this->assertEquals('contracts', $result['section']);
    }

    public function test_build_includes_contracts_summary_with_capability(): void
    {
        $user = User::factory()->create();
        Contract::factory()->count(3)->create(['user_id' => $user->id]);

        $result = $this->builder->build($user, null, ['contracts.view'], []);

        $this->assertArrayHasKey('contracts_summary', $result);
        $this->assertEquals(3, $result['contracts_summary']['total']);
    }

    public function test_build_excludes_contracts_summary_without_capability(): void
    {
        $user = User::factory()->create();

        $result = $this->builder->build($user, null, [], []);

        $this->assertArrayNotHasKey('contracts_summary', $result);
    }

    public function test_build_includes_contract_details_with_valid_id(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $user->id]);

        $result = $this->builder->build($user, 'contracts', ['contracts.view'], ['contract_id' => $contract->id]);

        $this->assertArrayHasKey('contract', $result);
        $this->assertEquals($contract->id, $result['contract']['id']);
    }

    public function test_build_excludes_contract_details_without_access(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $otherUser->id]);

        $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class);
        $this->builder->build($user, 'contracts', ['contracts.view'], ['contract_id' => $contract->id]);
    }

    public function test_build_includes_notifications_summary(): void
    {
        $user = User::factory()->create();

        $result = $this->builder->build($user, null, ['notifications.view'], []);

        $this->assertArrayHasKey('notifications_summary', $result);
        $this->assertArrayHasKey('latest', $result['notifications_summary']);
    }

    public function test_build_includes_admin_notifications_with_manage_capability(): void
    {
        $user = User::factory()->create();

        $result = $this->builder->build($user, null, ['notifications.view', 'notifications.manage'], []);

        $this->assertArrayHasKey('notifications_summary', $result);
        $this->assertArrayHasKey('admin_latest', $result['notifications_summary']);
    }

    public function test_build_includes_dashboard_with_capability(): void
    {
        $user = User::factory()->create();

        $result = $this->builder->build($user, 'dashboard', ['dashboard.analytics.view'], []);

        $this->assertArrayHasKey('dashboard', $result);
        $this->assertIsArray($result['dashboard']);
    }

    public function test_build_validates_context(): void
    {
        $user = User::factory()->create();

        $result = $this->builder->build($user, 'contracts', ['contracts.view'], ['contract_id' => 123]);

        // Should not throw exception if validation passes
        $this->assertIsArray($result);
    }

    public function test_build_checks_contract_policy(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $user->id]);

        $result = $this->builder->build($user, 'contracts', ['contracts.view'], ['contract_id' => $contract->id]);

        // Should include contract if user owns it
        $this->assertArrayHasKey('contract', $result);
    }

    public function test_build_throws_on_unauthorized_contract(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $otherUser->id]);

        $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class);
        $this->builder->build($user, 'contracts', ['contracts.view'], ['contract_id' => $contract->id]);
    }

    public function test_build_handles_missing_contract(): void
    {
        $user = User::factory()->create();

        $result = $this->builder->build($user, 'contracts', ['contracts.view'], ['contract_id' => 99999]);

        $this->assertArrayNotHasKey('contract', $result);
    }

    public function test_build_filters_by_user_contracts(): void
    {
        $user = User::factory()->create();
        Contract::factory()->count(2)->create(['user_id' => $user->id]);
        Contract::factory()->count(3)->create(['user_id' => User::factory()->create()->id]);

        $result = $this->builder->build($user, null, ['contracts.view'], []);

        $this->assertEquals(2, $result['contracts_summary']['total']);
    }

    public function test_build_includes_all_contracts_with_view_all(): void
    {
        $user = User::factory()->create();
        Contract::factory()->count(2)->create(['user_id' => $user->id]);
        Contract::factory()->count(3)->create(['user_id' => User::factory()->create()->id]);

        $result = $this->builder->build($user, null, ['contracts.view', 'contracts.view_all'], []);

        $this->assertEquals(5, $result['contracts_summary']['total']);
    }

    public function test_build_handles_empty_capabilities(): void
    {
        $user = User::factory()->create();

        $result = $this->builder->build($user, null, [], []);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayNotHasKey('contracts_summary', $result);
        $this->assertArrayNotHasKey('notifications_summary', $result);
    }

    public function test_build_handles_null_section_key(): void
    {
        $user = User::factory()->create();

        $result = $this->builder->build($user, null, [], []);

        $this->assertArrayHasKey('user', $result);
        $this->assertNull($result['section'] ?? null);
    }

    public function test_build_excludes_dashboard_without_capability(): void
    {
        $user = User::factory()->create();

        $result = $this->builder->build($user, 'dashboard', [], []);

        $this->assertArrayNotHasKey('dashboard', $result);
    }
}
