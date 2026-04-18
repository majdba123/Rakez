<?php

namespace Tests\Unit\Filament;

use App\Support\Filament\ProcessStepper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessStepperTest extends TestCase
{
    #[Test]
    public function it_renders_all_supported_step_states(): void
    {
        app()->setLocale('en');

        $html = ProcessStepper::render([
            ['label' => 'Step One', 'state' => 'completed'],
            ['label' => 'Step Two', 'state' => 'current'],
            ['label' => 'Step Three', 'state' => 'failed'],
            ['label' => 'Step Four', 'state' => 'skipped'],
            ['label' => 'Step Five', 'state' => 'pending'],
        ])->toHtml();

        $this->assertStringContainsString('Step One', $html);
        $this->assertStringContainsString('Step Two', $html);
        $this->assertStringContainsString('Step Three', $html);
        $this->assertStringContainsString('Step Four', $html);
        $this->assertStringContainsString('Step Five', $html);

        $this->assertStringContainsString(__('filament-admin.stepper.state.completed'), $html);
        $this->assertStringContainsString(__('filament-admin.stepper.state.current'), $html);
        $this->assertStringContainsString(__('filament-admin.stepper.state.failed'), $html);
        $this->assertStringContainsString(__('filament-admin.stepper.state.skipped'), $html);
        $this->assertStringContainsString(__('filament-admin.stepper.state.pending'), $html);
    }

    #[Test]
    public function it_escapes_label_and_description_content(): void
    {
        app()->setLocale('en');

        $html = ProcessStepper::render([
            [
                'label' => '<script>alert(1)</script>',
                'state' => 'current',
                'description' => '<b>unsafe</b>',
            ],
        ])->toHtml();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>unsafe</b>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;unsafe&lt;/b&gt;', $html);
    }
}
