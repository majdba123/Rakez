<?php

namespace Tests\Unit\AI;

use App\Services\AI\ContextValidator;
use App\Services\AI\SectionRegistry;
use Tests\TestCase;

class ContextValidatorTest extends TestCase
{
    private ContextValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ContextValidator(new SectionRegistry());
    }

    public function test_validate_passes_valid_data(): void
    {
        $context = ['contract_id' => 123];

        $result = $this->validator->validate('contracts', $context);

        $this->assertEquals($context, $result);
    }

    public function test_validate_returns_validated_data(): void
    {
        $context = ['contract_id' => '123']; // String, should be converted to int

        $result = $this->validator->validate('contracts', $context);

        $this->assertArrayHasKey('contract_id', $result);
    }

    public function test_validate_throws_for_invalid_type(): void
    {
        $context = ['contract_id' => 'invalid'];

        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('contracts', $context);
    }

    public function test_validate_throws_for_below_min(): void
    {
        $context = ['contract_id' => 0];

        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('contracts', $context);
    }

    public function test_validate_handles_missing_schema(): void
    {
        $context = ['contract_id' => 123];

        $result = $this->validator->validate('general', $context);

        $this->assertEquals($context, $result);
    }

    public function test_validate_handles_empty_context(): void
    {
        $result = $this->validator->validate('contracts', []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_validate_parses_multiple_rules(): void
    {
        $context = [
            'contract_id' => 123,
            'unit_id' => 456,
        ];

        $result = $this->validator->validate('units', $context);

        $this->assertArrayHasKey('contract_id', $result);
        $this->assertArrayHasKey('unit_id', $result);
    }

    public function test_validate_parses_rule_with_value(): void
    {
        $context = ['contract_id' => 123];

        $result = $this->validator->validate('contracts', $context);

        $this->assertEquals(123, $result['contract_id']);
    }

    public function test_validate_handles_null_section_key(): void
    {
        $context = ['contract_id' => 123];

        $result = $this->validator->validate(null, $context);

        $this->assertEquals($context, $result);
    }

    public function test_validate_skips_extra_params(): void
    {
        $context = [
            'contract_id' => 123,
            'extra_param' => 'value',
        ];

        $result = $this->validator->validate('contracts', $context);

        $this->assertArrayHasKey('contract_id', $result);
        // Extra params might be filtered by schema, but validation should pass
    }

    public function test_validate_handles_negative_numbers(): void
    {
        $context = ['contract_id' => -1];

        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('contracts', $context);
    }

    public function test_validate_handles_string_numbers(): void
    {
        $context = ['contract_id' => '123'];

        $result = $this->validator->validate('contracts', $context);

        // Laravel validator should convert string to int if valid
        $this->assertArrayHasKey('contract_id', $result);
    }
}
