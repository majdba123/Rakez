<?php

namespace App\Support\Filament;

use Illuminate\Support\HtmlString;

class ProcessStepper
{
    /**
     * @param  array<int, array{label:string, state:string, description?:string}>  $steps
     */
    public static function render(array $steps): HtmlString
    {
        $items = array_map(
            function (array $step, int $index): string {
                $state = $step['state'] ?? 'pending';
                $label = e($step['label'] ?? '');
                $description = isset($step['description']) ? e($step['description']) : null;

                [$badgeClasses, $itemClasses, $stateLabel] = match ($state) {
                    'completed' => ['bg-success-600 text-white border-success-600', 'border-success-200 bg-success-50', __('filament-admin.stepper.state.completed')],
                    'current' => ['bg-primary-600 text-white border-primary-600', 'border-primary-300 bg-primary-50', __('filament-admin.stepper.state.current')],
                    'failed' => ['bg-danger-600 text-white border-danger-600', 'border-danger-200 bg-danger-50', __('filament-admin.stepper.state.failed')],
                    'skipped' => ['bg-gray-400 text-white border-gray-400', 'border-gray-200 bg-gray-50', __('filament-admin.stepper.state.skipped')],
                    default => ['bg-gray-100 text-gray-700 border-gray-300', 'border-gray-200 bg-white', __('filament-admin.stepper.state.pending')],
                };

                $descriptionHtml = $description
                    ? '<div class="text-xs text-gray-500">'.$description.'</div>'
                    : '';

                $stepNumber = $index + 1;

                return <<<HTML
<li class="rounded-lg border p-3 {$itemClasses}">
    <div class="flex items-start gap-3">
        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs font-bold {$badgeClasses}">{$stepNumber}</span>
        <div class="min-w-0">
            <div class="text-sm font-semibold text-gray-900">{$label}</div>
            <div class="text-xs text-gray-600">{$stateLabel}</div>
            {$descriptionHtml}
        </div>
    </div>
</li>
HTML;
            },
            $steps,
            array_keys($steps),
        );

        return new HtmlString(
            '<ol class="grid gap-2 md:grid-cols-2 xl:grid-cols-4">'.implode('', $items).'</ol>'
        );
    }
}
