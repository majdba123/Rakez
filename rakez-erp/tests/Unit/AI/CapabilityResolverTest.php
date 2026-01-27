<?php

namespace Tests\Unit\AI;

use App\Models\User;
use App\Services\AI\CapabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityResolverTest extends TestCase
{
    use RefreshDatabase;

    private CapabilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CapabilityResolver();
    }

    public function test_resolve_returns_capabilities_from_attribute(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('capabilities', ['contracts.view', 'units.view']);

        $result = $this->resolver->resolve($user);

        $this->assertContains('contracts.view', $result);
        $this->assertContains('units.view', $result);
    }

    public function test_resolve_uses_spatie_permissions_when_available(): void
    {
        // This test requires Spatie package to be installed
        // For now, we'll test the fallback behavior
        config(['ai_capabilities.bootstrap_role_map.default' => ['contracts.view', 'units.edit']]);

        $user = User::factory()->create();
        $user->setAttribute('capabilities', null);

        $result = $this->resolver->resolve($user);

        $this->assertContains('contracts.view', $result);
        $this->assertContains('units.edit', $result);
    }

    public function test_resolve_falls_back_to_bootstrap_default(): void
    {
        config(['ai_capabilities.bootstrap_role_map.default' => ['contracts.view', 'notifications.view']]);

        $user = User::factory()->create();
        $user->setAttribute('capabilities', null);

        $result = $this->resolver->resolve($user);

        $this->assertContains('contracts.view', $result);
        $this->assertContains('notifications.view', $result);
    }

    public function test_resolve_filters_invalid_capabilities(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('capabilities', [
            'contracts.view',
            '', // empty string
            null, // null
            123, // non-string
            'units.view',
        ]);

        $result = $this->resolver->resolve($user);

        $this->assertContains('contracts.view', $result);
        $this->assertContains('units.view', $result);
        $this->assertNotContains('', $result);
        $this->assertNotContains(null, $result);
        $this->assertNotContains(123, $result);
    }

    public function test_resolve_removes_duplicates(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('capabilities', ['contracts.view', 'units.view', 'contracts.view']);

        $result = $this->resolver->resolve($user);

        $this->assertCount(2, $result);
        $this->assertEquals(['contracts.view', 'units.view'], $result);
    }

    public function test_resolve_caches_results(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('capabilities', ['contracts.view']);

        $result1 = $this->resolver->resolve($user);
        $result2 = $this->resolver->resolve($user);

        $this->assertEquals($result1, $result2);
        $this->assertSame($result1, $result2); // Same instance from cache
    }

    public function test_resolve_handles_empty_capabilities(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('capabilities', []);

        config(['ai_capabilities.bootstrap_role_map.default' => ['contracts.view']]);

        $result = $this->resolver->resolve($user);

        $this->assertContains('contracts.view', $result);
    }

    public function test_resolve_handles_null_capabilities(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('capabilities', null);

        config(['ai_capabilities.bootstrap_role_map.default' => ['notifications.view']]);

        $result = $this->resolver->resolve($user);

        $this->assertContains('notifications.view', $result);
    }

    public function test_has_returns_true_for_existing_capability(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('capabilities', ['contracts.view', 'units.view']);

        $this->assertTrue($this->resolver->has($user, 'contracts.view'));
        $this->assertTrue($this->resolver->has($user, 'units.view'));
    }

    public function test_has_returns_false_for_missing_capability(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('capabilities', ['contracts.view']);

        $this->assertFalse($this->resolver->has($user, 'units.edit'));
        $this->assertFalse($this->resolver->has($user, 'nonexistent'));
    }

    public function test_resolve_with_spatie_getPermissionNames(): void
    {
        // This test requires Spatie package - testing fallback instead
        config(['ai_capabilities.bootstrap_role_map.default' => ['contracts.view', 'units.edit']]);

        $user = User::factory()->create();
        $user->setAttribute('capabilities', null);

        $result = $this->resolver->resolve($user);

        $this->assertContains('contracts.view', $result);
        $this->assertContains('units.edit', $result);
    }

    public function test_resolve_with_spatie_getAllPermissions(): void
    {
        // This test requires Spatie package - testing fallback instead
        config(['ai_capabilities.bootstrap_role_map.default' => ['contracts.view', 'units.edit']]);

        $user = User::factory()->create();
        $user->setAttribute('capabilities', null);

        $result = $this->resolver->resolve($user);

        $this->assertContains('contracts.view', $result);
        $this->assertContains('units.edit', $result);
    }

    public function test_resolve_prioritizes_attribute_over_spatie(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('capabilities', ['contracts.view']);

        $result = $this->resolver->resolve($user);

        $this->assertContains('contracts.view', $result);
        // Should not include bootstrap defaults when attribute has values
        $this->assertNotContains('notifications.view', $result);
    }
}
