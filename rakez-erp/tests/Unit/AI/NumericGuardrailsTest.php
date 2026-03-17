<?php

namespace Tests\Unit\AI;

use App\Services\AI\NumericGuardrails;
use Tests\TestCase;

class NumericGuardrailsTest extends TestCase
{
    private NumericGuardrails $guardrails;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guardrails = new NumericGuardrails;
    }

    public function test_validate_roi_ok(): void
    {
        $check = $this->guardrails->validateROI(500.0, 'romi');
        $this->assertTrue($check->isOk());
    }

    public function test_validate_roi_critical_below_min(): void
    {
        $check = $this->guardrails->validateROI(50.0, 'romi');
        $this->assertTrue($check->isCritical());
    }

    public function test_validate_roi_warning_above_max(): void
    {
        $check = $this->guardrails->validateROI(3000.0, 'romi');
        $this->assertTrue($check->isWarning());
    }

    public function test_validate_project_roi(): void
    {
        $check = $this->guardrails->validateROI(50.0, 'project_roi');
        $this->assertTrue($check->isOk());
    }

    public function test_validate_cpl_ok(): void
    {
        $check = $this->guardrails->validateCPL(50.0);
        $this->assertTrue($check->isOk());
    }

    public function test_validate_cpl_critical_above_max(): void
    {
        $check = $this->guardrails->validateCPL(200.0);
        $this->assertTrue($check->isCritical());
    }

    public function test_validate_cpl_warning_too_low(): void
    {
        $check = $this->guardrails->validateCPL(3.0);
        $this->assertTrue($check->isWarning());
    }

    public function test_validate_cpl_with_platform(): void
    {
        $check = $this->guardrails->validateCPL(25.0, 'google');
        $this->assertTrue($check->isOk());
    }

    public function test_validate_cpl_with_region(): void
    {
        $check = $this->guardrails->validateCPL(50.0, null, 'الرياض');
        $this->assertTrue($check->isOk());
    }

    public function test_validate_close_rate_ok(): void
    {
        $check = $this->guardrails->validateCloseRate(10.0);
        $this->assertTrue($check->isOk());
    }

    public function test_validate_close_rate_critical(): void
    {
        $check = $this->guardrails->validateCloseRate(2.0);
        $this->assertTrue($check->isCritical());
    }

    public function test_validate_dti_ok(): void
    {
        $check = $this->guardrails->validateDTI(40.0);
        $this->assertTrue($check->isOk());
    }

    public function test_validate_dti_critical(): void
    {
        $check = $this->guardrails->validateDTI(60.0);
        $this->assertTrue($check->isCritical());
    }

    public function test_validate_dti_warning_near_limit(): void
    {
        $check = $this->guardrails->validateDTI(52.0);
        $this->assertTrue($check->isWarning());
    }
}
