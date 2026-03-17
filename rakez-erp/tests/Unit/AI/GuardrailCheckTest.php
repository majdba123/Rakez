<?php

namespace Tests\Unit\AI;

use App\Services\AI\GuardrailCheck;
use PHPUnit\Framework\TestCase;

class GuardrailCheckTest extends TestCase
{
    public function test_ok_status(): void
    {
        $check = new GuardrailCheck('cpl', 50.0, 'ok', 'Normal', ['min' => 15, 'max' => 150]);

        $this->assertTrue($check->isOk());
        $this->assertFalse($check->isWarning());
        $this->assertFalse($check->isCritical());
        $this->assertEquals('ok', $check->status());
    }

    public function test_warning_status(): void
    {
        $check = new GuardrailCheck('cpl', 5.0, 'warning', 'Low CPL', ['min' => 15, 'max' => 150]);

        $this->assertFalse($check->isOk());
        $this->assertTrue($check->isWarning());
        $this->assertFalse($check->isCritical());
    }

    public function test_critical_status(): void
    {
        $check = new GuardrailCheck('cpl', 200.0, 'critical', 'High CPL', ['min' => 15, 'max' => 150]);

        $this->assertFalse($check->isOk());
        $this->assertFalse($check->isWarning());
        $this->assertTrue($check->isCritical());
    }

    public function test_to_array(): void
    {
        $check = new GuardrailCheck('roi', 150.0, 'ok', 'Normal ROI', ['min' => 100, 'max' => 2000]);

        $arr = $check->toArray();

        $this->assertEquals('roi', $arr['metric']);
        $this->assertEquals(150.0, $arr['value']);
        $this->assertEquals('ok', $arr['status']);
        $this->assertEquals('Normal ROI', $arr['message']);
        $this->assertEquals(['min' => 100, 'max' => 2000], $arr['range']);
    }

    public function test_accessors(): void
    {
        $check = new GuardrailCheck('dti', 45.0, 'ok', 'Within limit', ['max' => 55]);

        $this->assertEquals('dti', $check->metric());
        $this->assertEquals(45.0, $check->value());
        $this->assertEquals('Within limit', $check->message());
        $this->assertEquals(['max' => 55], $check->range());
    }
}
