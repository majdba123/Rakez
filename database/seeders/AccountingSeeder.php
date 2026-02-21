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

class AccountingSeeder extends Seeder
{
    /** Distribution types that sum to 100% (lead, persuasion, closing, management). */
    private const DISTRIBUTION_TEMPLATES = [
        [['lead_generation', 25], ['persuasion', 30], ['closing', 35], ['team_leader', 10]],
        [['lead_generation', 20], ['persuasion', 25], ['closing', 40], ['sales_manager', 15]],
        [['lead_generation', 30], ['persuasion', 25], ['closing', 30], ['team_leader', 10], ['project_manager', 5]],
        [['lead_generation', 25], ['persuasion', 30], ['closing', 30], ['external_marketer', 15]],
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

        $this->seedSalaryDistributions($counts['salary_distributions']);
        $this->seedAccountingNotifications($confirmedReservations, $accountingUsers ?: $admins);
    }

    private function seedDeposits($confirmedReservations, array $accountingPool, int $targetDeposits): void
    {
        $statuses = ['pending', 'received', 'confirmed', 'refunded'];
        $created = 0;

        foreach ($confirmedReservations as $index => $reservation) {
            if ($created >= $targetDeposits) {
                break;
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
            'notes' => fake()->optional()->sentence(),
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

        foreach ($ids as $reservationId) {
            $reservation = $confirmedReservations->firstWhere('id', $reservationId);
            if (!$reservation) {
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
                    'status' => Arr::random(['pending', 'approved', 'paid']),
                    'approved_at' => now()->subDays(fake()->numberBetween(0, 10)),
                    'paid_at' => null,
                ]
            );
            $commissionIds[] = $commission->id;
        }

        return $commissionIds;
    }

    private function seedCommissionDistributions(array $commissionIds, array $accountingPool): void
    {
        $salesUsers = User::where('type', 'sales')->pluck('id')->all();
        $salesUsers = $salesUsers ?: User::where('type', 'marketing')->pluck('id')->all();
        $statuses = ['pending', 'approved', 'rejected', 'paid'];

        foreach ($commissionIds as $idx => $commissionId) {
            $commission = Commission::find($commissionId);
            if (!$commission || $commission->net_amount <= 0) {
                continue;
            }

            $template = self::DISTRIBUTION_TEMPLATES[$idx % count(self::DISTRIBUTION_TEMPLATES)];
            $status = $statuses[$idx % 4];

            foreach ($template as $item) {
                [$type, $percentage] = $item;
                $amount = ($commission->net_amount * $percentage) / 100;

                $data = [
                    'commission_id' => $commissionId,
                    'type' => $type,
                    'percentage' => $percentage,
                    'amount' => $amount,
                    'status' => $status,
                    'notes' => fake()->optional()->sentence(),
                ];

                if (in_array($type, ['external_marketer', 'other'])) {
                    $data['external_name'] = fake()->name();
                    $data['bank_account'] = 'SA' . fake()->numerify('######################');
                    $data['user_id'] = null;
                } else {
                    $data['user_id'] = $salesUsers ? Arr::random($salesUsers) : null;
                }

                if (in_array($status, ['approved', 'rejected', 'paid'])) {
                    $data['approved_by'] = Arr::random($accountingPool);
                    $data['approved_at'] = now()->subDays(fake()->numberBetween(1, 10));
                }
                if ($status === 'paid') {
                    $data['paid_at'] = now()->subDays(fake()->numberBetween(1, 5));
                }

                CommissionDistribution::create($data);
            }
        }
    }

    private function seedSalaryDistributions(int $targetCount): void
    {
        $employees = User::where('type', '!=', 'admin')->pluck('id')->all();
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $prev = now()->subMonth();

        $pairs = [];
        foreach ($employees as $userId) {
            $pairs[] = ['user_id' => $userId, 'month' => $currentMonth, 'year' => $currentYear];
            $pairs[] = ['user_id' => $userId, 'month' => $prev->month, 'year' => $prev->year];
        }
        shuffle($pairs);

        $take = min($targetCount, count($pairs));
        for ($i = 0; $i < $take; $i++) {
            $p = $pairs[$i];
            $baseSalary = fake()->randomFloat(2, 5000, 20000);
            $totalCommissions = fake()->randomFloat(2, 0, 10000);
            AccountingSalaryDistribution::firstOrCreate(
                ['user_id' => $p['user_id'], 'month' => $p['month'], 'year' => $p['year']],
                [
                    'user_id' => $p['user_id'],
                    'month' => $p['month'],
                    'year' => $p['year'],
                    'base_salary' => $baseSalary,
                    'total_commissions' => $totalCommissions,
                    'total_amount' => $baseSalary + $totalCommissions,
                    'status' => fake()->randomElement(['pending', 'approved', 'paid']),
                    'paid_at' => fake()->optional(0.3)->dateTimeThisYear(),
                ]
            );
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
