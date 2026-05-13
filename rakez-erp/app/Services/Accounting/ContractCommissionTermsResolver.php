<?php

namespace App\Services\Accounting;

use App\Models\Contract;
use Illuminate\Validation\ValidationException;

class ContractCommissionTermsResolver
{
    /**
     * Resolve commission_source (buyer|owner) and commission_percentage from a Contract.
     *
     * @return array{commission_source: string, commission_percentage: float}
     *
     * @throws ValidationException if commission_from or commission_percent is missing/invalid
     */
    public function resolve(Contract $contract): array
    {
        return [
            'commission_source'    => $this->resolveSource($contract),
            'commission_percentage' => $this->resolvePercentage($contract),
        ];
    }

    private function resolveSource(Contract $contract): string
    {
        $raw = $contract->commission_from;

        if ($raw === null || trim((string) $raw) === '') {
            throw ValidationException::withMessages([
                'commission_source' => 'Contract does not have a commission source (commission_from) configured. Please update the contract first.',
            ]);
        }

        $value = strtolower(trim((string) $raw));

        if (str_contains($value, 'مالك') || $value === 'owner') {
            return 'owner';
        }

        if (str_contains($value, 'مشتري') || $value === 'buyer') {
            return 'buyer';
        }

        throw ValidationException::withMessages([
            'commission_source' => "Cannot determine commission source from contract value \"{$raw}\". Set commission_from to owner/buyer (or المالك/المشتري).",
        ]);
    }

    private function resolvePercentage(Contract $contract): float
    {
        $raw = $contract->commission_percent;

        if ($raw === null) {
            throw ValidationException::withMessages([
                'commission_percentage' => 'Contract does not have a commission percentage (commission_percent) configured. Please update the contract first.',
            ]);
        }

        $value = (float) $raw;

        if ($value <= 0) {
            throw ValidationException::withMessages([
                'commission_percentage' => 'Contract commission percentage must be greater than 0.',
            ]);
        }

        return $value;
    }
}
