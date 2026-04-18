<?php

namespace Tests\Feature\Governance;

use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class GovernanceObservabilityAccessTest extends BasePermissionTestCase
{
    #[Test]
    public function top_authority_can_access_effective_access_and_governance_audit_pages(): void
    {
        $viewer = $this->createSuperAdmin([
            'is_active' => true,
        ]);

        $subject = $this->createSalesStaff([
            'is_active' => true,
        ]);

        $auditLog = app(GovernanceAuditLogger::class)->log('governance.test.event', $subject, [
            'after' => ['status' => 'ok'],
        ], $viewer);

        $this->actingAs($viewer)->get('/admin/effective-access')->assertOk();
        $this->actingAs($viewer)->get("/admin/effective-access/{$subject->id}")->assertOk();
        $this->actingAs($viewer)->get('/admin/governance-audit')->assertOk();
        $this->actingAs($viewer)->get("/admin/governance-audit/{$auditLog->id}")->assertOk();
    }

    #[Test]
    public function auditor_cannot_access_observability_pages_without_top_authority(): void
    {
        $auditor = $this->createDefaultUser([
            'is_active' => true,
        ]);
        $auditor->assignRole('auditor_readonly');

        $subject = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $subject->assignRole('default');

        $auditLog = app(GovernanceAuditLogger::class)->log('governance.test.read_only', $subject, [
            'before' => ['active' => true],
            'after' => ['active' => false],
        ], $auditor);

        $this->actingAs($auditor)->get('/admin')->assertForbidden();
        $this->actingAs($auditor)->get('/admin/effective-access')->assertForbidden();
        $this->actingAs($auditor)->get("/admin/effective-access/{$subject->id}")->assertForbidden();
        $this->actingAs($auditor)->get('/admin/governance-audit')->assertForbidden();
        $this->actingAs($auditor)->get("/admin/governance-audit/{$auditLog->id}")->assertForbidden();

        $this->actingAs($auditor)->get('/admin/users/create')->assertForbidden();
        $this->actingAs($auditor)->get("/admin/users/{$subject->id}/edit")->assertForbidden();
        $this->actingAs($auditor)->get("/admin/roles/" . $auditor->roles()->firstOrFail()->id . '/edit')->assertForbidden();
        $this->actingAs($auditor)->get("/admin/direct-permissions/{$subject->id}/edit")->assertForbidden();
    }
}
