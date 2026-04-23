<?php

namespace App\Support\Projects;

use App\Models\SecondPartyData;
use App\Models\Contract;

class ContractReadinessStepBuilder
{
    /**
     * @return array<int, array{label: string, state: string, description?: string}>
     */
    public function stepsForContract(Contract $contract): array
    {
        $contract->loadMissing([
            'info',
            'secondPartyData',
            'contractUnits',
            'boardsDepartment',
            'photographyDepartment',
            'montageDepartment',
        ]);

        $readiness = $contract->checkMarketingReadiness();

        $steps = [
            $this->step(
                'Contract Approval',
                $contract->isApprovedOrCompleted() || $contract->isReady(),
                ! $contract->isApprovedOrCompleted() && ! $contract->isReady(),
                match ($contract->status) {
                    'rejected' => 'The contract was rejected and cannot move forward until reviewed.',
                    'pending' => 'The contract is still awaiting governance approval.',
                    default => null,
                },
                $contract->status === 'rejected',
            ),
            $this->step(
                'Contract Info',
                (bool) $contract->info,
                $contract->isApproved() && ! $contract->info,
                $contract->info ? 'Contract information has been recorded.' : 'Contract information is still missing.',
            ),
            $this->step(
                'Second Party Data',
                SecondPartyData::hasAllCompletionFieldsFilled($contract->secondPartyData),
                $contract->isApprovedOrCompleted() && ! SecondPartyData::hasAllCompletionFieldsFilled($contract->secondPartyData),
                SecondPartyData::hasAllCompletionFieldsFilled($contract->secondPartyData)
                    ? 'Second party completion requirements are satisfied.'
                    : 'Second party completion requirements are still missing.',
            ),
            $this->step(
                'Units Upload',
                $contract->contractUnits->isNotEmpty(),
                $contract->isApprovedOrCompleted() && $contract->contractUnits->isEmpty(),
                $contract->contractUnits->isNotEmpty()
                    ? sprintf('%d units uploaded.', $contract->contractUnits->count())
                    : 'Units CSV has not been uploaded yet.',
            ),
            $this->step(
                'Boards Department',
                (bool) $contract->boardsDepartment?->processed_at,
                $contract->isApprovedOrCompleted() && ! $contract->boardsDepartment?->processed_at,
                $contract->boardsDepartment?->processed_at
                    ? 'Boards department data has been processed.'
                    : 'Boards department data is still pending.',
            ),
            $this->step(
                'Photography Approval',
                $contract->photographyDepartment?->status === 'approved',
                $contract->isApprovedOrCompleted() && $contract->photographyDepartment?->status !== 'approved',
                $this->approvalDescription(
                    $contract->photographyDepartment?->status,
                    $contract->photographyDepartment?->processed_at?->toDateTimeString(),
                ),
                $contract->photographyDepartment?->status === 'rejected',
            ),
            $this->step(
                'Montage Approval',
                $contract->montageDepartment?->status === 'approved',
                $contract->isApprovedOrCompleted() && $contract->montageDepartment?->status !== 'approved',
                $this->approvalDescription(
                    $contract->montageDepartment?->status,
                    $contract->montageDepartment?->processed_at?->toDateTimeString(),
                ),
                $contract->montageDepartment?->status === 'rejected',
            ),
        ];

        $steps[] = [
            'label' => 'Marketing Readiness',
            'state' => $this->finalState($contract, $readiness['ready']),
            'description' => $readiness['ready']
                ? 'All readiness requirements are satisfied.'
                : 'The contract is not fully ready for marketing yet.',
        ];

        return $steps;
    }

    private function step(
        string $label,
        bool $completed,
        bool $current,
        ?string $description = null,
        bool $failed = false,
    ): array {
        $state = $failed
            ? 'failed'
            : ($completed
                ? 'completed'
                : ($current ? 'current' : 'pending'));

        return array_filter([
            'label' => $label,
            'state' => $state,
            'description' => $description,
        ], fn (mixed $value): bool => $value !== null);
    }

    private function approvalDescription(?string $status, ?string $processedAt): string
    {
        if ($status === 'approved') {
            return $processedAt
                ? "Approved at {$processedAt}."
                : 'Approved.';
        }

        if ($status === 'rejected') {
            return 'Rejected and requires a corrected resubmission.';
        }

        if ($processedAt) {
            return "Processed at {$processedAt} and awaiting approval.";
        }

        return 'Awaiting department submission or approval.';
    }

    private function finalState(Contract $contract, bool $ready): string
    {
        if ($contract->status === 'rejected') {
            return 'failed';
        }

        if ($ready) {
            return 'completed';
        }

        if ($contract->isApprovedOrCompleted()) {
            return 'current';
        }

        return 'pending';
    }
}
