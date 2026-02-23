<?php

namespace Tests\Unit\AI;

use App\Models\User;
use App\Services\AI\CapabilityResolver;
use App\Services\AI\CatalogService;
use Tests\TestCase;

class CatalogServiceTest extends TestCase
{
    private CatalogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CatalogService(new CapabilityResolver());
    }

    public function test_sections_returns_all_section_keys_with_labels(): void
    {
        $sections = $this->service->sections();

        $this->assertIsArray($sections);
        $this->assertNotEmpty($sections);
        $this->assertArrayHasKey('general', $sections);
        $this->assertArrayHasKey('contracts', $sections);
        $this->assertEquals('General', $sections['general']);
        $this->assertEquals('Contracts', $sections['contracts']);
    }

    public function test_sectionKeys_returns_string_array(): void
    {
        $keys = $this->service->sectionKeys();

        $this->assertIsArray($keys);
        $this->assertContains('general', $keys);
        $this->assertContains('sales', $keys);
        $this->assertContains('hr', $keys);
    }

    public function test_isSectionValid_returns_true_for_known_section(): void
    {
        $this->assertTrue($this->service->isSectionValid('general'));
        $this->assertTrue($this->service->isSectionValid('contracts'));
    }

    public function test_isSectionValid_returns_false_for_unknown_section(): void
    {
        $this->assertFalse($this->service->isSectionValid('nonexistent'));
        $this->assertFalse($this->service->isSectionValid(''));
    }

    public function test_isPermissionValid_returns_true_for_known_permission(): void
    {
        $this->assertTrue($this->service->isPermissionValid('contracts.view'));
        $this->assertTrue($this->service->isPermissionValid('sales.dashboard.view'));
        $this->assertTrue($this->service->isPermissionValid('use-ai-assistant'));
    }

    public function test_isPermissionValid_returns_false_for_unknown_permission(): void
    {
        $this->assertFalse($this->service->isPermissionValid('fake.perm'));
        $this->assertFalse($this->service->isPermissionValid(''));
    }

    public function test_roleNames_returns_all_roles(): void
    {
        $roles = $this->service->roleNames();

        $this->assertIsArray($roles);
        $this->assertContains('admin', $roles);
        $this->assertContains('sales', $roles);
        $this->assertContains('marketing', $roles);
        $this->assertContains('hr', $roles);
        $this->assertContains('credit', $roles);
        $this->assertContains('accounting', $roles);
    }

    public function test_permissionsForRole_returns_array(): void
    {
        $perms = $this->service->permissionsForRole('admin');

        $this->assertIsArray($perms);
        $this->assertNotEmpty($perms);
        $this->assertContains('contracts.view', $perms);
    }

    public function test_permissionsForRole_returns_empty_for_unknown_role(): void
    {
        $this->assertEmpty($this->service->permissionsForRole('ghost'));
    }

    public function test_sectionsForRole_admin_has_all_sections(): void
    {
        $adminSections = $this->service->sectionsForRole('admin');

        $this->assertIsArray($adminSections);
        $this->assertArrayHasKey('general', $adminSections);
        $this->assertArrayHasKey('contracts', $adminSections);
        $this->assertArrayHasKey('sales', $adminSections);
        $this->assertArrayHasKey('hr', $adminSections);
        $this->assertArrayHasKey('credit', $adminSections);
        $this->assertArrayHasKey('accounting', $adminSections);
    }

    public function test_sectionsForRole_marketing_has_marketing_sections(): void
    {
        $mktSections = $this->service->sectionsForRole('marketing');

        $this->assertArrayHasKey('general', $mktSections);
        $this->assertArrayHasKey('marketing_dashboard', $mktSections);
        $this->assertArrayHasKey('campaign_advisor', $mktSections);
        $this->assertArrayNotHasKey('hr', $mktSections);
        $this->assertArrayNotHasKey('credit', $mktSections);
    }

    public function test_sectionsForRole_hr_cannot_access_sales(): void
    {
        $hrSections = $this->service->sectionsForRole('hr');

        $this->assertArrayHasKey('general', $hrSections);
        $this->assertArrayHasKey('hr', $hrSections);
        $this->assertArrayHasKey('hiring_advisor', $hrSections);
        $this->assertArrayNotHasKey('sales', $hrSections);
        $this->assertArrayNotHasKey('credit', $hrSections);
    }

    public function test_sectionsForRole_unknown_returns_empty(): void
    {
        $this->assertEmpty($this->service->sectionsForRole('ghost'));
    }

    /**
     * Integrity: every permission referenced in ai_sections.required_capabilities
     * must exist in the catalog permissions.
     */
    public function test_all_section_required_capabilities_are_valid_permissions(): void
    {
        $sections = config('ai_sections', []);
        $permissions = $this->service->catalog()['permissions'] ?? [];

        foreach ($sections as $key => $sec) {
            foreach ($sec['required_capabilities'] ?? [] as $cap) {
                $this->assertArrayHasKey(
                    $cap,
                    $permissions,
                    "Section '{$key}' requires capability '{$cap}' which is not defined in catalog permissions."
                );
            }
        }
    }

    /**
     * Integrity: every permission in role maps should be a valid catalog permission.
     */
    public function test_all_role_permissions_are_valid(): void
    {
        $roles = $this->service->roleMap();
        $permissions = $this->service->catalog()['permissions'] ?? [];

        foreach ($roles as $role => $data) {
            foreach ($data['permissions'] ?? [] as $perm) {
                $this->assertArrayHasKey(
                    $perm,
                    $permissions,
                    "Role '{$role}' has permission '{$perm}' which is not defined in catalog permissions."
                );
            }
        }
    }

    /**
     * Integrity: no orphan sections — every section in a role mapping
     * must exist in the top-level sections list.
     */
    public function test_no_orphan_sections_in_role_map(): void
    {
        $roles = $this->service->roleMap();
        $validSections = $this->service->sectionKeys();

        foreach ($roles as $role => $data) {
            foreach ($data['sections'] ?? [] as $sec) {
                $this->assertContains(
                    $sec,
                    $validSections,
                    "Role '{$role}' references section '{$sec}' which does not exist in the catalog."
                );
            }
        }
    }

    public function test_marketReferences_has_expected_keys(): void
    {
        $refs = $this->service->marketReferences();

        $this->assertArrayHasKey('cpl', $refs);
        $this->assertArrayHasKey('close_rate', $refs);
        $this->assertEquals(15, $refs['cpl']['min']);
        $this->assertEquals(150, $refs['cpl']['max']);
    }

    public function test_flush_resets_cache(): void
    {
        $this->service->sections();
        $this->service->flush();

        $sections = $this->service->sections();
        $this->assertNotEmpty($sections);
    }
}
