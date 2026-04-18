<?php

namespace Tests\Feature\Governance;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class CreditGovernanceAccessTest extends BasePermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'Credit Oversight',
        ]);
    }

    #[Test]
    public function super_admin_can_access_credit_oversight_pages(): void
    {
        $user = $this->createSuperAdmin([
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/admin/credit-overview')->assertOk();
        $this->actingAs($user)->get('/admin/credit-bookings')->assertOk();
        $this->actingAs($user)->get('/admin/title-transfers')->assertOk();
        $this->actingAs($user)->get('/admin/claim-files')->assertOk();
        $this->actingAs($user)->get('/admin/credit-notifications')->assertOk();
    }

    #[Test]
    public function section_governance_roles_cannot_access_credit_pages_without_top_authority(): void
    {
        $creditAdmin = $this->createDefaultUser([
            'is_active' => true,
        ]);
        $creditAdmin->assignRole('credit_admin');

        $auditor = $this->createDefaultUser([
            'is_active' => true,
        ]);
        $auditor->assignRole('auditor_readonly');

        $paths = [
            '/admin/credit-overview',
            '/admin/credit-bookings',
            '/admin/title-transfers',
            '/admin/claim-files',
            '/admin/credit-notifications',
        ];

        foreach ($paths as $path) {
            $this->actingAs($creditAdmin)->get($path)->assertForbidden();
            $this->actingAs($auditor)->get($path)->assertForbidden();
        }
    }

    #[Test]
    public function legacy_credit_staff_does_not_gain_panel_access_without_governance_overlay(): void
    {
        $creditStaff = User::factory()->create([
            'type' => 'credit',
            'is_active' => true,
        ]);
        $creditStaff->syncRolesFromType();

        $this->actingAs($creditStaff)->get('/admin/credit-overview')->assertForbidden();
        $this->actingAs($creditStaff)->get('/admin/credit-bookings')->assertForbidden();
    }
}
