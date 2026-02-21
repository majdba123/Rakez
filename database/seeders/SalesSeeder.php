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
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $counts = SeedCounts::all();

        $readyContracts = Contract::where('status', 'ready')->pluck('id')->all();
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
        $salesLeaders = $salesLeaders ?: $salesUsers;

        foreach ($readyContracts as $contractId) {
            $leaderId = Arr::random($salesLeaders);

            // Create assignments with date ranges (some active, some past, some future)
            $dateType = fake()->numberBetween(0, 2);
            $startDate = null;
            $endDate = null;

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

            SalesProjectAssignment::firstOrCreate(
                [
                    'leader_id' => $leaderId,
                    'contract_id' => $contractId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                ['assigned_by' => Arr::random($salesLeaders)]
            );
        }

        $contractToUnits = $this->buildContractUnitsMap($readyContracts);

        // Guarantee every sales user (including those without team_id, e.g. login user) has at least 2 targets
        $allSalesUserIds = array_unique(array_merge(
            $salesUsers,
            User::where('type', 'sales')->pluck('id')->all()
        ));
        $targetsPerUser = 2;
        foreach ($allSalesUserIds as $marketerId) {
            for ($t = 0; $t < $targetsPerUser; $t++) {
                $contractId = Arr::random($readyContracts);
                if (empty($contractToUnits[$contractId])) {
                    continue;
                }
                $unitId = Arr::random($contractToUnits[$contractId]);
                SalesTarget::create([
                    'leader_id' => Arr::random($salesLeaders),
                    'marketer_id' => $marketerId,
                    'contract_id' => $contractId,
                    'contract_unit_id' => $unitId,
                    'target_type' => 'reservation',
                    'start_date' => now()->subDays(fake()->numberBetween(0, 30))->format('Y-m-d'),
                    'end_date' => now()->addDays(fake()->numberBetween(30, 90))->format('Y-m-d'),
                    'status' => $t === 0 ? 'in_progress' : Arr::random(['new', 'in_progress', 'completed']),
                    'leader_notes' => fake()->optional()->sentence(),
                ]);
            }
        }

        $existingCount = SalesTarget::count();
        for ($i = $existingCount; $i < $counts['sales_targets']; $i++) {
            $contractId = Arr::random($readyContracts);
            if (empty($contractToUnits[$contractId])) {
                continue;
            }
            $unitId = Arr::random($contractToUnits[$contractId]);
            SalesTarget::factory()->create([
                'leader_id' => Arr::random($salesLeaders),
                'marketer_id' => Arr::random($salesUsers),
                'contract_id' => $contractId,
                'contract_unit_id' => $unitId,
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

        // Guarantee enough "waiting" so Credit Bookings "حجوزات الانتظار" tab has data
        $waitingCount = min(35, $counts['waiting_list_entries']);
        $otherWaitingStatuses = ['converted', 'cancelled', 'expired'];
        for ($i = 0; $i < $counts['waiting_list_entries']; $i++) {
            $contractId = Arr::random($readyContracts);
            if (empty($contractToUnits[$contractId])) {
                continue;
            }
            $unitId = Arr::random($contractToUnits[$contractId]);
            $waitingListStatus = $i < $waitingCount ? 'waiting' : $otherWaitingStatuses[$i % 3];

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

        // Guarantee enough of each status so all Credit Bookings tabs have data to test
        $underNegotiationCount = 60;  // Negotiation tab
        $confirmedCount = 160;        // Confirmed + Sold (CreditSeeder sets credit_status)
        $cancelledCount = 50;        // Rejected/Cancelled tab (cancelled_at set)
        $reservationStatusMap = array_merge(
            array_fill(0, $underNegotiationCount, 'under_negotiation'),
            array_fill(0, $confirmedCount, 'confirmed'),
            array_fill(0, $cancelledCount, 'cancelled')
        );
        $totalSlots = count($reservationStatusMap);
        if ($totalSlots < $counts['sales_reservations']) {
            $extra = $counts['sales_reservations'] - $totalSlots;
            $reservationStatusMap = array_merge(
                $reservationStatusMap,
                array_fill(0, $extra, Arr::random(['under_negotiation', 'confirmed', 'cancelled']))
            );
        }

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
        $secondParties = SecondPartyData::whereIn('contract_id', $contractIds)->get(['id', 'contract_id']);
        $secondPartyIds = $secondParties->pluck('id')->all();
        $units = ContractUnit::whereIn('second_party_data_id', $secondPartyIds)->get(['id', 'second_party_data_id']);

        $contractBySecondParty = [];
        foreach ($secondParties as $secondParty) {
            $contractBySecondParty[$secondParty->id] = $secondParty->contract_id;
        }

        foreach ($units as $unit) {
            $contractId = $contractBySecondParty[$unit->second_party_data_id] ?? null;
            if (!$contractId) {
                continue;
            }
            $map[$contractId][] = $unit->id;
        }

        foreach ($contractIds as $contractId) {
            if (!isset($map[$contractId])) {
                $map[$contractId] = [];
            }
        }

        return $map;
    }
}
