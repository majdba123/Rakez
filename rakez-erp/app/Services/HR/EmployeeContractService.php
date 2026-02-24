<?php

namespace App\Services\HR;

use App\Models\User;
use App\Models\EmployeeContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\Pdf\PdfFactory;
use Exception;

class EmployeeContractService
{
    /**
     * Create a new employee contract.
     */
    public function createContract(int $userId, array $data): EmployeeContract
    {
        $user = User::findOrFail($userId);

        DB::beginTransaction();
        try {
            $contract = EmployeeContract::create([
                'user_id' => $userId,
                'contract_data' => $data['contract_data'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'status' => $data['status'] ?? 'draft',
            ]);

            // Update user's contract_end_date if provided
            if (isset($data['end_date'])) {
                $user->update(['contract_end_date' => $data['end_date']]);
            }

            DB::commit();
            return $contract;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an employee contract.
     */
    public function updateContract(int $contractId, array $data): EmployeeContract
    {
        $contract = EmployeeContract::findOrFail($contractId);

        DB::beginTransaction();
        try {
            $updateData = [];

            if (isset($data['contract_data'])) {
                $updateData['contract_data'] = $data['contract_data'];
            }
            if (isset($data['start_date'])) {
                $updateData['start_date'] = $data['start_date'];
            }
            if (array_key_exists('end_date', $data)) {
                $updateData['end_date'] = $data['end_date'];
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }

            $contract->update($updateData);

            // Update user's contract_end_date if changed
            if (array_key_exists('end_date', $data)) {
                $contract->employee->update(['contract_end_date' => $data['end_date']]);
            }

            DB::commit();
            return $contract->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate PDF for a contract.
     */
    public function generatePdf(int $contractId): string
    {
        $contract = EmployeeContract::with('employee')->findOrFail($contractId);
        $user = $contract->employee;

        $data = [
            'contract' => $contract,
            'employee' => $user,
            'contract_data' => $contract->contract_data,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        $filename = sprintf(
            'contracts/employee_%d_contract_%d_%s.pdf',
            $user->id,
            $contract->id,
            now()->format('Ymd_His')
        );

        Storage::disk('public')->put($filename, PdfFactory::output('contracts.employee', $data));

        $contract->update(['pdf_path' => $filename]);

        return $filename;
    }

    /**
     * Get contracts for a user (paginated).
     *
     * @param int $userId
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getUserContracts(int $userId, int $perPage = 15)
    {
        return EmployeeContract::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get expiring contracts within X days.
     */
    public function getExpiringContracts(int $days = 30): Collection
    {
        return EmployeeContract::with('employee')
            ->expiringWithin($days)
            ->orderBy('end_date', 'asc')
            ->get();
    }

    /**
     * Get expired contracts that are still marked as active.
     */
    public function getExpiredContracts(): Collection
    {
        return EmployeeContract::with('employee')
            ->expired()
            ->get();
    }

    /**
     * Mark expired contracts as expired.
     * Called by scheduler.
     */
    public function markExpiredContracts(): int
    {
        return EmployeeContract::where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->toDateString())
            ->update(['status' => 'expired']);
    }

    /**
     * Activate a contract.
     */
    public function activateContract(int $contractId): EmployeeContract
    {
        $contract = EmployeeContract::findOrFail($contractId);

        if ($contract->status !== 'draft') {
            throw new Exception('Only draft contracts can be activated');
        }

        $contract->update(['status' => 'active']);

        return $contract->fresh();
    }

    /**
     * Terminate a contract.
     */
    public function terminateContract(int $contractId): EmployeeContract
    {
        $contract = EmployeeContract::findOrFail($contractId);

        if (!in_array($contract->status, ['draft', 'active'])) {
            throw new Exception('Contract cannot be terminated');
        }

        $contract->update(['status' => 'terminated']);

        return $contract->fresh();
    }
}

