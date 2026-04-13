<?php

namespace Tests\Feature\Governance;

use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class GovernanceObservabilityAccessTest extends BasePermissionTestCase
{
    #[Test]
    public function erp_admin_can_access_effective_access_and_governance_audit_pages(): void
    {
        $viewer = $this->createDefaultUser([
            'is_active' => true,
        ]);
        $viewer->assignRole('erp_admin');

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
    public function auditor_can_access_read_only_governance_pages_but_cannot_mutate_records(): void
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

        $this->actingAs($auditor)->get('/admin')->assertOk();
        $this->actingAs($auditor)->get('/admin/effective-access')->assertOk();
        $this->actingAs($auditor)->get("/admin/effective-access/{$subject->id}")->assertOk();
        $this->actingAs($auditor)->get('/admin/governance-audit')->assertOk();
        $this->actingAs($auditor)->get("/admin/governance-audit/{$auditLog->id}")->assertOk();

        $this->actingAs($auditor)->get('/admin/users/create')->assertForbidden();
        $this->actingAs($auditor)->get("/admin/users/{$subject->id}/edit")->assertForbidden();
        $this->actingAs($auditor)->get("/admin/roles/" . $auditor->roles()->firstOrFail()->id . '/edit')->assertForbidden();
        $this->actingAs($auditor)->get("/admin/direct-permissions/{$subject->id}/edit")->assertForbidden();
    }
}
