<?php

namespace App\Http\Controllers\Credit;

use App\Constants\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SalesReservation;
use App\Models\SalesWaitingList;
use App\Services\Sales\SalesReservationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditBookingController extends Controller
{
    /** SRS 3.2.1: مدة العميل قبل الإفراغ (كاش) - أيام */
    private const CASH_CLIENT_EVACUATION_GRACE_DAYS = 7;

    /** SRS 3.2.1: مدة موظف الائتمان لتجهيز أوراق الإفراغ وتحديد الموعد (كاش) - أيام */
    private const CASH_CREDIT_PREPARATION_DAYS = 7;

    public function __construct(
        protected ?SalesReservationService $reservationService = null
    ) {
        $this->reservationService = $reservationService ?? app(SalesReservationService::class);
    }

    /**
     * Get all credit bookings (All tab): confirmed + negotiation + cancelled in one list.
     * GET /credit/bookings
     * Query: search, contract_id, credit_status, from_date, to_date, sort_by, sort_order, per_page
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SalesReservation::with([
                'contract',
                'contractUnit',
                'marketingEmployee.team',
                'financingTracker',
                'titleTransfer',
            ])
                ->whereIn('status', ReservationStatus::forCreditBookingList());

            $this->applyReservationListFilters($query, $request, 'confirmed_at');
            if ($request->has('credit_status')) {
                $query->byCreditStatus($request->input('credit_status'));
            }
            $this->applySort($query, $request, 'reservation', 'COALESCE(confirmed_at, created_at) DESC');

            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $bookings = $query->paginate($perPage);

            $items = $this->transformBookingsForList($bookings->getCollection());

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الحجوزات بنجاح',
                'data' => $items,
                'meta' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get confirmed bookings for credit.
     * GET /credit/bookings/confirmed
     */
    public function confirmed(Request $request): JsonResponse
    {
        try {
            $query = SalesReservation::with([
                'contract',
                'contractUnit',
                'marketingEmployee.team',
                'financingTracker',
                'titleTransfer',
            ])
                ->confirmedForCredit();

            // Filter by credit status
            if ($request->has('credit_status')) {
                $query->byCreditStatus($request->input('credit_status'));
            }

            // Filter by purchase mechanism
            if ($request->has('purchase_mechanism')) {
                $query->where('purchase_mechanism', $request->input('purchase_mechanism'));
            }

            // Filter by down payment confirmation
            if ($request->has('down_payment_confirmed')) {
                $query->where('down_payment_confirmed', filter_var($request->input('down_payment_confirmed'), FILTER_VALIDATE_BOOLEAN));
            }

            $this->applyReservationListFilters($query, $request, 'confirmed_at');
            $this->applySort($query, $request, 'reservation', 'confirmed_at DESC');

            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $bookings = $query->paginate($perPage);

            $items = $this->transformBookingsForList($bookings->getCollection());

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الحجوزات المؤكدة بنجاح',
                'data' => $items,
                'meta' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get negotiation bookings (read-only).
     * GET /credit/bookings/negotiation
     */
    public function negotiation(Request $request): JsonResponse
    {
        try {
            $query = SalesReservation::with([
                'contract',
                'contractUnit',
                'marketingEmployee.team',
                'negotiationApproval',
            ])
                ->where('status', ReservationStatus::UNDER_NEGOTIATION)
                ->where('reservation_type', 'negotiation');

            $this->applyReservationListFilters($query, $request, 'created_at');
            $this->applySort($query, $request, 'reservation', 'created_at DESC');

            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $bookings = $query->paginate($perPage);

            $items = $this->transformBookingsForList($bookings->getCollection());

            return response()->json([
                'success' => true,
                'message' => 'تم جلب حجوزات التفاوض بنجاح',
                'data' => $items,
                'meta' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update negotiation booking (e.g. mark viewed / notes). Read-only in practice; accepts PATCH for frontend compatibility.
     * PATCH /credit/bookings/negotiation/{id}
     */
    public function updateNegotiation(Request $request, $id): JsonResponse
    {
        if (!is_numeric($id) || (int) $id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'معرف الحجز غير صالح',
            ], 404);
        }
        $id = (int) $id;
        try {
            $reservation = SalesReservation::with([
                'contract',
                'contractUnit',
                'marketingEmployee.team',
                'negotiationApproval',
            ])
                ->where('id', $id)
                ->where('status', ReservationStatus::UNDER_NEGOTIATION)
                ->where('reservation_type', 'negotiation')
                ->firstOrFail();

            // Optional: update credit-side fields if added later (e.g. credit_notes, viewed_at)
            // For now return success and booking data so frontend does not 404
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة التفاوض',
                'data' => $reservation,
            ], 200);
        } catch (Exception $e) {
            $code = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $code === 404 ? 'الحجز غير موجود أو ليس تحت التفاوض' : $e->getMessage(),
            ], $code);
        }
    }

    /**
     * Get waiting bookings (read-only).
     * GET /credit/bookings/waiting
     */
    public function waiting(Request $request): JsonResponse
    {
        try {
            $query = SalesWaitingList::with([
                'contract',
                'contractUnit',
                'salesStaff',
            ])
                ->where('status', 'waiting');

            $this->applyWaitingListFilters($query, $request);
            $this->applySort($query, $request, 'waiting', 'created_at DESC');

            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $bookings = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب حجوزات الانتظار بنجاح',
                'data' => $bookings->items(),
                'meta' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process a waiting-list booking (e.g. record action and notes). Does not convert to reservation.
     * PATCH /credit/bookings/waiting/{id} or POST /credit/bookings/waiting/{id}/process
     */
    public function processWaiting(Request $request, $id): JsonResponse
    {
        if (!is_numeric($id) || (int) $id <= 0) {
            return ApiResponse::error('معرف الحجز غير صالح', 400);
        }
        $id = (int) $id;
        try {
            $entry = SalesWaitingList::with(['contract', 'contractUnit', 'salesStaff'])
                ->where('status', 'waiting')
                ->findOrFail($id);

            $notes = $request->input('notes');
            if (is_string($notes) && $notes !== '') {
                $entry->notes = $notes;
                $entry->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'تمت معالجة الحجز بنجاح',
                'data' => $entry->fresh(['contract', 'contractUnit', 'salesStaff']),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('الحجز غير موجود أو ليس في قائمة الانتظار');
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sold bookings (مباعة tab).
     * GET /credit/bookings/sold
     */
    public function sold(Request $request): JsonResponse
    {
        try {
            $query = SalesReservation::with([
                'contract',
                'contractUnit',
                'marketingEmployee.team',
                'financingTracker',
                'titleTransfer',
            ])
                ->where('credit_status', 'sold');

            $this->applyReservationListFilters($query, $request, 'confirmed_at');
            $this->applySort($query, $request, 'reservation', 'confirmed_at DESC');

            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $bookings = $query->paginate($perPage);

            $items = $this->transformBookingsForList($bookings->getCollection());

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الحجوزات المباعة بنجاح',
                'data' => $items,
                'meta' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cancelled/rejected bookings (مرفوضة / ملغاة tab).
     * GET /credit/bookings/cancelled
     */
    public function cancelled(Request $request): JsonResponse
    {
        try {
            $query = SalesReservation::with([
                'contract',
                'contractUnit',
                'marketingEmployee.team',
            ])
                ->whereNotNull('cancelled_at');

            $this->applyReservationListFilters($query, $request, 'cancelled_at');
            $this->applySort($query, $request, 'reservation', 'cancelled_at DESC');

            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $bookings = $query->paginate($perPage);

            $items = $this->transformBookingsForList($bookings->getCollection());

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الحجوزات الملغاة بنجاح',
                'data' => $items,
                'meta' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get booking details.
     * GET /credit/bookings/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $reservation = SalesReservation::with([
                'contract.info',
                'contract.teams',
                'contractUnit',
                'marketingEmployee.team',
                'financingTracker.assignedUser',
                'titleTransfer.processedBy',
                'claimFile',
                'negotiationApproval',
                'paymentInstallments',
                'deposits',
            ])->findOrFail($id);

            $downPaymentDate = $reservation->deposits->sortBy('payment_date')->first()?->payment_date ?? $reservation->down_payment_confirmed_at;
            $downPaymentDateFormatted = $downPaymentDate ? \Carbon\Carbon::parse($downPaymentDate)->format('Y-m-d') : null;

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الحجز بنجاح',
                'data' => [
                    'id' => $reservation->id,
                    // Project Information (3.1.1) – always strings for UI
                    'project' => [
                        'id' => $reservation->contract?->id,
                        'name' => $reservation->contract?->project_name ?? $reservation->contract?->info?->project_name ?? 'غير محدد',
                        'district' => $reservation->contract?->district ?? '',
                        'city' => $reservation->contract?->city ?? '',
                        'property_type' => $reservation->contractUnit?->unit_type ?? '',
                        'unit_value' => $reservation->contractUnit?->price,
                    ],
                    // Unit Information
                    'unit' => [
                        'id' => $reservation->contractUnit?->id,
                        'number' => $reservation->contractUnit?->unit_number,
                        'type' => $reservation->contractUnit?->unit_type,
                        'area' => $reservation->contractUnit?->area,
                        'price' => $reservation->contractUnit?->price,
                    ],
                    // Client Information (3.1.2)
                    'client' => [
                        'name' => $reservation->client_name ?? 'غير محدد',
                        'mobile' => $reservation->client_mobile,
                        'email' => $reservation->client_email ?? null, // optional; add client_email column to sales_reservations if needed
                        'nationality' => $reservation->client_nationality,
                        'iban' => $reservation->client_iban,
                    ],
                    // Financial Details (3.1.3)
                    'financial' => [
                        'down_payment_amount' => $reservation->down_payment_amount,
                        'down_payment_date' => $downPaymentDateFormatted,
                        'down_payment_status' => $reservation->down_payment_status,
                        'down_payment_confirmed' => $reservation->down_payment_confirmed,
                        'down_payment_confirmed_at' => $reservation->down_payment_confirmed_at?->toIso8601String(),
                        'payment_method' => $reservation->payment_method,
                        'purchase_mechanism' => $reservation->purchase_mechanism,
                        'brokerage_commission_percent' => $reservation->brokerage_commission_percent,
                        'commission_payer' => $reservation->commission_payer,
                        'tax_amount' => $reservation->tax_amount,
                    ],
                    // Marketing Details (3.1.4) – فريق المشروع، فريق البائع، آلية الشراء (always strings for UI)
                    'marketing' => [
                        'team_name' => $reservation->marketingEmployee?->team?->name ?? 'غير معين',
                        'project_team' => $reservation->contract?->teams?->first()?->name ?? $reservation->marketingEmployee?->team?->name ?? 'غير معين',
                        'seller_team' => $reservation->marketingEmployee?->team?->name ?? 'غير معين',
                        'marketer_name' => $reservation->marketingEmployee?->name ?? 'غير معين',
                        'purchase_mechanism' => $reservation->purchase_mechanism,
                        'purchase_mechanism_label_ar' => $this->purchaseMechanismLabelAr($reservation->purchase_mechanism) ?? 'غير محدد',
                    ],
                    // Status
                    'status' => $reservation->status,
                    'credit_status' => $reservation->credit_status,
                    'reservation_type' => $reservation->reservation_type,
                    // Dates
                    'contract_date' => $reservation->contract_date,
                    'confirmed_at' => $reservation->confirmed_at,
                    // Related data (financing without internal tracker id; booking-centric only)
                    'financing' => $this->financingForUserOrNull($reservation),
                    'title_transfer' => $reservation->titleTransfer,
                    'claim_file' => $reservation->claimFile,
                    // متابعة إجراءات الائتمان – 7 steps (التواصل مع العميل، رفع الطلب، صدور التقييم، زيارة المقيم، الإجراءات البنكية، تنفيذ العقود، فترة التجهيز)
                    'credit_procedure_steps' => $this->buildCreditProcedureSteps($reservation),
                    'payment_installments' => $reservation->paymentInstallments,
                    // Flags
                    'is_cash_purchase' => $reservation->isCashPurchase(),
                    'is_bank_financing' => $reservation->isBankFinancing(),
                    'is_supported_bank' => $reservation->isSupportedBank(),
                    'requires_accounting_confirmation' => $reservation->requiresAccountingConfirmation(),
                    // SRS 3.2.1: كاش – العميل 7 أيام قبل الإفراغ؛ موظف الائتمان 7 أيام لتجهيز الأوراق وتحديد الموعد
                    'cash_terms' => $reservation->isCashPurchase() ? [
                        'client_evacuation_grace_days' => self::CASH_CLIENT_EVACUATION_GRACE_DAYS,
                        'credit_preparation_days' => self::CASH_CREDIT_PREPARATION_DAYS,
                    ] : null,
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Cancel reservation (3.5 إلغاء الحجز: رفض البنك / تراجع العميل).
     * POST /credit/bookings/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate(['cancellation_reason' => 'nullable|string|max:500']);

        try {
            $reservation = $this->reservationService->cancelReservation(
                $id,
                $request->input('cancellation_reason'),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الحجز بنجاح',
                'data' => [
                    'id' => $reservation->id,
                    'status' => $reservation->status,
                    'cancelled_at' => $reservation->cancelled_at?->toIso8601String(),
                ],
            ], 200);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Apply search, contract_id, and optional date range to a SalesReservation query.
     * Query params: search, contract_id, from_date, to_date (when $dateColumn is set).
     */
    private function applyReservationListFilters($query, Request $request, ?string $dateColumn = null): void
    {
        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                if (is_numeric($term)) {
                    $q->orWhere('sales_reservations.id', (int) $term);
                }
                $q->orWhere('sales_reservations.client_name', 'like', '%' . $term . '%');
                $q->orWhereHas('contract', function ($c) use ($term) {
                    $c->where('project_name', 'like', '%' . $term . '%');
                });
                $q->orWhereHas('contractUnit', function ($u) use ($term) {
                    $u->where('unit_number', 'like', '%' . $term . '%');
                });
            });
        }
        if ($request->filled('contract_id')) {
            $query->where('sales_reservations.contract_id', $request->input('contract_id'));
        }
        if ($dateColumn) {
            if ($request->filled('from_date')) {
                $query->whereDate('sales_reservations.' . $dateColumn, '>=', $request->input('from_date'));
            }
            if ($request->filled('to_date')) {
                $query->whereDate('sales_reservations.' . $dateColumn, '<=', $request->input('to_date'));
            }
        }
    }

    /**
     * Apply search, contract_id, and date range to a SalesWaitingList query.
     * Query params: search, contract_id, from_date, to_date.
     */
    private function applyWaitingListFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                if (is_numeric($term)) {
                    $q->orWhere('sales_waiting_list.id', (int) $term);
                }
                $q->orWhere('sales_waiting_list.client_name', 'like', '%' . $term . '%');
                $q->orWhereHas('contract', function ($c) use ($term) {
                    $c->where('project_name', 'like', '%' . $term . '%');
                });
                $q->orWhereHas('contractUnit', function ($u) use ($term) {
                    $u->where('unit_number', 'like', '%' . $term . '%');
                });
            });
        }
        if ($request->filled('contract_id')) {
            $query->where('sales_waiting_list.contract_id', $request->input('contract_id'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('sales_waiting_list.created_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('sales_waiting_list.created_at', '<=', $request->input('to_date'));
        }
    }

    /**
     * Apply sort to reservation or waiting-list query.
     * Query params: sort_by, sort_order (asc|desc).
     * $defaultOrder: e.g. 'confirmed_at DESC' or 'created_at DESC'.
     */
    private function applySort($query, Request $request, string $type, string $defaultOrder): void
    {
        $allowed = $type === 'reservation'
            ? ['id', 'client_name', 'confirmed_at', 'created_at', 'cancelled_at', 'contract_id']
            : ['id', 'client_name', 'created_at', 'contract_id'];
        $table = $type === 'reservation' ? 'sales_reservations' : 'sales_waiting_list';
        $sortBy = $request->input('sort_by');
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        if ($sortBy && in_array($sortBy, $allowed, true)) {
            $query->orderBy($table . '.' . $sortBy, $sortOrder);
        } else {
            $query->orderByRaw($defaultOrder);
        }
    }

    /**
     * Transform reservation collection for list responses: add top-level client_name, project_name, booking_date, credit_status_label_ar
     * so the frontend table can display full data. Uses "غير محدد" when name is null so the UI always has a display string.
     */
    private function transformBookingsForList(\Illuminate\Support\Collection $collection): array
    {
        return $collection->map(function (SalesReservation $reservation) {
            $arr = $reservation->toArray();
            $arr['client_name'] = $reservation->client_name ?? 'غير محدد';
            $arr['project_name'] = $reservation->contract?->project_name ?? $reservation->contract?->info?->project_name ?? 'غير محدد';
            $arr['booking_date'] = ($reservation->confirmed_at ?? $reservation->created_at)?->format('Y-m-d');
            $arr['credit_status_label_ar'] = $this->creditStatusLabelAr($reservation->credit_status);
            return $arr;
        })->all();
    }

    /**
     * Arabic label for credit_status for consistent list display.
     */
    private function creditStatusLabelAr(?string $status): string
    {
        return match ($status) {
            'pending' => 'قيد الانتظار',
            'in_progress' => 'قيد التنفيذ',
            'title_transfer' => 'نقل الملكية',
            'sold' => 'مباع',
            'rejected' => 'مرفوض',
            default => $status ?? '—',
        };
    }

    /**
     * Financing state for API without internal tracker id (booking-centric only). Returns null if none.
     */
    private function financingForUserOrNull(SalesReservation $reservation): ?array
    {
        $tracker = $reservation->financingTracker;
        if (!$tracker) {
            return null;
        }
        $arr = $tracker->toArray();
        unset($arr['id']);
        $arr['booking_id'] = $reservation->id;

        return $arr;
    }

    /**
     * Arabic label for purchase_mechanism (آلية الشراء).
     */
    private function purchaseMechanismLabelAr(?string $value): ?string
    {
        return match ($value) {
            'cash' => 'كاش',
            'supported_bank' => 'بنك مدعوم',
            'unsupported_bank' => 'بنك غير مدعوم',
            default => $value,
        };
    }

    /**
     * Build 7 credit procedure steps for UI (متابعة إجراءات الائتمان).
     * Steps: التواصل مع العميل، رفع الطلب للبنك، صدور التقييم، زيارة المقيم، الإجراءات البنكية والعقود، تنفيذ العقود، فترة التجهيز قبل الإفراغ.
     */
    private function buildCreditProcedureSteps(SalesReservation $reservation): array
    {
        $tracker = $reservation->financingTracker;
        $titleTransfer = $reservation->titleTransfer;

        $steps = [
            ['key' => 'contact_client', 'label_ar' => 'التواصل مع العميل', 'status' => 'pending', 'date' => null],
            ['key' => 'submit_to_bank', 'label_ar' => 'رفع الطلب للبنك', 'status' => 'pending', 'date' => null],
            ['key' => 'valuation', 'label_ar' => 'صدور التقييم', 'status' => 'pending', 'date' => null],
            ['key' => 'appraiser_visit', 'label_ar' => 'زيارة المقيم للمشروع', 'status' => 'pending', 'date' => null],
            ['key' => 'bank_contracts', 'label_ar' => 'الإجراءات البنكية والعقود', 'status' => 'pending', 'date' => null],
            ['key' => 'contract_execution', 'label_ar' => 'تنفيذ العقود', 'status' => 'pending', 'date' => null],
            ['key' => 'pre_evacuation', 'label_ar' => 'فترة التجهيز قبل الإفراغ', 'status' => 'pending', 'date' => null],
        ];

        if ($tracker) {
            $stageStatusMap = [
                1 => 0,
                2 => 1,
                3 => 2,
                4 => 3,
                5 => 4,
            ];
            foreach ($stageStatusMap as $stageNum => $stepIndex) {
                $status = $tracker->{"stage_{$stageNum}_status"};
                $steps[$stepIndex]['status'] = $status;
                $date = $tracker->{"stage_{$stageNum}_completed_at"} ?? $tracker->{"stage_{$stageNum}_deadline"};
                $steps[$stepIndex]['date'] = $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : null;
            }
        }

        if ($titleTransfer) {
            $ttStatus = $titleTransfer->status;
            $steps[5]['status'] = in_array($ttStatus, ['scheduled', 'completed'], true) ? $ttStatus : 'pending';
            $steps[5]['date'] = $titleTransfer->scheduled_date?->format('Y-m-d') ?? $titleTransfer->completed_date?->format('Y-m-d');
            $steps[6]['status'] = $ttStatus === 'preparation' ? 'in_progress' : ($ttStatus === 'completed' ? 'completed' : 'pending');
            $steps[6]['date'] = $titleTransfer->completed_date?->format('Y-m-d');
        }

        if ($reservation->credit_status === 'sold') {
            $steps[5]['status'] = 'completed';
            $steps[6]['status'] = 'completed';
        }

        return $steps;
    }
}



