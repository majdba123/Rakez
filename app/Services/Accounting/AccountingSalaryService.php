<?php

namespace App\Services\Accounting;

use App\Models\User;
use App\Models\AccountingSalaryDistribution;
use App\Models\CommissionDistribution;
use App\Models\SalesReservation;
use Illuminate\Support\Facades\DB;
use Exception;

class AccountingSalaryService
{
    /**
     * Get salaries with commissions for all employees.
     */
    public function getSalariesWithCommissions(int $month, int $year, array $filters = [])
    {
        $query = User::with(['team', 'salaryDistributions' => function ($q) use ($month, $year) {
            $q->forPeriod($year, $month);
        }])
            ->where('is_active', true)
            ->whereNotNull('salary');

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (isset($filters['commission_eligible'])) {
            $query->where('commission_eligibility', $filters['commission_eligible']);
        }

        $employees = $query->get();

        // Calculate commissions for each employee
        return $employees->map(function ($employee) use ($month, $year) {
            $totalCommissions = $this->getEmployeeCommissionsForMonth($employee->id, $month, $year);
            $existingDistribution = $employee->salaryDistributions->first();

            return [
                'user_id' => $employee->id,
                'name' => $employee->name,
                'job_title' => $employee->job_title,
                'type' => $employee->type,
                'team_name' => $employee->team?->name ?? null,
                'base_salary' => $employee->salary,
                'commission_eligibility' => $employee->commission_eligibility,
                'total_commissions' => $totalCommissions,
                'total_amount' => $employee->salary + $totalCommissions,
                'distribution_status' => $existingDistribution?->status ?? 'not_created',
                'distribution_id' => $existingDistribution?->id ?? null,
            ];
        });
    }

    /**
     * Get employee's total commissions for a specific month.
     */
    public function getEmployeeCommissionsForMonth(int $userId, int $month, int $year): float
    {
        // Get approved commission distributions for this employee in the period
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month

        $total = CommissionDistribution::where('user_id', $userId)
            ->where('status', 'approved')
            ->whereHas('commission', function ($q) use ($startDate, $endDate) {
                $q->whereDate('approved_at', '>=', $startDate)
                    ->whereDate('approved_at', '<=', $endDate);
            })
            ->sum('amount');

        return (float) $total;
    }

    /**
     * Get employee detail with sold units for the month.
     */
    public function getEmployeeSoldUnits(int $userId, int $month, int $year): array
    {
        $employee = User::with(['team'])->findOrFail($userId);

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        // Get sold units (confirmed reservations) for this employee
        $soldUnits = SalesReservation::with([
            'contract',
            'contractUnit',
            'commission.distributions' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            }
        ])
            ->where('marketing_employee_id', $userId)
            ->where('status', \App\Constants\ReservationStatus::CONFIRMED)
            ->whereDate('confirmed_at', '>=', $startDate)
            ->whereDate('confirmed_at', '<=', $endDate)
            ->get();

        $totalCommissions = $this->getEmployeeCommissionsForMonth($userId, $month, $year);

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'job_title' => $employee->job_title,
                'type' => $employee->type,
                'team_name' => $employee->team?->name ?? null,
                'base_salary' => $employee->salary,
                'commission_eligibility' => $employee->commission_eligibility,
            ],
            'period' => [
                'month' => $month,
                'year' => $year,
            ],
            'sold_units' => $soldUnits->map(function ($reservation) {
                $employeeDistribution = $reservation->commission?->distributions->first();

                return [
                    'reservation_id' => $reservation->id,
                    'project_name' => $reservation->contract?->project_name ?? null,
                    'unit_number' => $reservation->contractUnit?->unit_number ?? null,
                    'final_selling_price' => $reservation->proposed_price,
                    'confirmed_at' => $reservation->confirmed_at,
                    'commission_percentage' => $employeeDistribution?->percentage ?? 0,
                    'commission_amount' => $employeeDistribution?->amount ?? 0,
                    'commission_status' => $employeeDistribution?->status ?? 'none',
                ];
            }),
            'summary' => [
                'units_sold' => $soldUnits->count(),
                'total_sales_value' => $soldUnits->sum('proposed_price'),
                'total_commissions' => $totalCommissions,
                'base_salary' => $employee->salary,
                'total_amount' => $employee->salary + $totalCommissions,
            ],
        ];
    }

    /**
     * Create salary distribution for an employee.
     */
    public function createSalaryDistribution(int $userId, int $month, int $year): AccountingSalaryDistribution
    {
        $employee = User::findOrFail($userId);

        if (!$employee->salary) {
            throw new Exception('Employee does not have a salary defined.');
        }

        // Check if distribution already exists
        $existing = AccountingSalaryDistribution::where('user_id', $userId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($existing) {
            throw new Exception('Salary distribution already exists for this period.');
        }

        DB::beginTransaction();
        try {
            $totalCommissions = $this->getEmployeeCommissionsForMonth($userId, $month, $year);

            $distribution = new AccountingSalaryDistribution([
                'user_id' => $userId,
                'month' => $month,
                'year' => $year,
                'base_salary' => $employee->salary,
                'total_commissions' => $totalCommissions,
                'status' => 'pending',
            ]);

            $distribution->calculateTotalAmount();
            $distribution->save();

            DB::commit();
            return $distribution->fresh(['user']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Approve salary distribution.
     */
    public function approveSalaryDistribution(int $distributionId): AccountingSalaryDistribution
    {
        $distribution = AccountingSalaryDistribution::findOrFail($distributionId);

        if ($distribution->status !== 'pending') {
            throw new Exception('Only pending distributions can be approved.');
        }

        $distribution->approve();
        return $distribution->fresh(['user']);
    }

    /**
     * Mark salary distribution as paid.
     */
    public function markSalaryDistributionAsPaid(int $distributionId): AccountingSalaryDistribution
    {
        $distribution = AccountingSalaryDistribution::findOrFail($distributionId);

        if ($distribution->status !== 'approved') {
            throw new Exception('Only approved distributions can be marked as paid.');
        }

        $distribution->markAsPaid();
        return $distribution->fresh(['user']);
    }
}
