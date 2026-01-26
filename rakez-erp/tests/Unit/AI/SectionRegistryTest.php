<?php

namespace Tests\Unit\AI;

use App\Services\AI\SectionRegistry;
use Tests\TestCase;

class SectionRegistryTest extends TestCase
{
    private SectionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new SectionRegistry();
    }

    public function test_all_returns_all_sections(): void
    {
        $sections = $this->registry->all();

        $this->assertIsArray($sections);
        $this->assertArrayHasKey('general', $sections);
        $this->assertArrayHasKey('contracts', $sections);
    }

    public function test_find_returns_section_by_key(): void
    {
        $section = $this->registry->find('contracts');

        $this->assertIsArray($section);
        $this->assertEquals('Contracts', $section['label']);
        $this->assertContains('contracts.view', $section['required_capabilities']);
    }

    public function test_find_returns_null_for_missing_key(): void
    {
        $section = $this->registry->find('nonexistent');

        $this->assertNull($section);
    }

    public function test_find_returns_null_for_null_key(): void
    {
        $section = $this->registry->find(null);

        $this->assertNull($section);
    }

    public function test_availableFor_filters_by_capabilities(): void
    {
        $capabilities = ['contracts.view', 'units.view'];

        $sections = $this->registry->availableFor($capabilities);

        $this->assertIsArray($sections);
        $this->assertNotEmpty($sections);

        foreach ($sections as $section) {
            $required = $section['required_capabilities'] ?? [];
            $this->assertEmpty(array_diff($required, $capabilities));
        }
    }

    public function test_availableFor_returns_empty_for_no_capabilities(): void
    {
        $sections = $this->registry->availableFor([]);

        $this->assertIsArray($sections);
        // Should only return sections with no required capabilities
        foreach ($sections as $section) {
            $required = $section['required_capabilities'] ?? [];
            $this->assertEmpty($required);
        }
    }

    public function test_allowedContextParams_returns_params(): void
    {
        $params = $this->registry->allowedContextParams('contracts');

        $this->assertIsArray($params);
        $this->assertContains('contract_id', $params);
    }

    public function test_allowedContextParams_returns_empty_for_missing_section(): void
    {
        $params = $this->registry->allowedContextParams('nonexistent');

        $this->assertIsArray($params);
        $this->assertEmpty($params);
    }

    public function test_suggestions_returns_suggestions(): void
    {
        $suggestions = $this->registry->suggestions('contracts');

        $this->assertIsArray($suggestions);
        $this->assertNotEmpty($suggestions);
        $this->assertContains('How do I create a contract?', $suggestions);
    }

    public function test_suggestions_returns_empty_for_missing_section(): void
    {
        $suggestions = $this->registry->suggestions('nonexistent');

        $this->assertIsArray($suggestions);
        $this->assertEmpty($suggestions);
    }

    public function test_contextSchema_returns_schema(): void
    {
        $schema = $this->registry->contextSchema('contracts');

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('contract_id', $schema);
        $this->assertEquals('int|min:1', $schema['contract_id']);
    }

    public function test_contextSchema_returns_empty_for_missing_section(): void
    {
        $schema = $this->registry->contextSchema('nonexistent');

        $this->assertIsArray($schema);
        $this->assertEmpty($schema);
    }

    public function test_contextSchema_returns_empty_for_section_without_schema(): void
    {
        $schema = $this->registry->contextSchema('general');

        $this->assertIsArray($schema);
        $this->assertEmpty($schema);
    }

    public function test_contextPolicy_returns_policy(): void
    {
        $policy = $this->registry->contextPolicy('contracts');

        $this->assertIsArray($policy);
        $this->assertArrayHasKey('contract_id', $policy);
        $this->assertEquals('view-contract', $policy['contract_id']);
    }

    public function test_contextPolicy_returns_empty_for_missing_section(): void
    {
        $policy = $this->registry->contextPolicy('nonexistent');

        $this->assertIsArray($policy);
        $this->assertEmpty($policy);
    }

    public function test_contextPolicy_returns_empty_for_section_without_policy(): void
    {
        $policy = $this->registry->contextPolicy('general');

        $this->assertIsArray($policy);
        $this->assertEmpty($policy);
    }

    public function test_parent_returns_parent_key(): void
    {
        $parent = $this->registry->parent('units_csv');

        $this->assertEquals('units', $parent);
    }

    public function test_parent_returns_null_for_missing_section(): void
    {
        $parent = $this->registry->parent('nonexistent');

        $this->assertNull($parent);
    }

    public function test_parent_returns_null_when_no_parent(): void
    {
        $parent = $this->registry->parent('contracts');

        $this->assertNull($parent);
    }
}
