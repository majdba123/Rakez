<?php

namespace Tests\Feature\Governance;

use App\Models\User;
use App\Services\Governance\FilamentNavigationPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class FilamentNavigationPolicyTest extends BasePermissionTestCase
{
    #[Test]
    public function workflow_admin_passes_requests_and_workflow_group_gate(): void
    {
        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'Requests & Workflow',
        ]);

        $user = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $user->assignRole('workflow_admin');

        $policy = app(FilamentNavigationPolicy::class);

        $this->assertTrue($policy->canAccessNavigationGroup($user, 'Requests & Workflow'));
    }

    #[Test]
    public function workflow_admin_fails_credit_oversight_group_gate(): void
    {
        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'Requests & Workflow',
        ]);

        $user = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $user->assignRole('workflow_admin');

        $policy = app(FilamentNavigationPolicy::class);

        $this->assertFalse($policy->canAccessNavigationGroup($user, 'Credit Oversight'));
    }

    #[Test]
    public function credit_admin_passes_credit_oversight_group_gate(): void
    {
        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'Credit Oversight',
        ]);

        $user = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $user->assignRole('credit_admin');

        $policy = app(FilamentNavigationPolicy::class);

        $this->assertTrue($policy->canAccessNavigationGroup($user, 'Credit Oversight'));
    }
}
