<?php

namespace Database\Seeders;

use App\Models\AccountingSalaryDistribution;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\ContractUnit;
use App\Models\Deposit;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $counts = SeedCounts::all();

        $accountingUsers = User::where('type', 'accounting')->pluck('id')->all();
        $admins = User::where('type', 'admin')->pluck('id')->all();
        $accountingPool = $accountingUsers ?: $admins;

        $confirmedReservations = SalesReservation::where('status', 'confirmed')->get();
        $commissionIds = [];

        if ($confirmedReservations->isNotEmpty()) {
            foreach ($confirmedReservations as $reservation) {
                $depositStatus = Arr::random(['received', 'confirmed', 'refunded']);
                Deposit::create([
                    'sales_reservation_id' => $reservation->id,
                    'contract_id' => $reservation->contract_id,
                    'contract_unit_id' => $reservation->contract_unit_id,
                    'amount' => fake()->randomFloat(2, 5000, 80000),
                    'payment_method' => $reservation->payment_method,
                    'client_name' => $reservation->client_name,
                    'payment_date' => now()->subDays(fake()->numberBetween(0, 20)),
                    'commission_source' => $depositStatus === 'refunded' ? 'owner' : Arr::random(['owner', 'buyer']),
                    'status' => $depositStatus,
                    'notes' => fake()->optional()->sentence(),
                    'confirmed_by' => Arr::random($accountingPool),
                    'confirmed_at' => now()->subDays(fake()->numberBetween(0, 5)),
                    'refunded_at' => $depositStatus === 'refunded' ? now()->subDays(1) : null,
                ]);
            }

            $additionalDeposits = max(0, $counts['deposits'] - $confirmedReservations->count());
            for ($i = 0; $i < $additionalDeposits; $i++) {
                $reservation = $confirmedReservations->random();
                $status = Arr::random(['received', 'confirmed', 'refunded']);
                Deposit::create([
                    'sales_reservation_id' => $reservation->id,
                    'contract_id' => $reservation->contract_id,
                    'contract_unit_id' => $reservation->contract_unit_id,
                    'amount' => fake()->randomFloat(2, 3000, 70000),
                    'payment_method' => $reservation->payment_method,
                    'client_name' => $reservation->client_name,
                    'payment_date' => now()->subDays(fake()->numberBetween(0, 20)),
                    'commission_source' => $status === 'refunded' ? 'owner' : Arr::random(['owner', 'buyer']),
                    'status' => $status,
                    'notes' => fake()->optional()->sentence(),
                    'confirmed_by' => Arr::random($accountingPool),
                    'confirmed_at' => now()->subDays(fake()->numberBetween(0, 5)),
                    'refunded_at' => $status === 'refunded' ? now()->subDays(1) : null,
                ]);
            }

            $reservationIds = $confirmedReservations->pluck('id')->all();
            shuffle($reservationIds);
            $reservationIds = array_slice($reservationIds, 0, min($counts['commissions'], count($reservationIds)));

            foreach ($reservationIds as $reservationId) {
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

            $salesUsers = User::where('type', 'sales')->pluck('id')->all();
            if (!$salesUsers) {
                $salesUsers = User::where('type', 'marketing')->pluck('id')->all();
            }

            foreach ($commissionIds as $commissionId) {
                $commission = Commission::find($commissionId);
                $commissionNet = $commission?->net_amount ?? 0;
                $distributionCount = fake()->numberBetween(2, 3);
                for ($i = 0; $i < $distributionCount; $i++) {
                    $status = fake()->boolean(40) ? 'approved' : 'pending';
                    $percentage = fake()->randomFloat(2, 10, 40);
                    $amount = $commissionNet > 0 ? ($commissionNet * $percentage) / 100 : 0;

                    $distribution = CommissionDistribution::create([
                        'commission_id' => $commissionId,
                        'user_id' => Arr::random($salesUsers),
                        'type' => Arr::random(['lead_generation', 'persuasion', 'closing']),
                        'percentage' => $percentage,
                        'amount' => $amount,
                        'status' => $status,
                        'notes' => fake()->optional()->sentence(),
                        'approved_by' => $status === 'approved' ? Arr::random($accountingPool) : null,
                        'approved_at' => $status === 'approved' ? now()->subDays(1) : null,
                    ]);
                    $distribution->calculateAmount();
                    $distribution->save();
                }
            }
        }

        $employees = User::where('type', '!=', 'admin')->pluck('id')->all();
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $previousMonth = now()->subMonth();

        $salaryPairs = [];
        foreach ($employees as $userId) {
            $salaryPairs[] = ['user_id' => $userId, 'month' => $currentMonth, 'year' => $currentYear];
            $salaryPairs[] = ['user_id' => $userId, 'month' => $previousMonth->month, 'year' => $previousMonth->year];
        }
        shuffle($salaryPairs);

        $targetCount = min($counts['salary_distributions'], count($salaryPairs));
        for ($i = 0; $i < $targetCount; $i++) {
            AccountingSalaryDistribution::factory()->create($salaryPairs[$i]);
        }
    }
}
