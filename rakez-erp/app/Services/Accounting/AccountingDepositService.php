<?php

namespace App\Services\Accounting;

use App\Models\Deposit;
use App\Models\SalesReservation;
use App\Models\User;
use App\Models\UserNotification;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingDepositService
{
    /**
     * Resolve a deposit for confirm/refund/PDF actions. Fails with a clear message if the ID is a reservation ID.
     */
    public function findDepositForAccountingAction(int $id): Deposit
    {
        $deposit = Deposit::find($id);
        if ($deposit !== null) {
            return $deposit;
        }

        if (SalesReservation::whereKey($id)->exists()) {
            throw new Exception(
                'المعرف المُرسل يخص حجزاً (sales reservation) وليس عربوناً. ' .
                'استخدم deposit_id من قائمة العربون المعلقة، أو من الحقل deposits[].deposit_id في المتابعة، ' .
                'مع مسارات تأكيد الاسترداد/العربون.'
            );
        }

        throw (new ModelNotFoundException())->setModel(Deposit::class, [$id]);
    }

    /**
     * Get unified accounting queue: both deposits and reservations awaiting deposits.
     * Supports scope filtering: all, actionable, closed.
     * Applies post-filtering BEFORE pagination to maintain correct meta counts.
     */
    public function getUnifiedAccountingQueue(array $filters = [])
    {
        $scope = $filters['scope'] ?? 'actionable';
        $allRows = collect();

        // Fetch reservation rows awaiting deposit creation
        $reservations = $this->fetchReservationsAwaitingDeposit($filters, $scope);
        $reservationRows = $reservations->map(fn (SalesReservation $r) => 
            $this->transformUnifiedQueueRow($r, null)
        );
        $allRows = $allRows->concat($reservationRows);

        // Fetch deposit rows
        $deposits = $this->fetchDepositsForQueue($filters, $scope);
        $depositRows = $deposits->map(fn (Deposit $d) => 
            $this->transformUnifiedQueueRow(null, $d)
        );
        $allRows = $allRows->concat($depositRows);

        // Sort unified collection by date descending, then by ID descending
        $allRows = $allRows->sortByDesc(function ($row) {
            $sortKey = $row['sort_key'] ?? '0000-00-00 00:00:00-0000000000';
            return str_replace('-', '', $sortKey);
        })->values();

        // Apply post-filtering BEFORE pagination to maintain correct meta counts
        $allRows = $this->applyPostFilters($allRows, $filters);

        // Manual pagination since we're merging two collections
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 25);
        $total = $allRows->count();
        $lastPage = max(1, ceil($total / $perPage));

        if ($page > $lastPage && $total > 0) {
            $page = $lastPage;
        }

        $items = $allRows->forPage($page, $perPage);

        // Remove sort_key before returning (internal field only)
        $items = $items->map(function ($row) {
            unset($row['sort_key']);
            return $row;
        });

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * Fetch reservations awaiting deposit creation.
     */
    protected function fetchReservationsAwaitingDeposit(array $filters, string $scope)
    {
        $query = SalesReservation::query()
            ->with([
                'contract',
                'contractUnit',
                'commission',
                'deposits' => fn ($q) => $q->orderByDesc('payment_date')->orderByDesc('id'),
            ])
            ->where('status', 'confirmed')
            ->whereNotNull('contract_id')
            ->whereNotNull('contract_unit_id')
            ->whereNotNull('client_name')
            ->whereHas('contract')
            ->whereHas('contractUnit')
            ->whereDoesntHave('deposits'); // Only those WITHOUT deposits yet

        $this->applyUnifiedQueueFilters($query, $filters, 'reservation');
        $this->applyScopeFilter($query, $scope, 'reservation');

        return $query->get();
    }

    /**
     * Fetch deposits for the unified queue.
     */
    protected function fetchDepositsForQueue(array $filters, string $scope)
    {
        $query = Deposit::query()
            ->with([
                'salesReservation.contract',
                'salesReservation.contractUnit',
                'salesReservation.commission',
                'contract',
                'contractUnit',
            ])
            ->whereIn('status', ['pending', 'received', 'confirmed', 'refunded'])
            ->whereHas('salesReservation', function (Builder $q) {
                $q->where('status', 'confirmed')
                    ->whereNotNull('contract_id')
                    ->whereNotNull('contract_unit_id')
                    ->whereNotNull('client_name')
                    ->whereHas('contract')
                    ->whereHas('contractUnit');
            })
            ->whereHas('contract')
            ->whereHas('contractUnit');

        $this->applyUnifiedQueueFilters($query, $filters, 'deposit');
        $this->applyScopeFilter($query, $scope, 'deposit');

        return $query->get();
    }

    /**
     * Apply common filters to both reservation and deposit queries.
     */
    protected function applyUnifiedQueueFilters(Builder $query, array $filters, string $entityType): void
    {
        if (isset($filters['contract_id'])) {
            $query->where('contract_id', $filters['contract_id']);
        }

        if (isset($filters['project_id'])) {
            $query->where('contract_id', $filters['project_id']);
        }

        if (isset($filters['project_name'])) {
            $query->whereHas('contract', function (Builder $q) {
                $q->where('project_name', 'like', '%' . $filters['project_name'] . '%');
            });
        }

        if (isset($filters['client_name'])) {
            if ($entityType === 'reservation') {
                $query->where('client_name', 'like', '%' . $filters['client_name'] . '%');
            } else {
                $query->whereHas('salesReservation', function (Builder $q) {
                    $q->where('client_name', 'like', '%' . $filters['client_name'] . '%');
                });
            }
        }

        if (isset($filters['commission_source'])) {
            if ($entityType === 'deposit') {
                $query->where('commission_source', $filters['commission_source']);
            } else {
                $this->applyReservationCommissionSourceFilter($query, $filters['commission_source']);
            }
        }

        if (isset($filters['from_date'])) {
            if ($entityType === 'reservation') {
                $query->whereDate('confirmed_at', '>=', $filters['from_date']);
            } else {
                $query->whereDate('payment_date', '>=', $filters['from_date']);
            }
        }

        if (isset($filters['to_date'])) {
            if ($entityType === 'reservation') {
                $query->whereDate('confirmed_at', '<=', $filters['to_date']);
            } else {
                $query->whereDate('payment_date', '<=', $filters['to_date']);
            }
        }
    }

    /**
     * Apply post-filtering to rows AFTER they're merged but BEFORE pagination.
     * This ensures pagination meta counts are accurate.
     */
    protected function applyPostFilters(Collection $rows, array $filters): Collection
    {
        if (isset($filters['accounting_state'])) {
            $rows = $rows->where('accounting_state', $filters['accounting_state']);
        }

        if (isset($filters['deposit_status'])) {
            $rows = $rows->where('deposit_status', $filters['deposit_status']);
        }

        if (isset($filters['has_deposit'])) {
            $hasDeposit = (bool) $filters['has_deposit'];
            $rows = $rows->where('has_deposit', $hasDeposit);
        }

        return $rows;
    }

    /**
     * Apply scope filter (all, actionable, closed).
     */
    protected function applyScopeFilter(Builder $query, string $scope, string $entityType): void
    {
        if ($scope === 'actionable') {
            if ($entityType === 'reservation') {
                // Reservations awaiting deposit are always actionable
            } elseif ($entityType === 'deposit') {
                // Only pending and received deposits are actionable
                $query->whereIn('status', ['pending', 'received']);
            }
        } elseif ($scope === 'closed') {
            if ($entityType === 'deposit') {
                $query->whereIn('status', ['confirmed', 'refunded']);
            } else {
                // Reservations don't have a "closed" state in accounting
                $query->where('id', '<', 0); // Empty result
            }
        }
        // 'all' scope: no additional filtering
    }

    /**
     * Transform a unified queue row (reservation or deposit).
     */
    protected function transformUnifiedQueueRow(?SalesReservation $reservation, ?Deposit $deposit): array
    {
        if ($reservation !== null) {
            return $this->buildUnifiedQueueReservationRow($reservation);
        }
        return $this->buildUnifiedQueueDepositRow($deposit);
    }

    /**
     * Build unified queue row for a reservation awaiting deposit.
     */
    protected function buildUnifiedQueueReservationRow(SalesReservation $reservation): array
    {
        $contract = $reservation->contract;
        $contractUnit = $reservation->contractUnit;
        $pricing = $this->buildPricingPayload($reservation);
        $accountingState = 'awaiting_deposit_creation';
        $actionRequired = true;

        return [
            'id' => $reservation->id,
            'reservation_id' => $reservation->id,
            'deposit_id' => null,
            'row_entity' => 'sales_reservation',
            'accounting_state' => $accountingState,
            'action_required' => $actionRequired,
            'has_deposit' => false,
            'deposit_status' => null,
            'contract_id' => $contract?->id,
            'contract_unit_id' => $contractUnit?->id,
            'project_name' => $contract?->project_name,
            'unit_number' => $contractUnit?->unit_number,
            'unit_type' => $contractUnit?->unit_type,
            'unit_price' => $pricing['unit_price'],
            'final_selling_price' => $pricing['final_selling_price'],
            'client_name' => $reservation->client_name,
            'commission_percentage' => $pricing['commission_percentage'],
            'commission_source' => $pricing['commission_source'],
            'pricing' => $pricing,
            'deposit' => [
                'has_deposit' => false,
                'id' => null,
                'status' => null,
                'count' => 0,
                'amount' => null,
                'payment_date' => null,
                'payment_method' => null,
            ],
            'deposits' => [],
            'project' => [
                'contract_id' => $contract?->id,
                'name' => $contract?->project_name,
            ],
            'unit' => [
                'id' => $contractUnit?->id,
                'number' => $contractUnit?->unit_number,
                'type' => $contractUnit?->unit_type,
            ],
            'client' => [
                'name' => $reservation->client_name,
            ],
            'contract' => $contract ? [
                'id' => $contract->id,
                'project_name' => $contract->project_name,
            ] : null,
            'contract_unit' => $contractUnit ? [
                'id' => $contractUnit->id,
                'unit_number' => $contractUnit->unit_number,
                'unit_type' => $contractUnit->unit_type,
                'price' => $pricing['unit_price'],
            ] : null,
            'sort_key' => $reservation->confirmed_at?->format('Y-m-d H:i:s') . '-' . str_pad($reservation->id, 10, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Build unified queue row for a deposit.
     */
    protected function buildUnifiedQueueDepositRow(Deposit $deposit): array
    {
        $reservation = $deposit->salesReservation;
        $contract = $deposit->contract ?? $reservation?->contract;
        $contractUnit = $deposit->contractUnit ?? $reservation?->contractUnit;
        $pricing = $this->buildPricingPayload($reservation);
        $depositItem = $this->transformRelatedDeposit($deposit, $reservation);
        
        $accountingState = $this->determineDepositAccountingState($deposit);
        $actionRequired = $this->isAccountingActionRequired($accountingState);

        return [
            'id' => $deposit->id,
            'deposit_id' => $deposit->id,
            'reservation_id' => $reservation?->id,
            'row_entity' => 'deposit',
            'accounting_state' => $accountingState,
            'action_required' => $actionRequired,
            'has_deposit' => true,
            'deposit_status' => $deposit->status,
            'contract_id' => $contract?->id,
            'contract_unit_id' => $contractUnit?->id,
            'project_name' => $contract?->project_name,
            'unit_number' => $contractUnit?->unit_number,
            'unit_type' => $contractUnit?->unit_type,
            'unit_price' => $pricing['unit_price'],
            'final_selling_price' => $pricing['final_selling_price'],
            'amount' => $depositItem['amount'],
            'payment_method' => $depositItem['payment_method'],
            'payment_date' => $depositItem['payment_date'],
            'client_name' => $reservation?->client_name ?? $deposit->client_name,
            'commission_percentage' => $pricing['commission_percentage'],
            'commission_source' => $pricing['commission_source'],
            'status' => $deposit->status,
            'pricing' => $pricing,
            'deposit' => [
                'has_deposit' => true,
                'id' => $deposit->id,
                'count' => 1,
                'status' => $deposit->status,
                'amount' => $depositItem['amount'],
                'payment_date' => $depositItem['payment_date'],
                'payment_method' => $depositItem['payment_method'],
            ],
            'deposits' => [$depositItem],
            'project' => [
                'contract_id' => $contract?->id,
                'name' => $contract?->project_name,
            ],
            'unit' => [
                'id' => $contractUnit?->id,
                'number' => $contractUnit?->unit_number,
                'type' => $contractUnit?->unit_type,
            ],
            'client' => [
                'name' => $reservation?->client_name ?? $deposit->client_name,
            ],
            'contract' => $contract ? [
                'id' => $contract->id,
                'project_name' => $contract->project_name,
            ] : null,
            'contract_unit' => $contractUnit ? [
                'id' => $contractUnit->id,
                'unit_number' => $contractUnit->unit_number,
                'unit_type' => $contractUnit->unit_type,
                'price' => $pricing['unit_price'],
            ] : null,
            'sort_key' => $deposit->payment_date?->format('Y-m-d H:i:s') . '-' . str_pad($deposit->id, 10, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Determine if accounting action is required for this state.
     */
    protected function isAccountingActionRequired(string $accountingState): bool
    {
        return in_array($accountingState, [
            'awaiting_deposit_creation',
            'deposit_pending_confirmation',
            'deposit_received',
        ], true);
    }

    /**
     * Get the accounting queue for actionable deposit records (backward compatibility).
     */
    public function getPendingDeposits(array $filters = [])
    {
        // Use unified queue with actionable scope by default for backward compatibility
        $filters['scope'] = $filters['scope'] ?? 'actionable';
        return $this->getUnifiedAccountingQueue($filters);
    }

    /**
     * Confirm deposit receipt.
     */
    public function confirmDepositReceipt(int $depositId, int $accountingUserId): Deposit
    {
        $deposit = $this->findDepositForAccountingAction($depositId);
        $deposit->load(['salesReservation.marketingEmployee']);

        if ($deposit->status !== 'pending') {
            throw new Exception('Only pending deposits can be confirmed.');
        }

        DB::beginTransaction();
        try {
            $deposit->confirmReceipt($accountingUserId);

            if ($deposit->salesReservation && $deposit->salesReservation->marketing_employee_id) {
                UserNotification::create([
                    'user_id' => $deposit->salesReservation->marketing_employee_id,
                    'message' => "تم تأكيد استلام العربون بمبلغ {$deposit->amount} ريال سعودي للحجز رقم {$deposit->sales_reservation_id}.",
                    'status' => 'pending',
                ]);
            }

            $this->notifyCreditDepartment($deposit);

            DB::commit();

            return $deposit->fresh(['confirmedBy', 'salesReservation']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get the reservation-centric follow-up queue for accounting.
     */
    public function getDepositFollowUp(array $filters = [])
    {
        $query = SalesReservation::query()
            ->with([
                'contract',
                'contractUnit',
                'commission',
                'deposits' => fn ($query) => $query->orderByDesc('payment_date')->orderByDesc('id'),
            ])
            ->where('status', 'confirmed')
            ->whereNotNull('contract_id')
            ->whereNotNull('contract_unit_id')
            ->whereNotNull('client_name')
            ->whereHas('contract')
            ->whereHas('contractUnit');

        if (isset($filters['project_id'])) {
            $query->where('contract_id', $filters['project_id']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('confirmed_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('confirmed_at', '<=', $filters['to_date']);
        }

        if (isset($filters['commission_source'])) {
            $this->applyReservationCommissionSourceFilter($query, $filters['commission_source']);
        }

        $paginator = $query->orderByDesc('confirmed_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 15);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (SalesReservation $reservation) => $this->transformFollowUpForList($reservation))
        );

        return $paginator;
    }

    /**
     * Process deposit refund.
     */
    public function processRefund(int $depositId): Deposit
    {
        $deposit = $this->findDepositForAccountingAction($depositId);
        $deposit->load(['salesReservation']);

        if (!$deposit->isRefundable()) {
            throw new Exception('This deposit is not refundable. Deposits with buyer as commission source are non-refundable.');
        }

        if ($deposit->isRefunded()) {
            throw new Exception('This deposit has already been refunded.');
        }

        if (!in_array($deposit->status, ['received', 'confirmed'], true)) {
            throw new Exception('يجب تأكيد استلام العربون أو تسجيله كمستلم قبل إمكانية الإرجاع.');
        }

        DB::beginTransaction();
        try {
            $deposit->refund();

            if ($deposit->salesReservation && $deposit->salesReservation->marketing_employee_id) {
                UserNotification::create([
                    'user_id' => $deposit->salesReservation->marketing_employee_id,
                    'message' => "تم استرداد العربون بمبلغ {$deposit->amount} ريال سعودي للحجز رقم {$deposit->sales_reservation_id}.",
                    'status' => 'pending',
                ]);
            }

            DB::commit();

            return $deposit->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate claim file for commission.
     */
    public function generateClaimFile(int $reservationId): array
    {
        $reservation = SalesReservation::with([
            'contract',
            'contractUnit',
            'commission.distributions.user',
            'deposits',
        ])->findOrFail($reservationId);

        if (!$reservation->commission) {
            throw new Exception('No commission found for this reservation.');
        }

        $commission = $reservation->commission;
        if ($commission->commission_source !== 'owner') {
            throw new Exception('ملف المطالبة متاح فقط عندما تكون نسبة السعي من المالك.');
        }

        $finalSellingPrice = $commission->final_selling_price ?? $reservation->proposed_price ?? $reservation->contractUnit?->price;
        $finalSellingPrice = $finalSellingPrice !== null ? round((float) $finalSellingPrice, 2) : null;

        return [
            'reservation_id' => $reservation->id,
            'project_name' => $reservation->contract?->project_name,
            'unit_number' => $reservation->contractUnit?->unit_number,
            'unit_type' => $reservation->contractUnit?->unit_type,
            'client_name' => $reservation->client_name,
            'final_selling_price' => $finalSellingPrice,
            'commission_percentage' => $commission->commission_percentage !== null ? round((float) $commission->commission_percentage, 2) : null,
            'commission_amount' => $commission->net_amount !== null ? round((float) $commission->net_amount, 2) : null,
            'commission_source' => $commission->commission_source,
            'deposit_amount' => round($reservation->deposits->sum('amount'), 2),
            'distributions' => $commission->distributions?->map(fn ($distribution) => [
                'type' => $distribution->type,
                'employee_name' => $distribution->user?->name,
                'percentage' => $distribution->percentage !== null ? round((float) $distribution->percentage, 2) : null,
                'amount' => $distribution->amount !== null ? round((float) $distribution->amount, 2) : null,
            ])->values()->all() ?? [],
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    protected function transformFollowUpForList(SalesReservation $reservation): array
    {
        $contract = $reservation->contract;
        $contractUnit = $reservation->contractUnit;
        $pricing = $this->buildPricingPayload($reservation);
        $deposits = $this->transformReservationDeposits($reservation);
        $latestDeposit = $reservation->deposits->sortByDesc(fn (Deposit $deposit) => sprintf(
            '%s-%010d',
            optional($deposit->payment_date)->format('Y-m-d H:i:s.u') ?? '',
            $deposit->id
        ))->first();

        return [
            'id' => $reservation->id,
            'reservation_id' => $reservation->id,
            'row_entity' => 'sales_reservation',
            'deposit_id' => $latestDeposit?->id,
            'accounting_state' => $this->determineReservationAccountingState($reservation),
            'project_name' => $contract?->project_name,
            'unit_number' => $contractUnit?->unit_number,
            'unit_type' => $contractUnit?->unit_type,
            'unit_price' => $pricing['unit_price'],
            'client_name' => $reservation->client_name,
            'final_selling_price' => $pricing['final_selling_price'],
            'commission_percentage' => $pricing['commission_percentage'],
            'commission_source' => $pricing['commission_source'],
            'contract_id' => $contract?->id,
            'contract_unit_id' => $contractUnit?->id,
            'has_deposit' => $reservation->deposits->isNotEmpty(),
            'reservation' => [
                'id' => $reservation->id,
            ],
            'project' => [
                'contract_id' => $contract?->id,
                'name' => $contract?->project_name,
            ],
            'unit' => [
                'id' => $contractUnit?->id,
                'number' => $contractUnit?->unit_number,
                'type' => $contractUnit?->unit_type,
            ],
            'client' => [
                'name' => $reservation->client_name,
            ],
            'pricing' => $pricing,
            'deposit' => [
                'has_deposit' => $reservation->deposits->isNotEmpty(),
                'id' => $latestDeposit?->id,
                'count' => $reservation->deposits->count(),
                'latest_status' => $latestDeposit?->status,
            ],
            'deposits' => $deposits,
            'contract' => $contract ? [
                'id' => $contract->id,
                'project_name' => $contract->project_name,
            ] : null,
            'contract_unit' => $contractUnit ? [
                'id' => $contractUnit->id,
                'unit_number' => $contractUnit->unit_number,
                'unit_type' => $contractUnit->unit_type,
                'price' => $pricing['unit_price'],
            ] : null,
        ];
    }

    protected function transformReservationDeposits(SalesReservation $reservation): array
    {
        return $reservation->deposits
            ->sortByDesc(fn (Deposit $deposit) => sprintf(
                '%s-%010d',
                optional($deposit->payment_date)->format('Y-m-d H:i:s.u') ?? '',
                $deposit->id
            ))
            ->map(fn (Deposit $deposit) => $this->transformRelatedDeposit($deposit, $reservation))
            ->values()
            ->all();
    }

    protected function transformRelatedDeposit(Deposit $deposit, ?SalesReservation $reservation = null): array
    {
        return [
            'deposit_id' => $deposit->id,
            'id' => $deposit->id,
            'reservation_id' => $reservation?->id ?? $deposit->sales_reservation_id,
            'amount' => $deposit->amount !== null ? round((float) $deposit->amount, 2) : null,
            'status' => $deposit->status,
            'payment_method' => $deposit->payment_method,
            'payment_date' => $deposit->payment_date?->format('Y-m-d'),
            'commission_source' => $deposit->commission_source,
        ];
    }

    protected function buildPricingPayload(?SalesReservation $reservation): array
    {
        $commission = $this->resolveCommissionContext($reservation);
        $contractUnit = $reservation?->contractUnit;
        $commissionModel = $reservation?->commission;

        $unitPrice = $contractUnit?->price !== null ? round((float) $contractUnit->price, 2) : null;
        $finalSellingPrice = null;
        $finalSellingPriceResolutionSource = 'unresolved';

        if ($commissionModel?->final_selling_price !== null) {
            $finalSellingPrice = round((float) $commissionModel->final_selling_price, 2);
            $finalSellingPriceResolutionSource = 'commission';
        } elseif ($reservation?->proposed_price !== null) {
            $finalSellingPrice = round((float) $reservation->proposed_price, 2);
            $finalSellingPriceResolutionSource = 'reservation';
        } elseif ($contractUnit?->price !== null) {
            $finalSellingPrice = round((float) $contractUnit->price, 2);
            $finalSellingPriceResolutionSource = 'unit';
        }

        return [
            'unit_price' => $unitPrice,
            'final_selling_price' => $finalSellingPrice,
            'final_selling_price_resolution_source' => $finalSellingPriceResolutionSource,
            'commission_percentage' => $commission['percentage'],
            'commission_source' => $commission['source'],
            'commission_resolution_source' => $commission['resolution_source'],
        ];
    }

    protected function resolveCommissionContext(?SalesReservation $reservation): array
    {
        $commissionModel = $reservation?->commission;
        $contract = $reservation?->contract;

        $percentage = null;
        $percentageSource = null;
        if ($commissionModel?->commission_percentage !== null) {
            $percentage = round((float) $commissionModel->commission_percentage, 2);
            $percentageSource = 'commission';
        } elseif ($reservation?->brokerage_commission_percent !== null) {
            $percentage = round((float) $reservation->brokerage_commission_percent, 2);
            $percentageSource = 'reservation';
        } elseif ($contract?->commission_percent !== null) {
            $percentage = round((float) $contract->commission_percent, 2);
            $percentageSource = 'contract';
        }

        $source = null;
        $sourceResolution = null;
        if ($commissionModel?->commission_source !== null) {
            $source = $this->normalizeCommissionParty($commissionModel->commission_source);
            $sourceResolution = 'commission';
        } elseif ($reservation?->commission_payer !== null) {
            $source = $this->normalizeCommissionParty($reservation->commission_payer);
            $sourceResolution = 'reservation';
        } elseif ($contract?->commission_from !== null) {
            $source = $this->normalizeCommissionParty($contract->commission_from);
            $sourceResolution = 'contract';
        }

        if ($source === null) {
            $source = 'unresolved';
        }

        return [
            'percentage' => $percentage,
            'source' => $source,
            'resolution_source' => $percentageSource === null && $sourceResolution === null
                ? 'unresolved'
                : ($percentageSource === $sourceResolution || $sourceResolution === null
                    ? ($percentageSource ?? $sourceResolution)
                    : ($percentageSource === null ? $sourceResolution : 'mixed')),
        ];
    }

    protected function normalizeCommissionParty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (mb_strtolower(trim($value))) {
            'owner', 'seller', 'المالك' => 'owner',
            'buyer', 'المشتري' => 'buyer',
            'both', 'الطرفين' => 'both',
            default => trim($value),
        };
    }

    protected function determineDepositAccountingState(Deposit $deposit): string
    {
        return match ($deposit->status) {
            'pending' => 'deposit_pending_confirmation',
            'received' => 'deposit_received',
            'confirmed' => 'deposit_confirmed',
            'refunded' => 'deposit_refunded',
            default => 'deposit_unknown',
        };
    }

    protected function determineReservationAccountingState(SalesReservation $reservation): string
    {
        if ($reservation->deposits->isEmpty()) {
            return 'awaiting_deposit_creation';
        }

        if ($reservation->deposits->contains(fn (Deposit $deposit) => $deposit->status === 'pending')) {
            return 'deposit_pending_confirmation';
        }

        if ($reservation->deposits->contains(fn (Deposit $deposit) => $deposit->status === 'received')) {
            return 'deposit_received';
        }

        if ($reservation->deposits->contains(fn (Deposit $deposit) => $deposit->status === 'confirmed')) {
            return 'deposit_confirmed';
        }

        if ($reservation->deposits->contains(fn (Deposit $deposit) => $deposit->status === 'refunded')) {
            return 'deposit_refunded';
        }

        return 'deposit_unknown';
    }

    protected function applyReservationCommissionSourceFilter(Builder $query, string $commissionSource): void
    {
        $reservationPayer = $commissionSource === 'owner' ? 'seller' : 'buyer';
        $contractValues = $commissionSource === 'owner'
            ? ['owner', 'المالك']
            : ['buyer', 'المشتري'];

        $query->where(function (Builder $filterQuery) use ($commissionSource, $reservationPayer, $contractValues) {
            $filterQuery->whereHas('commission', fn (Builder $commissionQuery) => $commissionQuery->where('commission_source', $commissionSource))
                ->orWhere(function (Builder $reservationQuery) use ($reservationPayer) {
                    $reservationQuery->whereDoesntHave('commission')
                        ->where('commission_payer', $reservationPayer);
                })
                ->orWhere(function (Builder $contractQuery) use ($contractValues) {
                    $contractQuery->whereDoesntHave('commission')
                        ->whereNull('commission_payer')
                        ->whereHas('contract', fn (Builder $projectQuery) => $projectQuery->whereIn('commission_from', $contractValues));
                });
        });
    }

    /**
     * Notify credit department about confirmed deposit.
     */
    protected function notifyCreditDepartment(Deposit $deposit): void
    {
        $message = sprintf(
            'تم تأكيد استلام العربون بمبلغ %s ريال سعودي للمشروع: %s - الوحدة: %s',
            $deposit->amount,
            $deposit->contract?->project_name ?? '-',
            $deposit->contractUnit?->unit_number ?? '-'
        );

        $creditUsers = User::where('type', 'credit')->get();
        foreach ($creditUsers as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'message' => $message,
                'status' => 'pending',
            ]);
        }
    }
}
