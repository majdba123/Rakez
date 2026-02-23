<?php

namespace Tests\Golden;

use App\Services\AI\NumericGuardrails;
use App\Services\AI\Tools\FinanceCalculatorTool;

class NumericConsistencyGoldenTest extends GoldenTestCase
{
    private FinanceCalculatorTool $calculator;

    private NumericGuardrails $guardrails;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guardrails = new NumericGuardrails();
        $this->calculator = new FinanceCalculatorTool($this->guardrails);
    }

    /** Q15: Mortgage formula returns correct result within 1 SAR tolerance. */
    public function test_mortgage_payment_accuracy(): void
    {
        $user = $this->createUserForRole('admin');

        $result = $this->calculator->__invoke($user, [
            'calculation_type' => 'mortgage',
            'unit_price' => 1000000,
            'down_payment_percent' => 20,
            'annual_rate' => 5.5,
            'years' => 20,
        ]);

        $pv = 800000;
        $r = (5.5 / 100) / 12;
        $n = 20 * 12;
        $expected = $pv * ($r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);

        $actual = $result['result']['monthly_payment'];
        $this->assertEqualsWithDelta(round($expected), $actual, 1.0, 'Mortgage payment should match formula within 1 SAR');
    }

    /** Q11: Commission split sums to total. */
    public function test_commission_split_sums_to_total(): void
    {
        $user = $this->createUserForRole('admin');

        $result = $this->calculator->__invoke($user, [
            'calculation_type' => 'commission',
            'sale_price' => 1500000,
            'commission_rate' => 2.5,
            'agent_count' => 3,
            'leader_share_percent' => 10,
        ]);

        $data = $result['result'];
        $totalCommission = $data['total_commission'];
        $leaderShare = $data['leader_share'];
        $agentsPool = $data['agents_pool'];
        $perAgent = $data['per_agent'];

        $this->assertEquals($totalCommission, $leaderShare + $agentsPool, 'Leader share + agents pool must equal total commission');
        $this->assertEqualsWithDelta($agentsPool, $perAgent * 3, 1.0, 'Per agent × count must equal agents pool');

        // 1,500,000 × 2.5% = 37,500
        $this->assertEquals(37500, $totalCommission);
    }

    /** Q10: ROMI calculation is correct. */
    public function test_romi_calculation_correctness(): void
    {
        $user = $this->createUserForRole('admin');

        $result = $this->calculator->__invoke($user, [
            'calculation_type' => 'romi',
            'sold_units' => 5,
            'avg_unit_price' => 1000000,
            'marketing_spend' => 100000,
        ]);

        $data = $result['result'];
        $revenue = 5 * 1000000;
        $expectedRomi = round((($revenue - 100000) / 100000) * 100, 1);

        $this->assertEquals($expectedRomi, $data['romi_percent']);
        $this->assertEquals('romi', $data['calculation_type']);
        $this->assertStringContainsString('ROMI', $data['label']);

        $this->assertArrayHasKey('guardrails', $data);
    }

    /** ROMI vs Project ROI distinction. */
    public function test_romi_vs_project_roi_labels(): void
    {
        $user = $this->createUserForRole('admin');

        $romi = $this->calculator->__invoke($user, [
            'calculation_type' => 'romi',
            'sold_units' => 10,
            'avg_unit_price' => 1000000,
            'marketing_spend' => 200000,
        ]);

        $projectRoi = $this->calculator->__invoke($user, [
            'calculation_type' => 'project_roi',
            'total_units' => 100,
            'sold_units' => 10,
            'avg_unit_price' => 1000000,
            'marketing_spend' => 200000,
            'operational_cost' => 500000,
        ]);

        $this->assertEquals('romi', $romi['result']['calculation_type']);
        $this->assertEquals('project_roi', $projectRoi['result']['calculation_type']);
        $this->assertStringContainsString('ROMI', $romi['result']['label']);
        $this->assertStringContainsString('ROI', $projectRoi['result']['label']);
    }

    /** Q17: Payment plan installments are correct. */
    public function test_payment_plan_installments(): void
    {
        $user = $this->createUserForRole('admin');

        $result = $this->calculator->__invoke($user, [
            'calculation_type' => 'payment_plan',
            'unit_price' => 1200000,
            'down_payment_percent' => 10,
            'installments' => 24,
            'grace_period' => false,
        ]);

        $data = $result['result'];
        $this->assertEquals(120000, $data['down_payment']);
        $this->assertEquals(1080000, $data['remaining']);
        $this->assertEquals(45000, $data['monthly_installment']);
    }

    /** Out-of-range values are labeled in guardrails. */
    public function test_out_of_range_cpl_has_guardrail_flag(): void
    {
        $result = $this->guardrails->validateCPL(200);
        $this->assertFalse($result->inRange);
        $this->assertNotNull($result->justification);
    }

    /** Out-of-range close rate is labeled. */
    public function test_out_of_range_close_rate_has_guardrail_flag(): void
    {
        $result = $this->guardrails->validateCloseRate(25);
        $this->assertFalse($result->inRange);
    }

    /** Mortgage formula uses correct DTI. */
    public function test_mortgage_min_salary_uses_sama_dti(): void
    {
        $mortgage = $this->guardrails->calculateMortgage(500000, 5.5, 20);

        $expectedMinSalary = $mortgage['monthly_payment'] / 0.55;
        $this->assertEqualsWithDelta($expectedMinSalary, $mortgage['min_salary_required'], 1.0);
        $this->assertEquals(55, $mortgage['max_dti']);
    }

    /** Tool outputs include inputs echo, assumptions, and guardrails fields. */
    public function test_tool_output_has_structured_fields(): void
    {
        $user = $this->createUserForRole('admin');

        $result = $this->calculator->__invoke($user, [
            'calculation_type' => 'romi',
            'sold_units' => 5,
            'avg_unit_price' => 1000000,
            'marketing_spend' => 100000,
        ]);

        $this->assertArrayHasKey('inputs', $result['result']);
        $this->assertArrayHasKey('assumptions', $result['result']);
        $this->assertArrayHasKey('guardrails', $result['result']);
    }
}
