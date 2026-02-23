<?php

namespace Tests\Unit\AI;

use App\Services\AI\GuardrailResult;
use App\Services\AI\NumericGuardrails;
use Tests\TestCase;

class NumericGuardrailsTest extends TestCase
{
    private NumericGuardrails $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new NumericGuardrails();
    }

    // ── CPL ──────────────────────────────────────────────────────

    public function test_cpl_within_global_range(): void
    {
        $r = $this->guard->validateCPL(50);
        $this->assertTrue($r->inRange);
        $this->assertNull($r->justification);
    }

    public function test_cpl_below_global_range(): void
    {
        $r = $this->guard->validateCPL(5);
        $this->assertFalse($r->inRange);
        $this->assertNotNull($r->justification);
    }

    public function test_cpl_above_global_range(): void
    {
        $r = $this->guard->validateCPL(200);
        $this->assertFalse($r->inRange);
    }

    public function test_cpl_channel_specific_google(): void
    {
        $r = $this->guard->validateCPL(45, 'google');
        $this->assertTrue($r->inRange);
    }

    public function test_cpl_channel_specific_google_out_of_range(): void
    {
        $r = $this->guard->validateCPL(5, 'google');
        $this->assertFalse($r->inRange);
    }

    public function test_cpl_channel_specific_tiktok(): void
    {
        $r = $this->guard->validateCPL(15, 'tiktok');
        $this->assertTrue($r->inRange);
    }

    // ── Close Rate ──────────────────────────────────────────────

    public function test_close_rate_in_range(): void
    {
        $r = $this->guard->validateCloseRate(10);
        $this->assertTrue($r->inRange);
    }

    public function test_close_rate_below_range(): void
    {
        $r = $this->guard->validateCloseRate(2);
        $this->assertFalse($r->inRange);
    }

    public function test_close_rate_above_range(): void
    {
        $r = $this->guard->validateCloseRate(25);
        $this->assertFalse($r->inRange);
    }

    // ── ROI (ROMI vs Project) ──────────────────────────────────

    public function test_romi_in_range(): void
    {
        $r = $this->guard->validateROI(500, 'romi');
        $this->assertTrue($r->inRange);
        $this->assertEquals('romi', $r->metric);
    }

    public function test_romi_out_of_range(): void
    {
        $r = $this->guard->validateROI(5000, 'romi');
        $this->assertFalse($r->inRange);
    }

    public function test_project_roi_in_range(): void
    {
        $r = $this->guard->validateROI(50, 'project_roi');
        $this->assertTrue($r->inRange);
        $this->assertEquals('project_roi', $r->metric);
    }

    public function test_project_roi_out_of_range(): void
    {
        $r = $this->guard->validateROI(1000, 'project_roi');
        $this->assertFalse($r->inRange);
    }

    // ── Mortgage formula ────────────────────────────────────────

    public function test_mortgage_standard_case(): void
    {
        $m = $this->guard->calculateMortgage(900000, 5.5, 25);

        $this->assertGreaterThan(0, $m['monthly_payment']);
        $this->assertGreaterThan($m['monthly_payment'], $m['total_payment']);
        $this->assertGreaterThan(0, $m['total_interest']);
        $this->assertEquals(55, $m['max_dti']);
        $this->assertGreaterThan(0, $m['min_salary_required']);

        // Known result: 900k at 5.5% for 25y ≈ 5,527 SAR/month
        $this->assertEqualsWithDelta(5527.0, $m['monthly_payment'], 10.0);
    }

    public function test_mortgage_with_zero_principal_returns_zeroes(): void
    {
        $m = $this->guard->calculateMortgage(0, 5.5, 25);

        $this->assertEquals(0.0, $m['monthly_payment']);
        $this->assertEquals(0.0, $m['total_payment']);
    }

    public function test_mortgage_with_zero_rate_returns_zeroes(): void
    {
        $m = $this->guard->calculateMortgage(1000000, 0, 25);

        $this->assertEquals(0.0, $m['monthly_payment']);
    }

    public function test_mortgage_min_salary_respects_dti(): void
    {
        $m = $this->guard->calculateMortgage(500000, 5.5, 20);

        $expectedMinSalary = $m['monthly_payment'] / 0.55;
        $this->assertEqualsWithDelta($expectedMinSalary, $m['min_salary_required'], 1.0);
    }

    // ── Generic range ───────────────────────────────────────────

    public function test_validateRange_in_range(): void
    {
        $r = $this->guard->validateRange('custom', 50, 10, 100);
        $this->assertTrue($r->inRange);
    }

    public function test_validateRange_out_of_range(): void
    {
        $r = $this->guard->validateRange('custom', 200, 10, 100);
        $this->assertFalse($r->inRange);
    }

    // ── GuardrailResult DTO ─────────────────────────────────────

    public function test_guardrail_result_toArray(): void
    {
        $r = new GuardrailResult('test', 50, 10, 100, ['assumption1']);

        $arr = $r->toArray();

        $this->assertEquals('test', $arr['metric']);
        $this->assertEquals(50, $arr['value']);
        $this->assertTrue($arr['in_range']);
        $this->assertNull($arr['justification']);
        $this->assertEquals(['assumption1'], $arr['assumptions']);
    }

    public function test_guardrail_result_out_of_range_has_justification(): void
    {
        $r = new GuardrailResult('test', 200, 10, 100);

        $this->assertFalse($r->inRange);
        $this->assertNotNull($r->justification);
        $this->assertStringContainsString('200', $r->justification);
    }
}
