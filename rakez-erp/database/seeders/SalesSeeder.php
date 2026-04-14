<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesAttendanceSchedule;
use App\Models\SalesProjectAssignment;
use App\Models\SalesReservation;
use App\Models\SalesReservationAction;
use App\Models\SalesTarget;
use App\Models\SalesWaitingList;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $counts = SeedCounts::all();

        $readyContracts = Contract::where('status', 'completed')->pluck('id')->all();
        // Use only sales users with team_id set so marketing details (فريق المشروع، فريق البائع) are populated
        $salesUsers = User::where('type', 'sales')->whereNotNull('team_id')->pluck('id')->all();
        if (empty($salesUsers)) {
            $salesUsers = User::where('type', 'marketing')->whereNotNull('team_id')->pluck('id')->all();
        }
        if (empty($salesUsers)) {
            $salesUsers = User::whereIn('type', ['sales', 'marketing'])->pluck('id')->all();
        }

        $accountingUsers = User::where('type', 'accounting')->pluck('id')->all();
        $accountingPool = $accountingUsers ?: User::where('type', 'admin')->pluck('id')->all();

        $salesLeaders = User::where('type', 'sales')->where('is_manager', true)->pluck('id')->all();
        $salesLeadersFromType = User::where('type', 'sales_leader')->pluck('id')->all();
        $salesLeaders = array_unique(array_merge($salesLeaders, $salesLeadersFromType));
        $salesLeaders = $salesLeaders ?: $salesUsers;

        // Guarantee at least one active assignment for each named sales leader
        $namedLeaders = User::whereIn('email', ['ahmed@rakez.com', 'sales.leader@rakez.com'])->pluck('id')->all();
        $assignedContracts = [];
        foreach ($namedLeaders as $idx => $namedLeaderId) {
            $contractId = $readyContracts[$idx] ?? ($readyContracts[0] ?? null);
            if ($contractId && !in_array($contractId, $assignedContracts)) {
                SalesProjectAssignment::updateOrCreate(
                    ['leader_id' => $namedLeaderId, 'contract_id' => $contractId],
                    [
                        'assigned_by' => $namedLeaderId,
                        'start_date' => now()->subDays(30)->toDateString(),
                        'end_date' => now()->addDays(180)->toDateString(),
                    ]
                );
                $assignedContracts[] = $contractId;
            }
        }

        foreach ($readyContracts as $contractId) {
            $leaderId = Arr::random($salesLeaders);

            // Create or update a single assignment per (leader, contract) pair
            $dateType = fake()->numberBetween(0, 2);

            if ($dateType === 0) {
                // Active assignment (started in past, ends in future)
                $startDate = now()->subDays(fake()->numberBetween(10, 60))->toDateString();
                $endDate = now()->addDays(fake()->numberBetween(30, 180))->toDateString();
            } elseif ($dateType === 1) {
                // Past assignment (ended in past)
                $startDate = now()->subDays(fake()->numberBetween(90, 180))->toDateString();
                $endDate = now()->subDays(fake()->numberBetween(10, 30))->toDateString();
            } else {
                // Future assignment (starts in future)
                $startDate = now()->addDays(fake()->numberBetween(10, 60))->toDateString();
                $endDate = now()->addDays(fake()->numberBetween(90, 240))->toDateString();
            }

            SalesProjectAssignment::updateOrCreate(
                [
                    'leader_id' => $leaderId,
                    'contract_id' => $contractId,
                ],
                [
                    'assigned_by' => Arr::random($salesLeaders),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            );
        }

        $contractToUnits = $this->buildContractUnitsMap($readyContracts);

        for ($i = 0; $i < $counts['sales_targets']; $i++) {
            $contractId = Arr::random($readyContracts);
            if (empty($contractToUnits[$contractId])) {
                continue;
            }
            $unitIds = $contractToUnits[$contractId];
            $isMultiUnit = ($i % 5 === 4) && count($unitIds) >= 2;
            if ($isMultiUnit) {
                $selectedUnitIds = array_slice(array_values($unitIds), 0, min(3, count($unitIds)));
                $firstUnitId = $selectedUnitIds[0];
            } else {
                $firstUnitId = Arr::random($unitIds);
                $selectedUnitIds = [$firstUnitId];
            }
            $sumPrices = (float) ContractUnit::query()->whereIn('id', $selectedUnitIds)->sum('price');
            SalesTarget::factory()->create([
                'leader_id' => Arr::random($salesLeaders),
                'marketer_id' => Arr::random($salesUsers),
                'contract_id' => $contractId,
                'contract_unit_id' => null,
                'must_sell_units_count' => count($selectedUnitIds),
                'assigned_target_value' => $sumPrices > 0 ? $sumPrices : count($selectedUnitIds) * 100000,
                'status' => $i % 3 === 0 ? 'completed' : ($i % 2 === 0 ? 'in_progress' : 'new'),
            ]);
        }

        for ($i = 0; $i < $counts['attendance_schedules']; $i++) {
            $contractId = Arr::random($readyContracts);
            SalesAttendanceSchedule::factory()->create([
                'contract_id' => $contractId,
                'user_id' => Arr::random($salesUsers),
                'created_by' => Arr::random($salesLeaders),
            ]);
        }

        for ($i = 0; $i < $counts['waiting_list_entries']; $i++) {
            $contractId = Arr::random($readyContracts);
            if (empty($contractToUnits[$contractId])) {
                continue;
            }
            $unitId = Arr::random($contractToUnits[$contractId]);
            $waitingListStatuses = ['waiting', 'converted', 'cancelled', 'expired'];
            $waitingListStatus = $waitingListStatuses[$i % 4];

            $factory = SalesWaitingList::factory();
            if ($waitingListStatus === 'converted') {
                $factory = $factory->converted();
            } elseif ($waitingListStatus === 'cancelled') {
                $factory = $factory->cancelled();
            } elseif ($waitingListStatus === 'expired') {
                $factory = $factory->expired();
            }

            $factory->create([
                'contract_id' => $contractId,
                'contract_unit_id' => $unitId,
                'sales_staff_id' => Arr::random($salesUsers),
                'status' => $waitingListStatus,
            ]);
        }

        $reservationStatusMap = array_merge(
            array_fill(0, 100, 'under_negotiation'),
            array_fill(0, 160, 'confirmed'),
            array_fill(0, 40, 'cancelled')
        );
        shuffle($reservationStatusMap);

        for ($i = 0; $i < $counts['sales_reservations']; $i++) {
            $contractId = Arr::random($readyContracts);
            if (empty($contractToUnits[$contractId])) {
                continue;
            }
            $unitId = Arr::random($contractToUnits[$contractId]);
            $status = $reservationStatusMap[$i] ?? 'under_negotiation';

            $paymentMethod = Arr::random(['bank_transfer', 'cash', 'bank_financing']);
            $purchaseMechanism = $paymentMethod === 'cash'
                ? 'cash'
                : Arr::random(['supported_bank', 'unsupported_bank']);

            // client_name is required for credit/bookings list display
            $clientName = Arr::random($this->arabicClientNames());

            $reservation = SalesReservation::create([
                'contract_id' => $contractId,
                'contract_unit_id' => $unitId,
                'marketing_employee_id' => Arr::random($salesUsers),
                'status' => $status,
                'reservation_type' => $status === 'confirmed' ? 'confirmed_reservation' : 'negotiation',
                'contract_date' => now()->subDays(fake()->numberBetween(0, 60))->format('Y-m-d'),
                'negotiation_notes' => $status === 'under_negotiation' ? fake()->sentence() : null,
                'negotiation_reason' => $status === 'under_negotiation' ? 'price' : null,
                'proposed_price' => $status === 'under_negotiation' ? fake()->randomFloat(2, 300000, 900000) : null,
                'evacuation_date' => $status === 'under_negotiation' ? now()->addMonths(2)->format('Y-m-d') : null,
                'approval_deadline' => $status === 'under_negotiation' ? now()->addHours(48) : null,
                'client_name' => $clientName,
                'client_mobile' => '05' . fake()->numerify('########'),
                'client_nationality' => 'Saudi',
                'client_iban' => 'SA' . fake()->numerify('######################'),
                'payment_method' => $paymentMethod,
                'down_payment_amount' => fake()->randomFloat(2, 10000, 200000),
                'down_payment_status' => Arr::random(['refundable', 'non_refundable']),
                'down_payment_confirmed' => $paymentMethod !== 'cash' ? fake()->boolean(40) : true,
                'down_payment_confirmed_by' => $paymentMethod !== 'cash' ? Arr::random($accountingPool) : null,
                'down_payment_confirmed_at' => $paymentMethod !== 'cash' ? now()->subDays(fake()->numberBetween(0, 10)) : null,
                'brokerage_commission_percent' => fake()->randomFloat(2, 1, 5),
                'commission_payer' => Arr::random(['seller', 'buyer']),
                'tax_amount' => fake()->randomFloat(2, 1000, 20000),
                'credit_status' => 'pending',
                'purchase_mechanism' => $purchaseMechanism,
                'voucher_pdf_path' => null,
                'snapshot' => [
                    'project' => ['name' => 'Seeded Project'],
                    'unit' => ['number' => 'U-' . fake()->numerify('###')],
                    'employee' => ['name' => 'Seeded Employee'],
                ],
                'confirmed_at' => $status === 'confirmed' ? now()->subDays(fake()->numberBetween(0, 30)) : null,
                'cancelled_at' => $status === 'cancelled' ? now()->subDays(fake()->numberBetween(0, 30)) : null,
            ]);

            $actionTypes = ['lead_acquisition', 'persuasion', 'closing'];
            foreach ($actionTypes as $actionType) {
                SalesReservationAction::create([
                    'sales_reservation_id' => $reservation->id,
                    'user_id' => Arr::random($salesUsers),
                    'action_type' => $actionType,
                    'notes' => fake()->sentence(),
                    'created_at' => now()->subDays(fake()->numberBetween(0, 30)),
                ]);
            }
        }
    }

    /** Arabic client names for seeded reservations (client_name is required). */
    private function arabicClientNames(): array
    {
        return [
            'عبدالله المنصور',
            'محمد السعيد',
            'خالد العتيبي',
            'فهد الشمري',
            'سعد الدوسري',
            'ناصر القحطاني',
            'راشد الحربي',
            'عمر الزهراني',
            'يوسف الغامدي',
            'أحمد الشهري',
            'عبدالرحمن المطيري',
            'تركي العنزي',
            'بندر البلوي',
            'سلطان السلمي',
            'ماجد الحارثي',
        ];
    }

    private function buildContractUnitsMap(array $contractIds): array
    {
        $map = [];
        $units = ContractUnit::whereIn('contract_id', $contractIds)->get(['id', 'contract_id']);

        foreach ($units as $unit) {
            $map[$unit->contract_id][] = $unit->id;
        }

        foreach ($contractIds as $contractId) {
            if (!isset($map[$contractId])) {
                $map[$contractId] = [];
            }
        }

        return $map;
    }
}
