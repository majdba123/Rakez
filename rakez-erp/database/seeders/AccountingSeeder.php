<?php

namespace Database\Seeders;

use App\Models\AccountingSalaryDistribution;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\Deposit;
use App\Models\SalesReservation;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * سيدر المحاسبة — ترابط الجداول:
 *
 * SalesReservation (confirmed) → contract_id, contract_unit_id, marketing_employee_id
 *   ↓
 * Commission (one per sales_reservation_id) → contract_unit_id, approved_at, net_amount
 *   ↓
 * CommissionDistribution (many per commission_id) → user_id, type, percentage, amount, status (approved/paid)
 *   ↓
 * AccountingSalaryDistribution (per user_id + month + year) → total_commissions = sum(CommissionDistribution.amount)
 *   where commission.approved_at in month and distribution.status in [approved, paid]
 *
 * Deposit → sales_reservation_id, contract_id, contract_unit_id (من الحجز).
 * UserNotification → user_id (للمحاسبة).
 */
class AccountingSeeder extends Seeder
{
    /** Distribution types that sum to 100% (مطابق لـ config('commission_distribution.types')). */
    private const DISTRIBUTION_TEMPLATES = [
        [['lead_generation', 25], ['persuasion', 30], ['closing', 35], ['team_leader', 10]],
        [['lead_generation', 20], ['persuasion', 25], ['closing', 40], ['sales_manager', 15]],
        [['lead_generation', 30], ['persuasion', 25], ['closing', 30], ['team_leader', 10], ['project_manager', 5]],
        [['lead_generation', 25], ['persuasion', 30], ['closing', 30], ['external_marketer', 15]],
        [['lead_generation', 25], ['persuasion', 30], ['closing', 25], ['assistant_pm', 5], ['owner', 10], ['management', 5]],
    ];

    /** Accounting notification messages (Arabic) for filtering by type. */
    private const NOTIFICATION_MESSAGES = [
        'unit_reserved' => 'تم حجز وحدة جديدة - المشروع: %s - الوحدة: %s - العميل: %s',
        'deposit_received' => 'تم استلام عربون بمبلغ %s ريال سعودي - المشروع: %s - الوحدة: %s - العميل: %s',
        'unit_vacated' => 'تم إخلاء وحدة - المشروع: %s - الوحدة: %s',
        'reservation_cancelled' => 'تم إلغاء حجز - المشروع: %s - الوحدة: %s - العميل: %s',
        'commission_confirmed' => 'تم تأكيد عمولة - المبلغ الصافي: %s ريال سعودي - المشروع: %s - الوحدة: %s',
        'commission_received' => 'تم استلام عمولة من المالك - المبلغ: %s ريال سعودي - المشروع: %s - الوحدة: %s',
    ];

    public function run(): void
    {
        $counts = SeedCounts::all();

        $accountingUsers = User::where('type', 'accounting')->pluck('id')->all();
        $admins = User::where('type', 'admin')->pluck('id')->all();
        $accountingPool = $accountingUsers ?: $admins;

        $confirmedReservations = SalesReservation::where('status', 'confirmed')->get();
        $commissionIds = [];

        if ($confirmedReservations->isNotEmpty()) {
            $this->seedDeposits($confirmedReservations, $accountingPool, $counts['deposits']);
            $commissionIds = $this->seedCommissions($confirmedReservations, $accountingPool, $counts['commissions']);
            $this->seedCommissionDistributions($commissionIds, $accountingPool);
        }

        // الرواتب مبنية على مجموع العمولات المعتمدة فعلياً لكل موظف لكل شهر (ربط صحيح)
        $this->seedSalaryDistributionsFromCommissions($counts['salary_distributions']);
        $this->ensureEmployeeDisplayData();
        $this->seedAccountingNotifications($confirmedReservations, $accountingUsers ?: $admins);
    }

    /**
     * التأكد من وجود بيانات عرض للموظفين (المسمى الوظيفي، القسم) لمن لديهم راتب.
     */
    private function ensureEmployeeDisplayData(): void
    {
        $jobTitles = ['مدير مبيعات', 'منسق مبيعات', 'مسوق عقاري', 'مدير مشروع', 'محاسب', 'موظف مبيعات', 'موظف تسويق', 'قائد فريق'];
        $departments = ['المبيعات', 'التسويق', 'المحاسبة', 'الائتمان', 'المشاريع', 'الموارد البشرية'];

        User::where('is_active', true)
            ->whereNotNull('salary')
            ->where('salary', '>', 0)
            ->get()
            ->each(function (User $user) use ($jobTitles, $departments) {
                $updates = [];
                if (empty($user->job_title)) {
                    $updates['job_title'] = $jobTitles[array_rand($jobTitles)];
                }
                if (empty($user->department)) {
                    $updates['department'] = $departments[array_rand($departments)];
                }
                if ($updates !== []) {
                    $user->update($updates);
                }
            });
    }

    private function seedDeposits($confirmedReservations, array $accountingPool, int $targetDeposits): void
    {
        $statuses = ['pending', 'received', 'confirmed', 'refunded'];
        $created = 0;

        foreach ($confirmedReservations as $index => $reservation) {
            if ($created >= $targetDeposits) {
                break;
            }
            if (!$reservation->contract_id || !$reservation->contract_unit_id) {
                continue;
            }
            $status = $statuses[$index % 4];
            Deposit::create($this->depositAttributes($reservation, $status, $accountingPool));
            $created++;
        }

        $additional = max(0, $targetDeposits - $created);
        for ($i = 0; $i < $additional; $i++) {
            $reservation = $confirmedReservations->random();
            $status = $statuses[$i % 4];
            Deposit::create($this->depositAttributes($reservation, $status, $accountingPool));
        }
    }

    private function depositAttributes(SalesReservation $reservation, string $status, array $accountingPool): array
    {
        $attrs = [
            'sales_reservation_id' => $reservation->id,
            'contract_id' => $reservation->contract_id,
            'contract_unit_id' => $reservation->contract_unit_id,
            'amount' => fake()->randomFloat(2, 5000, 80000),
            'payment_method' => $reservation->payment_method,
            'client_name' => $reservation->client_name,
            'payment_date' => now()->subDays(fake()->numberBetween(0, 60)),
            'commission_source' => $status === 'refunded' ? 'owner' : Arr::random(['owner', 'buyer']),
            'status' => $status,
            'notes' => fake()->optional(0.8)->passthrough('تم استلام المبلغ من العميل وفق الوثائق المرفقة.'),
        ];
        if (in_array($status, ['confirmed', 'refunded'])) {
            $attrs['confirmed_by'] = Arr::random($accountingPool);
            $attrs['confirmed_at'] = now()->subDays(fake()->numberBetween(0, 10));
        }
        if ($status === 'refunded') {
            $attrs['refunded_at'] = now()->subDays(fake()->numberBetween(1, 5));
        }
        return $attrs;
    }

    private function seedCommissions($confirmedReservations, array $accountingPool, int $targetCommissions): array
    {
        $ids = $confirmedReservations->pluck('id')->shuffle()->take(min($targetCommissions, $confirmedReservations->count()))->all();
        $commissionIds = [];

        // تواريخ في الشهر الحالي والماضي لربط العمولات بالرواتب (approved_at ضمن الشهر)
        $now = now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();

        foreach ($ids as $index => $reservationId) {
            $reservation = $confirmedReservations->firstWhere('id', $reservationId);
            if (!$reservation || !$reservation->contract_id || !$reservation->contract_unit_id) {
                continue;
            }
            $unit = ContractUnit::find($reservation->contract_unit_id);
            $finalPrice = $unit?->price ?? fake()->randomFloat(2, 500000, 2000000);
            $percentage = fake()->randomFloat(2, 1.5, 4.5);
            $totalAmount = ($finalPrice * $percentage) / 100;
            $vat = ($totalAmount * 15) / 100;
            $marketingExpenses = fake()->numberBetween(0, 6000);
            $bankFees = fake()->numberBetween(0, 1500);
            $netAmount = $totalAmount - $vat - $marketingExpenses - $bankFees;

            // جزء من العمولات معتمد و approved_at ضمن الشهر الحالي أو الماضي (لظهورها في الرواتب)
            $isApprovedForSalary = ($index % 3) !== 2; // ثلثان معتمدون
            $status = $isApprovedForSalary ? Arr::random(['approved', 'approved', 'paid']) : Arr::random(['pending', 'approved']);
            $approvedAt = $isApprovedForSalary
                ? ($index % 2 === 0 ? $currentMonthStart->copy()->addDays(fake()->numberBetween(1, 15)) : $lastMonthStart->copy()->addDays(fake()->numberBetween(1, 28)))
                : now()->subDays(fake()->numberBetween(0, 60));

            $commission = Commission::firstOrCreate(
                ['sales_reservation_id' => $reservationId],
                [
                    'contract_unit_id' => $reservation->contract_unit_id,
                    'final_selling_price' => $finalPrice,
                    'commission_percentage' => $percentage,
                    'total_amount' => $totalAmount,
                    'vat' => $vat,
                    'marketing_expenses' => $marketingExpenses,
                    'bank_fees' => $bankFees,
                    'net_amount' => $netAmount,
                    'commission_source' => Arr::random(['owner', 'buyer']),
                    'team_responsible' => fake()->optional()->word(),
                    'status' => $status,
                    'approved_at' => $status !== 'pending' ? $approvedAt : null,
                    'paid_at' => $status === 'paid' ? $approvedAt?->copy()->addDays(2) : null,
                ]
            );
            $commissionIds[] = $commission->id;
        }

        return $commissionIds;
    }

    private function seedCommissionDistributions(array $commissionIds, array $accountingPool): void
    {
        // مستفيدون يمكن تعيينهم في التوزيعات ليظهر اسم الموظف في الجدول (sales, marketing, project_management)
        $beneficiaryUserIds = User::where('is_active', true)
            ->whereIn('type', ['sales', 'marketing', 'project_management'])
            ->pluck('id')
            ->all();
        if (empty($beneficiaryUserIds)) {
            $beneficiaryUserIds = User::where('is_active', true)->where('type', '!=', 'admin')->pluck('id')->all();
        }
        if (empty($beneficiaryUserIds)) {
            $beneficiaryUserIds = User::pluck('id')->all();
        }

        // نسبة جيدة معتمدة أو مدفوعة ليظهر اسم الموظف والعمولة في الرواتب
        $statuses = ['approved', 'approved', 'paid', 'pending', 'rejected'];

        foreach ($commissionIds as $idx => $commissionId) {
            $commission = Commission::find($commissionId);
            if (!$commission || $commission->net_amount <= 0) {
                continue;
            }

            $template = self::DISTRIBUTION_TEMPLATES[$idx % count(self::DISTRIBUTION_TEMPLATES)];
            // إذا العمولة معتمدة (لها approved_at) نجعل التوزيعات معتمدة/مدفوعة لاحتسابها في الرواتب
            $status = $commission->approved_at
                ? Arr::random(['approved', 'approved', 'paid'])
                : $statuses[$idx % count($statuses)];

            foreach ($template as $item) {
                [$type, $percentage] = $item;
                $amount = ($commission->net_amount * $percentage) / 100;

                $data = [
                    'commission_id' => $commissionId,
                    'type' => $type,
                    'percentage' => $percentage,
                    'amount' => $amount,
                    'status' => $status,
                    'notes' => fake()->optional(0.8)->passthrough('تم استلام المبلغ من العميل وفق الوثائق المرفقة.'),
                ];

                if (in_array($type, ['external_marketer', 'other'], true)) {
                    $data['external_name'] = fake()->name();
                    $data['bank_account'] = 'SA' . fake()->numerify('######################');
                    $data['user_id'] = null;
                } else {
                    // دائماً تعيين موظف حقيقي ليظهر اسم المستفيد في جدول توزيع العمولات
                    $data['user_id'] = $beneficiaryUserIds[array_rand($beneficiaryUserIds)];
                }

                // محاذاة تاريخ اعتماد التوزيعة مع شهر عمولة العمولة (commission.approved_at) لترابط صحيح مع الرواتب
                if (in_array($status, ['approved', 'rejected', 'paid'])) {
                    $data['approved_by'] = Arr::random($accountingPool);
                    $data['approved_at'] = $commission->approved_at
                        ? $commission->approved_at->copy()->addMinutes(fake()->numberBetween(0, 60))
                        : now()->subDays(fake()->numberBetween(1, 10));
                }
                if ($status === 'paid') {
                    $data['paid_at'] = $data['approved_at'] ?? now()->subDays(fake()->numberBetween(1, 5));
                }

                CommissionDistribution::create($data);
            }
        }
    }

    /**
     * إنشاء توزيعات الرواتب مرتبطة بجدول العمولات: لكل موظف وشهر، total_commissions = مجموع توزيعات العمولة المعتمدة في ذلك الشهر.
     */
    private function seedSalaryDistributionsFromCommissions(int $targetCount): void
    {
        $now = now();
        $prevMonth = $now->copy()->subMonth();
        $months = [
            ['year' => $now->year, 'month' => $now->month],
            ['year' => $prevMonth->year, 'month' => $prevMonth->month],
        ];
        $currentYear = $now->year;
        $currentMonth = $now->month;

        // موظفون لديهم راتب (لأخذ base_salary منهم)
        $employeesWithSalary = User::where('is_active', true)
            ->whereNotNull('salary')
            ->where('salary', '>', 0)
            ->get(['id', 'salary']);
        if ($employeesWithSalary->isEmpty()) {
            return;
        }

        $created = 0;
        foreach ($months as $period) {
            $year = (int) $period['year'];
            $month = (int) $period['month'];
            $startDate = sprintf('%04d-%02d-01', $year, $month);
            $endDate = date('Y-m-t', strtotime($startDate));

            foreach ($employeesWithSalary as $user) {
                if ($created >= $targetCount) {
                    break 2;
                }
                $userId = $user->id;
                $baseSalary = (float) $user->salary;

                // مجموع العمولات المعتمدة/المدفوعة لهذا الموظف في هذا الشهر (نفس منطق AccountingSalaryService)
                $totalCommissions = (float) CommissionDistribution::where('user_id', $userId)
                    ->whereIn('status', ['approved', 'paid'])
                    ->whereHas('commission', function ($q) use ($startDate, $endDate) {
                        $q->whereDate('approved_at', '>=', $startDate)
                            ->whereDate('approved_at', '<=', $endDate);
                    })
                    ->sum('amount');

                AccountingSalaryDistribution::updateOrCreate(
                    ['user_id' => $userId, 'month' => $month, 'year' => $year],
                    [
                        'user_id' => $userId,
                        'month' => $month,
                        'year' => $year,
                        'base_salary' => $baseSalary,
                        'total_commissions' => round($totalCommissions, 2),
                        'total_amount' => round($baseSalary + $totalCommissions, 2),
                        'status' => fake()->randomElement(['pending', 'approved', 'paid']),
                        'paid_at' => fake()->optional(0.3)->dateTimeThisYear(),
                        'notes' => $totalCommissions > 0 ? 'عمولات معتمدة من توزيع العمولات لهذا الشهر' : null,
                    ]
                );
                $created++;
            }
        }

        // إن لم نصل للعدد المطلوب، نضيف سجلات لشهور إضافية
        if ($created < $targetCount) {
            $extraNeeded = $targetCount - $created;
            $prev2 = now()->subMonths(2);
            $allEmployees = User::where('is_active', true)->whereNotNull('salary')->where('salary', '>', 0)->get(['id', 'salary']);
            $pairs = [];
            foreach ($allEmployees as $u) {
                foreach ([[$currentYear, $currentMonth], [$prev2->year, $prev2->month]] as [$y, $m]) {
                    $pairs[] = ['user_id' => $u->id, 'month' => $m, 'year' => $y, 'base_salary' => (float) $u->salary];
                }
            }
            shuffle($pairs);
            foreach (array_slice($pairs, 0, $extraNeeded) as $p) {
                $startDate = sprintf('%04d-%02d-01', $p['year'], $p['month']);
                $endDate = date('Y-m-t', strtotime($startDate));
                $totalCommissions = (float) CommissionDistribution::where('user_id', $p['user_id'])
                    ->whereIn('status', ['approved', 'paid'])
                    ->whereHas('commission', function ($q) use ($startDate, $endDate) {
                        $q->whereDate('approved_at', '>=', $startDate)->whereDate('approved_at', '<=', $endDate);
                    })
                    ->sum('amount');
                AccountingSalaryDistribution::firstOrCreate(
                    ['user_id' => $p['user_id'], 'month' => $p['month'], 'year' => $p['year']],
                    [
                        'base_salary' => $p['base_salary'],
                        'total_commissions' => round($totalCommissions, 2),
                        'total_amount' => round($p['base_salary'] + $totalCommissions, 2),
                        'status' => fake()->randomElement(['pending', 'approved', 'paid']),
                        'paid_at' => fake()->optional(0.3)->dateTimeThisYear(),
                    ]
                );
            }
        }
    }

    private function seedAccountingNotifications($confirmedReservations, array $accountingUserIds): void
    {
        if (empty($accountingUserIds)) {
            return;
        }

        $contracts = Contract::whereIn('id', $confirmedReservations->pluck('contract_id')->unique())->get()->keyBy('id');
        $units = ContractUnit::whereIn('id', $confirmedReservations->pluck('contract_unit_id')->unique())->get()->keyBy('id');
        $commissions = Commission::whereIn('sales_reservation_id', $confirmedReservations->pluck('id'))->get();
        $deposits = Deposit::whereIn('sales_reservation_id', $confirmedReservations->pluck('id'))->get();

        $notifications = [];

        foreach (['unit_reserved', 'deposit_received', 'unit_vacated', 'reservation_cancelled', 'commission_confirmed', 'commission_received'] as $type) {
            $count = fake()->numberBetween(3, 8);
            for ($i = 0; $i < $count; $i++) {
                $reservation = $confirmedReservations->random();
                $contract = $contracts->get($reservation->contract_id);
                $unit = $units->get($reservation->contract_unit_id);
                $projectName = $contract?->project_name ?? 'مشروع تجريبي';
                $unitNumber = $unit?->unit_number ?? 'U-' . $reservation->contract_unit_id;
                $clientName = $reservation->client_name ?? 'عميل';

                $message = match ($type) {
                    'unit_reserved' => sprintf(self::NOTIFICATION_MESSAGES['unit_reserved'], $projectName, $unitNumber, $clientName),
                    'deposit_received' => sprintf(
                        self::NOTIFICATION_MESSAGES['deposit_received'],
                        number_format($deposits->where('sales_reservation_id', $reservation->id)->first()?->amount ?? 10000),
                        $projectName,
                        $unitNumber,
                        $clientName
                    ),
                    'unit_vacated' => sprintf(self::NOTIFICATION_MESSAGES['unit_vacated'], $projectName, $unitNumber),
                    'reservation_cancelled' => sprintf(self::NOTIFICATION_MESSAGES['reservation_cancelled'], $projectName, $unitNumber, $clientName),
                    'commission_confirmed' => sprintf(
                        self::NOTIFICATION_MESSAGES['commission_confirmed'],
                        number_format($commissions->where('sales_reservation_id', $reservation->id)->first()?->net_amount ?? 5000),
                        $projectName,
                        $unitNumber
                    ),
                    'commission_received' => sprintf(
                        self::NOTIFICATION_MESSAGES['commission_received'],
                        number_format($commissions->where('sales_reservation_id', $reservation->id)->first()?->net_amount ?? 5000),
                        $projectName,
                        $unitNumber
                    ),
                    default => null,
                };

                if ($message) {
                    foreach ($accountingUserIds as $userId) {
                        $notifications[] = [
                            'user_id' => $userId,
                            'message' => $message,
                            'status' => fake()->randomElement(['pending', 'read']),
                            'created_at' => now()->subDays(fake()->numberBetween(0, 14)),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($notifications, 100) as $chunk) {
            UserNotification::insert($chunk);
        }
    }
}
