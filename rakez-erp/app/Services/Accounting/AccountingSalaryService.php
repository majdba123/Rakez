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

        // Calculate commissions for each employee — عرض صحيح: اسم الموظف والمسمى والقسم دائماً مع fallback
        return $employees->map(function ($employee) use ($month, $year) {
            $totalCommissions = $this->getEmployeeCommissionsForMonth($employee->id, $month, $year);
            $existingDistribution = $employee->salaryDistributions->first();
            $baseSalary = $employee->salary !== null ? (float) $employee->salary : 0;
            $totalAmount = $baseSalary + $totalCommissions;

            return [
                'user_id' => $employee->id,
                'name' => $employee->name ?? 'غير محدد',
                'employee_name' => $employee->name ?? 'غير محدد',
                'job_title' => $employee->job_title ?? '—',
                'department' => $employee->department ?? $employee->team?->name ?? '—',
                'type' => $employee->type,
                'team_name' => $employee->team?->name ?? '—',
                'phone' => $employee->phone ?? null,
                'email' => $employee->email ?? null,
                'base_salary' => $baseSalary,
                'commission_eligibility' => (bool) $employee->commission_eligibility,
                'total_commissions' => round($totalCommissions, 2),
                'total_amount' => round($totalAmount, 2),
                'distribution_status' => $existingDistribution?->status ?? 'not_created',
                'distribution_id' => $existingDistribution?->id ?? null,
                'distribution' => $existingDistribution ? [
                    'id' => $existingDistribution->id,
                    'base_salary' => (float) $existingDistribution->base_salary,
                    'total_commissions' => (float) $existingDistribution->total_commissions,
                    'total_amount' => (float) $existingDistribution->total_amount,
                    'status' => $existingDistribution->status,
                ] : null,
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
            ->whereIn('status', ['approved', 'paid'])
            ->whereHas('commission', function ($q) use ($startDate, $endDate) {
                $q->whereDate('approved_at', '>=', $startDate)
                    ->whereDate('approved_at', '<=', $endDate);
            })
            ->sum('amount');

        return (float) $total;
    }

    /**
     * تفصيل عمولات الموظف للشهر من توزيعات العمولة الفعلية (معتمدة/مدفوعة) مجمّعة حسب المشروع
     * لمعرفة كيف وصل إجمالي العمولات — كل مشروع مع قائمة السطور (وحدة، نوع، مبلغ).
     */
    public function getEmployeeCommissionBreakdownForMonth(int $userId, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $distributions = CommissionDistribution::with([
            'commission.contractUnit',
            'commission.salesReservation.contract',
        ])
            ->where('user_id', $userId)
            ->whereIn('status', ['approved', 'paid'])
            ->whereHas('commission', function ($q) use ($startDate, $endDate) {
                $q->whereDate('approved_at', '>=', $startDate)
                    ->whereDate('approved_at', '<=', $endDate);
            })
            ->get();

        $typeLabels = config('commission_distribution.type_labels', []);
        $lines = $distributions->map(function ($dist) use ($typeLabels) {
            $comm = $dist->commission;
            return [
                'distribution_id' => $dist->id,
                'project_name' => $comm?->salesReservation?->contract?->project_name ?? '—',
                'unit_number' => $comm?->contractUnit?->unit_number ?? '—',
                'commission_type' => $dist->type,
                'commission_type_label' => $typeLabels[$dist->type] ?? $dist->type,
                'percentage' => $dist->percentage !== null ? round((float) $dist->percentage, 2) : null,
                'amount' => round((float) $dist->amount, 2),
                'status' => $dist->status,
            ];
        });

        $byProject = $lines->groupBy('project_name')->map(function ($items, $projectName) {
            return [
                'project_name' => $projectName,
                'total_commission' => round($items->sum('amount'), 2),
                'details' => $items->values()->all(),
            ];
        })->values()->all();

        return [
            'by_project' => $byProject,
            'total_commissions' => round($lines->sum('amount'), 2),
        ];
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
            ->where('status', 'confirmed')
            ->whereDate('confirmed_at', '>=', $startDate)
            ->whereDate('confirmed_at', '<=', $endDate)
            ->get();

        $totalCommissions = $this->getEmployeeCommissionsForMonth($userId, $month, $year);
        $baseSalary = $employee->salary !== null ? (float) $employee->salary : 0;

        $soldUnitsList = $soldUnits->map(function ($reservation) {
            $employeeDistribution = $reservation->commission?->distributions->first();
            return [
                'reservation_id' => $reservation->id,
                'project_name' => $reservation->contract?->project_name ?? '—',
                'unit_number' => $reservation->contractUnit?->unit_number ?? '—',
                'final_selling_price' => $reservation->proposed_price,
                'confirmed_at' => $reservation->confirmed_at,
                'commission_percentage' => $employeeDistribution ? round((float) $employeeDistribution->percentage, 2) : 0,
                'commission_amount' => $employeeDistribution ? round((float) $employeeDistribution->amount, 2) : 0,
                'commission_status' => $employeeDistribution?->status ?? 'none',
            ];
        });

        // تفصيل العمولات حسب المشروع: كيف وصل الإجمالي — كل مشروع مع قائمة السطور (وحدة، نوع عمولة، مبلغ)
        $breakdown = $this->getEmployeeCommissionBreakdownForMonth($userId, $month, $year);
        $commissionsByProject = $breakdown['by_project'];

        $salaryDistribution = AccountingSalaryDistribution::where('user_id', $userId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name ?? 'غير محدد',
                'employee_name' => $employee->name ?? 'غير محدد',
                'job_title' => $employee->job_title ?? '—',
                'department' => $employee->department ?? $employee->team?->name ?? '—',
                'type' => $employee->type,
                'team_name' => $employee->team?->name ?? '—',
                'phone' => $employee->phone ?? null,
                'email' => $employee->email ?? null,
                'base_salary' => $baseSalary,
                'commission_eligibility' => (bool) $employee->commission_eligibility,
            ],
            'period' => [
                'month' => $month,
                'year' => $year,
            ],
            'salary_distribution' => $salaryDistribution ? [
                'id' => $salaryDistribution->id,
                'base_salary' => (float) $salaryDistribution->base_salary,
                'total_commissions' => (float) $salaryDistribution->total_commissions,
                'total_amount' => (float) $salaryDistribution->total_amount,
                'status' => $salaryDistribution->status,
            ] : null,
            'sold_units' => $soldUnitsList->values()->all(),
            'commissions_by_project' => $commissionsByProject,
            'commissions_total' => $breakdown['total_commissions'],
            'summary' => [
                'units_sold' => $soldUnits->count(),
                'total_sales_value' => round((float) $soldUnits->sum('proposed_price'), 2),
                'total_commissions' => round($totalCommissions, 2),
                'base_salary' => $baseSalary,
                'total_amount' => round($baseSalary + $totalCommissions, 2),
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
