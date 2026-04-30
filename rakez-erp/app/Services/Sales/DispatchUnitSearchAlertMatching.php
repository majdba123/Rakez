<?php

namespace App\Services\Sales;

use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchUnitSearchAlertMatching
{
    public function dispatchManySafely(array $unitIds, array $context = []): void
    {
        foreach ($this->uniqueUnitIds($unitIds) as $unitId) {
            try {
                app(SalesUnitSearchAlertMatchingService::class)->dispatchForUnit($unitId);
            } catch (Throwable $e) {
                Log::warning('Unit search alert matching dispatch failed', array_merge($context, [
                    'contract_unit_id' => $unitId,
                    'error' => $e->getMessage(),
                ]));
            }
        }
    }

    private function uniqueUnitIds(array $unitIds): array
    {
        return array_values(array_unique(array_map(
            fn ($unitId) => (int) $unitId,
            array_filter($unitIds)
        )));
    }
}
