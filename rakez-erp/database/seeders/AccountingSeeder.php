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
            foreach ($confirmedReservations as $index => $reservation) {
                $depositStatuses = ['pending', 'received', 'confirmed', 'refunded'];
                $depositStatus = $depositStatuses[$index % 4];
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
                    'confirmed_by' => in_array($depositStatus, ['confirmed', 'refunded']) ? Arr::random($accountingPool) : null,
                    'confirmed_at' => in_array($depositStatus, ['confirmed', 'refunded']) ? now()->subDays(fake()->numberBetween(0, 5)) : null,
                    'refunded_at' => $depositStatus === 'refunded' ? now()->subDays(1) : null,
                ]);
            }

            $additionalDeposits = max(0, $counts['deposits'] - $confirmedReservations->count());
            for ($i = 0; $i < $additionalDeposits; $i++) {
                $reservation = $confirmedReservations->random();
                $depositStatuses = ['pending', 'received', 'confirmed', 'refunded'];
                $status = $depositStatuses[$i % 4];
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
                    'confirmed_by' => in_array($status, ['confirmed', 'refunded']) ? Arr::random($accountingPool) : null,
                    'confirmed_at' => in_array($status, ['confirmed', 'refunded']) ? now()->subDays(fake()->numberBetween(0, 5)) : null,
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

            $distributionTypes = ['lead_generation', 'persuasion', 'closing', 'team_leader', 'sales_manager', 'project_manager', 'external_marketer', 'other'];
            $distributionStatuses = ['pending', 'approved', 'rejected', 'paid'];
            
            foreach ($commissionIds as $commissionIndex => $commissionId) {
                $commission = Commission::find($commissionId);
                $commissionNet = $commission?->net_amount ?? 0;
                $distributionCount = fake()->numberBetween(2, 4);
                for ($i = 0; $i < $distributionCount; $i++) {
                    $status = $distributionStatuses[($commissionIndex * $distributionCount + $i) % 4];
                    $percentage = fake()->randomFloat(2, 10, 40);
                    $amount = $commissionNet > 0 ? ($commissionNet * $percentage) / 100 : 0;
                    $type = $distributionTypes[($commissionIndex * $distributionCount + $i) % 8];
                    
                    $distributionData = [
                        'commission_id' => $commissionId,
                        'type' => $type,
                        'percentage' => $percentage,
                        'amount' => $amount,
                        'status' => $status,
                        'notes' => fake()->optional()->sentence(),
                    ];
                    
                    // For external_marketer and other types, add external_name
                    if (in_array($type, ['external_marketer', 'other'])) {
                        $distributionData['external_name'] = fake()->name();
                        $distributionData['user_id'] = null;
                    } else {
                        $distributionData['user_id'] = Arr::random($salesUsers);
                    }
                    
                    // Add bank_account for external types
                    if (in_array($type, ['external_marketer', 'other'])) {
                        $distributionData['bank_account'] = 'SA' . fake()->numerify('######################');
                    }
                    
                    // Set approval fields based on status
                    if (in_array($status, ['approved', 'rejected', 'paid'])) {
                        $distributionData['approved_by'] = Arr::random($accountingPool);
                        $distributionData['approved_at'] = now()->subDays(fake()->numberBetween(1, 10));
                    }
                    
                    // Set paid_at for paid status
                    if ($status === 'paid') {
                        $distributionData['paid_at'] = now()->subDays(fake()->numberBetween(1, 5));
                    }

                    $distribution = CommissionDistribution::create($distributionData);
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
