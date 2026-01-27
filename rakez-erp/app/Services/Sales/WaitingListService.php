<?php

namespace App\Services\Sales;

use App\Models\SalesWaitingList;
use App\Models\SalesReservation;
use App\Models\ContractUnit;
use App\Models\User;
use App\Models\UserNotification;
use App\Events\UserNotificationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Exception;

class WaitingListService
{
    protected SalesReservationService $reservationService;

    public function __construct(SalesReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    /**
     * Create a new waiting list entry.
     */
    public function createWaitingListEntry(array $data, User $user): SalesWaitingList
    {
        DB::beginTransaction();

        try {
            // Verify the unit exists
            $unit = ContractUnit::with('secondPartyData.contract')->findOrFail($data['contract_unit_id']);

            // Set expiry date (default 30 days from now, configurable)
            $expiryDays = config('sales.waiting_list_expiry_days', 30);
            $expiresAt = now()->addDays($expiryDays);

            // Create waiting list entry
            $waitingEntry = SalesWaitingList::create([
                'contract_id' => $data['contract_id'],
                'contract_unit_id' => $data['contract_unit_id'],
                'sales_staff_id' => $user->id,
                'client_name' => $data['client_name'],
                'client_mobile' => $data['client_mobile'],
                'client_email' => $data['client_email'] ?? null,
                'priority' => $data['priority'] ?? 1,
                'status' => 'waiting',
                'notes' => $data['notes'] ?? null,
                'expires_at' => $expiresAt,
            ]);

            DB::commit();

            // Send notification to sales staff
            $this->notifySalesStaff($waitingEntry);

            return $waitingEntry->fresh(['contract', 'contractUnit', 'salesStaff']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get waiting list entries for a specific unit.
     */
    public function getWaitingListForUnit(int $unitId): array
    {
        return SalesWaitingList::byUnit($unitId)
            ->active()
            ->orderByPriority()
            ->with(['salesStaff', 'contract', 'contractUnit'])
            ->get()
            ->toArray();
    }

    /**
     * Get all waiting list entries with filters and pagination.
     */
    public function getWaitingList(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SalesWaitingList::with(['contract', 'contractUnit', 'salesStaff', 'convertedToReservation', 'convertedBy']);

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by sales staff
        if (isset($filters['sales_staff_id'])) {
            $query->byStaff($filters['sales_staff_id']);
        }

        // Filter by contract
        if (isset($filters['contract_id'])) {
            $query->where('contract_id', $filters['contract_id']);
        }

        // Filter by unit
        if (isset($filters['contract_unit_id'])) {
            $query->byUnit($filters['contract_unit_id']);
        }

        // Filter active only
        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->active();
        }

        // Default ordering
        $query->orderByPriority();

        return $query->paginate($perPage);
    }

    /**
     * Convert waiting list entry to confirmed reservation (Leader only).
     */
    public function convertToReservation(int $waitingListId, array $reservationData, User $leader): SalesReservation
    {
        DB::beginTransaction();

        try {
            // Get waiting list entry
            $waitingEntry = SalesWaitingList::with(['contract', 'contractUnit'])->findOrFail($waitingListId);

            // Verify entry is still active
            if (!$waitingEntry->isActive()) {
                throw new Exception('Waiting list entry is not active');
            }

            // Verify unit is available
            $unit = $waitingEntry->contractUnit;
            $activeReservation = SalesReservation::where('contract_unit_id', $unit->id)
                ->whereIn('status', ['under_negotiation', 'confirmed'])
                ->exists();

            if ($activeReservation) {
                throw new Exception('Unit is already reserved');
            }

            // Merge waiting list data with reservation data
            $fullReservationData = array_merge([
                'contract_id' => $waitingEntry->contract_id,
                'contract_unit_id' => $waitingEntry->contract_unit_id,
                'client_name' => $waitingEntry->client_name,
                'client_mobile' => $waitingEntry->client_mobile,
            ], $reservationData);

            // Create reservation using the original sales staff
            $reservation = $this->reservationService->createReservation(
                $fullReservationData,
                $waitingEntry->salesStaff
            );

            // Mark waiting list entry as converted
            $waitingEntry->markAsConverted($reservation, $leader);

            DB::commit();

            // Notify sales staff about conversion
            $this->notifyConversion($waitingEntry, $reservation);

            return $reservation;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancel a waiting list entry.
     */
    public function cancelWaitingEntry(int $waitingListId, User $user): SalesWaitingList
    {
        $waitingEntry = SalesWaitingList::findOrFail($waitingListId);

        // Verify user has permission (owner or leader)
        if ($waitingEntry->sales_staff_id !== $user->id && !$user->isSalesLeader()) {
            throw new Exception('Unauthorized to cancel this waiting list entry');
        }

        $waitingEntry->markAsCancelled();

        return $waitingEntry->fresh();
    }

    /**
     * Notify waiting clients when a unit becomes available.
     */
    public function notifyWaitingClients(int $unitId): void
    {
        $waitingEntries = SalesWaitingList::byUnit($unitId)
            ->active()
            ->orderByPriority()
            ->with('salesStaff')
            ->get();

        foreach ($waitingEntries as $entry) {
            UserNotification::create([
                'user_id' => $entry->sales_staff_id,
                'type' => 'waiting_list_unit_available',
                'title' => 'Unit Available',
                'message' => "Unit {$entry->contractUnit->unit_number} is now available for client {$entry->client_name}",
                'data' => [
                    'waiting_list_id' => $entry->id,
                    'contract_unit_id' => $entry->contract_unit_id,
                    'client_name' => $entry->client_name,
                ],
                'status' => 'pending',
            ]);

            event(new UserNotificationEvent(
                $entry->sales_staff_id,
                'Unit Available',
                "Unit {$entry->contractUnit->unit_number} is now available"
            ));
        }
    }

    /**
     * Mark expired waiting list entries.
     */
    public function markExpiredEntries(): int
    {
        $expiredEntries = SalesWaitingList::expired()->get();

        foreach ($expiredEntries as $entry) {
            $entry->markAsExpired();
        }

        return $expiredEntries->count();
    }

    /**
     * Send notification to sales staff about new waiting list entry.
     */
    protected function notifySalesStaff(SalesWaitingList $waitingEntry): void
    {
        UserNotification::create([
            'user_id' => $waitingEntry->sales_staff_id,
            'type' => 'waiting_list_created',
            'title' => 'Waiting List Entry Created',
            'message' => "Waiting list entry created for client {$waitingEntry->client_name}",
            'data' => [
                'waiting_list_id' => $waitingEntry->id,
                'contract_unit_id' => $waitingEntry->contract_unit_id,
                'client_name' => $waitingEntry->client_name,
            ],
            'status' => 'pending',
        ]);

        event(new UserNotificationEvent(
            $waitingEntry->sales_staff_id,
            'Waiting List Entry Created',
            "Entry created for {$waitingEntry->client_name}"
        ));
    }

    /**
     * Send notification about waiting list conversion.
     */
    protected function notifyConversion(SalesWaitingList $waitingEntry, SalesReservation $reservation): void
    {
        UserNotification::create([
            'user_id' => $waitingEntry->sales_staff_id,
            'type' => 'waiting_list_converted',
            'title' => 'Waiting List Converted',
            'message' => "Waiting list entry for {$waitingEntry->client_name} has been converted to reservation",
            'data' => [
                'waiting_list_id' => $waitingEntry->id,
                'reservation_id' => $reservation->id,
                'client_name' => $waitingEntry->client_name,
            ],
            'status' => 'pending',
        ]);

        event(new UserNotificationEvent(
            $waitingEntry->sales_staff_id,
            'Waiting List Converted',
            "Entry for {$waitingEntry->client_name} converted to reservation"
        ));
    }
}
