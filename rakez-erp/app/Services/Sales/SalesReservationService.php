<?php

namespace App\Services\Sales;

use App\Models\SalesReservation;
use App\Models\SalesReservationAction;
use App\Models\NegotiationApproval;
use App\Models\ContractUnit;
use App\Models\Contract;
use App\Models\User;
use App\Models\UserNotification;
use App\Events\UserNotificationEvent;
use App\Exceptions\UnitAlreadyReservedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

class SalesReservationService
{
    protected ReservationVoucherService $voucherService;

    public function __construct(
        ReservationVoucherService $voucherService,
        protected SalesDashboardService $salesDashboardService,
        protected DepositService $depositService,
    ) {
        $this->voucherService = $voucherService;
    }

    /**
     * Map contract commission_from value to deposit commission_source enum value.
     * Handles both Arabic and English values.
     */
    protected function mapCommissionSource(?string $contractCommissionFrom): string
    {
        if (!$contractCommissionFrom) {
            return 'owner'; // default
        }

        $value = strtolower(trim($contractCommissionFrom));
        
        // Map Arabic values
        if (str_contains($value, 'مالك') || $value === 'مالك') {
            return 'owner';
        }
        if (str_contains($value, 'مشتري') || $value === 'مشتري') {
            return 'buyer';
        }
        if (str_contains($value, 'طرفين') || $value === 'الطرفين') {
            return 'owner'; // Default to owner if both parties
        }
        
        // Map English values
        if ($value === 'owner') {
            return 'owner';
        }
        if ($value === 'buyer') {
            return 'buyer';
        }
        
        // Fallback to owner
        return 'owner';
    }

    /**
     * Create a new reservation with DB locking to prevent double booking.
     */
    public function createReservation(array $data, User $user): SalesReservation
    {
        DB::beginTransaction();
        
        try {
            // Lock the unit row to prevent concurrent reservations
            $unit = ContractUnit::where('id', $data['contract_unit_id'])
                ->lockForUpdate()
                ->first();

            if (!$unit) {
                throw new Exception('Unit not found');
            }

            // Check if there's an active reservation for this unit
            $activeReservation = SalesReservation::where('contract_unit_id', $unit->id)
                ->whereIn('status', ['under_negotiation', 'confirmed'])
                ->exists();

            if ($activeReservation) {
                throw new UnitAlreadyReservedException('Unit already reserved');
            }

            // Load contract and related data for snapshot
            $contract = Contract::with(['info', 'secondPartyData', 'city', 'district'])->findOrFail($data['contract_id']);

            // Determine initial status based on reservation type
            $status = $data['reservation_type'] === 'negotiation' 
                ? 'under_negotiation' 
                : 'confirmed';

            // Create snapshot of data for voucher
            $snapshot = [
                'project' => [
                    'name' => $contract->project_name,
                    'city' => $contract->city?->name,
                    'district' => $contract->district?->name,
                    'developer_name' => $contract->developer_name,
                    'developer_number' => $contract->developer_number,
                ],
                'unit' => [
                    'number' => $unit->unit_number,
                    'type' => $unit->unit_type,
                    'area' => $unit->area,
                    'floor' => $unit->floor,
                    'price' => $unit->price,
                ],
                'employee' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'team' => $user->team?->name ?? '',
                ],
                'client' => [
                    'name' => $data['client_name'],
                    'mobile' => $data['client_mobile'],
                    'nationality' => $data['client_nationality'],
                    'iban' => $data['client_iban'],
                ],
                'payment' => [
                    'method' => $data['payment_method'],
                    'amount' => $data['down_payment_amount'],
                    'status' => $data['down_payment_status'],
                    'mechanism' => $data['purchase_mechanism'],
                ]
            ];

            // Set approval deadline for negotiation reservations (48 hours)
            $approvalDeadline = $data['reservation_type'] === 'negotiation' 
                ? now()->addHours(48) 
                : null;

            // Create reservation
            $reservation = SalesReservation::create([
                'contract_id' => $data['contract_id'],
                'contract_unit_id' => $data['contract_unit_id'],
                'marketing_employee_id' => $user->id,
                'status' => $status,
                'reservation_type' => $data['reservation_type'],
                'contract_date' => $data['contract_date'],
                'negotiation_notes' => $data['negotiation_notes'] ?? null,
                'negotiation_reason' => $data['negotiation_reason'] ?? null,
                'proposed_price' => $data['proposed_price'] ?? null,
                'evacuation_date' => $data['evacuation_date'] ?? null,
                'approval_deadline' => $approvalDeadline,
                'client_name' => $data['client_name'],
                'client_mobile' => $data['client_mobile'],
                'client_nationality' => $data['client_nationality'],
                'client_iban' => $data['client_iban'],
                'payment_method' => $data['payment_method'],
                'down_payment_amount' => $data['down_payment_amount'],
                'down_payment_status' => $data['down_payment_status'],
                'purchase_mechanism' => $data['purchase_mechanism'],
                'receipt_voucher_path' => $data['receipt_voucher_path'] ?? null,
                'snapshot' => $snapshot,
                'confirmed_at' => $status === 'confirmed' ? now() : null,
            ]);

            // Create negotiation approval record for negotiation reservations
            if ($data['reservation_type'] === 'negotiation') {
                NegotiationApproval::create([
                    'sales_reservation_id' => $reservation->id,
                    'requested_by' => $user->id,
                    'status' => 'pending',
                    'negotiation_reason' => $data['negotiation_reason'] ?? 'السعر',
                    'original_price' => $unit->price,
                    'proposed_price' => $data['proposed_price'] ?? $unit->price,
                    'deadline_at' => $approvalDeadline,
                ]);
            }

            // Update unit status to reserved
            $unit->update(['status' => 'reserved']);

            // Create deposit if reservation is confirmed (immediate deposit flow)
            if ($status === 'confirmed' && $data['down_payment_amount'] > 0) {
                $commissionSource = $this->mapCommissionSource($contract->commission_from);
                $this->depositService->createDeposit(
                    $reservation->id,
                    $data['contract_id'],
                    $data['contract_unit_id'],
                    (float) $data['down_payment_amount'],
                    $data['payment_method'],
                    $data['client_name'],
                    now()->format('Y-m-d'),
                    $commissionSource,
                    'تم إنشاء العربون من حجز المبيعات'
                );
            }

            // Generate PDF voucher (optional: if Mpdf is not installed, reservation is still created)
            try {
                $voucherPath = $this->voucherService->generate($reservation);
                $reservation->update(['voucher_pdf_path' => $voucherPath]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Reservation voucher PDF skipped: ' . $e->getMessage());
            }

            DB::commit();

            // Send notifications to departments (after commit)
            $this->notifyDepartments($reservation, $contract, $unit);

            // Notify sales managers for negotiation approvals
            if ($data['reservation_type'] === 'negotiation') {
                $this->notifySalesManagers($reservation, $unit);
            }

            return $reservation->fresh(['contract', 'contractUnit', 'marketingEmployee', 'negotiationApproval']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Confirm a reservation.
     */
    public function confirmReservation(int $id, User $user): SalesReservation
    {
        $reservation = SalesReservation::findOrFail($id);

        // Check ownership first - regular sales employees can only confirm their own reservations
        if ($reservation->marketing_employee_id !== $user->id) {
            // Only admins and project_management can confirm others' reservations
            if (!$user->hasRole('admin') && !$user->hasRole('project_management')) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to confirm this reservation');
            }
        }

        if (!$reservation->canConfirm()) {
            throw new Exception('Reservation cannot be confirmed in current status');
        }

        $reservation->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        // Optionally regenerate voucher
        $voucherPath = $this->voucherService->generate($reservation);
        $reservation->update(['voucher_pdf_path' => $voucherPath]);

        return $reservation->fresh();
    }

    /**
     * Cancel a reservation.
     */
    public function cancelReservation(int $id, ?string $reason, User $user): SalesReservation
    {
        $reservation = SalesReservation::findOrFail($id);

        // Check ownership: sales can cancel own; admin, credit and project_management can cancel any
        if ($reservation->marketing_employee_id !== $user->id) {
            if (!$user->hasRole('admin') && !$user->hasRole('credit') && !$user->hasRole('project_management')) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to cancel this reservation');
            }
        }

        if (!$reservation->canCancel()) {
            throw new Exception('Reservation cannot be cancelled in current status');
        }

        DB::beginTransaction();
        try {
            $reservation->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Check if there are other active reservations for this unit
            $otherActiveReservations = SalesReservation::where('contract_unit_id', $reservation->contract_unit_id)
                ->where('id', '!=', $reservation->id)
                ->whereIn('status', ['under_negotiation', 'confirmed'])
                ->exists();

            // If no other active reservations, mark unit as available
            if (!$otherActiveReservations) {
                $reservation->contractUnit->update(['status' => 'available']);
            }

            DB::commit();
            return $reservation->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get reservations with filters.
     */
    public function getReservations(array $filters, User $user): LengthAwarePaginator
    {
        $query = SalesReservation::with(['contract', 'contractUnit', 'marketingEmployee']);

        // Visibility: sales reps see own rows; sales leaders see team + led-project rows (see SalesDashboardService).
        // `mine=1` limits to own reservations for any sales user (including leaders).
        if ($user->type === 'sales' && !$user->hasRole('admin')) {
            if (!empty($filters['mine'])) {
                $query->where('marketing_employee_id', (int) $user->id);
            } else {
                $this->salesDashboardService->applyReservationListVisibility($query, $user);
            }
        } elseif (!empty($filters['mine'])) {
            $query->where('marketing_employee_id', (int) $user->id);
        }

        // Include cancelled or not
        if (empty($filters['include_cancelled']) || $filters['include_cancelled'] === 'false' || $filters['include_cancelled'] === 0) {
            $query->where('status', '!=', 'cancelled');
        }

        // Filter by contract
        if (!empty($filters['contract_id'])) {
            $query->where('contract_id', $filters['contract_id']);
        }

        if (!empty($filters['marketing_id'])) {
            $query->where('marketing_employee_id', (int) $filters['marketing_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Date range filter
        if (!empty($filters['from']) || !empty($filters['to'])) {
            $query->where(function ($q) use ($filters) {
                if (!empty($filters['from'])) {
                    $q->whereDate('created_at', '>=', $filters['from']);
                }
                if (!empty($filters['to'])) {
                    $q->whereDate('created_at', '<=', $filters['to']);
                }
            });
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Log an action for a reservation.
     */
    public function logAction(int $reservationId, string $actionType, ?string $notes, User $user): SalesReservationAction
    {
        $reservation = SalesReservation::findOrFail($reservationId);

        // Check if user owns this reservation or has permission
        if ($reservation->marketing_employee_id !== $user->id && !$user->hasPermissionTo('sales.reservations.view') && !$user->hasRole('admin')) {
            throw new Exception('Unauthorized to log actions for this reservation');
        }

        return SalesReservationAction::create([
            'sales_reservation_id' => $reservationId,
            'user_id' => $user->id,
            'action_type' => $actionType,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    /**
     * Update client contact fields from Credit (confirmed bookings only; not sold).
     *
     * @param  array<string, mixed>  $data  Only client_* keys from validated input.
     */
    public function updateClientDetailsForCredit(int $reservationId, array $data, User $user): SalesReservation
    {
        $reservation = SalesReservation::findOrFail($reservationId);

        if ($reservation->status !== 'confirmed') {
            throw new Exception('يمكن تعديل بيانات العميل من الائتمان للحجوزات المؤكدة فقط');
        }

        if ($reservation->credit_status === 'sold') {
            throw new Exception('لا يمكن تعديل حجز مباع');
        }

        $allowedKeys = ['client_name', 'client_mobile', 'client_nationality', 'client_iban'];
        $payload = array_intersect_key($data, array_flip($allowedKeys));

        $reservation->update($payload);

        return $reservation->fresh();
    }

    /**
     * Log a credit-department client contact action (audit trail).
     */
    public function logCreditClientContact(int $reservationId, ?string $notes, User $user): SalesReservationAction
    {
        SalesReservation::findOrFail($reservationId);

        return SalesReservationAction::create([
            'sales_reservation_id' => $reservationId,
            'user_id' => $user->id,
            'action_type' => 'credit_client_contact',
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    /**
     * Notify departments about new reservation.
     */
    protected function notifyDepartments(SalesReservation $reservation, Contract $contract, ContractUnit $unit): void
    {
        $departments = ['project_management', 'credit', 'accounting'];
        $users = User::whereIn('type', $departments)->get();

        $message = sprintf(
            'New reservation created for project: %s, Unit: %s by %s',
            $contract->project_name,
            $unit->unit_number,
            $reservation->marketingEmployee->name
        );

        foreach ($users as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'message' => $message,
                'event_type' => 'unit_reserved',
                'context' => [
                    'reservation_id' => $reservation->id,
                    'contract_id' => $contract->id,
                    'unit_id' => $unit->id,
                ],
            ]);

            event(new UserNotificationEvent($user->id, $message));
        }
    }

    /**
     * Notify sales managers about new negotiation request.
     */
    protected function notifySalesManagers(SalesReservation $reservation, ContractUnit $unit): void
    {
        // Get users with negotiation approve permission
        $managers = User::permission('sales.negotiation.approve')->get();

        $message = sprintf(
            'طلب موافقة تفاوض جديد: مشروع %s، وحدة %s - السعر المقترح: %s ر.س (مهلة الرد: 48 ساعة)',
            $reservation->contract->project_name ?? 'N/A',
            $unit->unit_number,
            number_format($reservation->proposed_price ?? 0, 2)
        );

        foreach ($managers as $manager) {
            UserNotification::create([
                'user_id' => $manager->id,
                'message' => $message,
                'event_type' => 'negotiation_requested',
                'context' => [
                    'reservation_id' => $reservation->id,
                    'contract_id' => $reservation->contract_id,
                    'unit_id' => $unit->id,
                ],
            ]);

            event(new UserNotificationEvent($manager->id, $message));
        }

        // Notify credit department (حجز تفاوض جديد) for visibility
        $creditUsers = User::where('type', 'credit')->get();
        foreach ($creditUsers as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'message' => $message,
                'event_type' => 'negotiation_requested',
                'context' => [
                    'reservation_id' => $reservation->id,
                    'contract_id' => $reservation->contract_id,
                    'unit_id' => $unit->id,
                ],
            ]);
            event(new UserNotificationEvent($user->id, $message));
        }
    }
}
