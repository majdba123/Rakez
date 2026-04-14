<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Resources\Sales\SalesTargetResource;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class HrTargetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SalesTarget::with(['leader', 'marketer', 'contract.city', 'contract.district', 'contractUnit', 'contractUnits']);

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('marketer_id')) {
                $query->where('marketer_id', (int) $request->input('marketer_id'));
            }

            if ($request->filled('leader_id')) {
                $query->where('leader_id', (int) $request->input('leader_id'));
            }

            if ($request->filled('contract_id')) {
                $query->where('contract_id', (int) $request->input('contract_id'));
            }

            if ($request->filled('target_type')) {
                $query->where('target_type', $request->input('target_type'));
            }

            if ($request->filled('from')) {
                $query->whereDate('start_date', '>=', $request->input('from'));
            }

            if ($request->filled('to')) {
                $query->whereDate('end_date', '<=', $request->input('to'));
            }

            $perPage = min((int) $request->input('per_page', 15), 100);

            $targets = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الأهداف بنجاح',
                'data' => SalesTargetResource::collection($targets->items()),
                'meta' => [
                    'total' => $targets->total(),
                    'per_page' => $targets->perPage(),
                    'current_page' => $targets->currentPage(),
                    'last_page' => $targets->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function statistics(Request $request, int $marketerId): JsonResponse
    {
        try {
            $query = SalesTarget::where('marketer_id', $marketerId);

            if ($request->filled('leader_id')) {
                $query->where('leader_id', (int) $request->input('leader_id'));
            }

            if ($request->filled('contract_id')) {
                $query->where('contract_id', (int) $request->input('contract_id'));
            }

            if ($request->filled('from')) {
                $query->whereDate('start_date', '>=', $request->input('from'));
            }

            if ($request->filled('to')) {
                $query->whereDate('end_date', '<=', $request->input('to'));
            }

            $statusCounts = (clone $query)
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $typeCounts = (clone $query)
                ->select('target_type', DB::raw('COUNT(*) as count'))
                ->groupBy('target_type')
                ->pluck('count', 'target_type')
                ->toArray();

            $total = array_sum($statusCounts);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الإحصائيات بنجاح',
                'data' => [
                    'total' => $total,
                    'by_status' => [
                        'new' => $statusCounts['new'] ?? 0,
                        'new_percent' => $total > 0 ? round((($statusCounts['new'] ?? 0) / $total) * 100, 1) : 0,
                        'in_progress' => $statusCounts['in_progress'] ?? 0,
                        'in_progress_percent' => $total > 0 ? round((($statusCounts['in_progress'] ?? 0) / $total) * 100, 1) : 0,
                        'completed' => $statusCounts['completed'] ?? 0,
                        'completed_percent' => $total > 0 ? round((($statusCounts['completed'] ?? 0) / $total) * 100, 1) : 0,
                    ],
                    'by_type' => [
                        'reservation' => $typeCounts['reservation'] ?? 0,
                        'reservation_percent' => $total > 0 ? round((($typeCounts['reservation'] ?? 0) / $total) * 100, 1) : 0,
                        'negotiation' => $typeCounts['negotiation'] ?? 0,
                        'negotiation_percent' => $total > 0 ? round((($typeCounts['negotiation'] ?? 0) / $total) * 100, 1) : 0,
                        'closing' => $typeCounts['closing'] ?? 0,
                        'closing_percent' => $total > 0 ? round((($typeCounts['closing'] ?? 0) / $total) * 100, 1) : 0,
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function marketers(Request $request): JsonResponse
    {
        try {
            $query = SalesTarget::query();

            if ($request->filled('from')) {
                $query->whereDate('start_date', '>=', $request->input('from'));
            }

            if ($request->filled('to')) {
                $query->whereDate('end_date', '<=', $request->input('to'));
            }

            $marketerStats = $query
                ->select(
                    'marketer_id',
                    DB::raw('COUNT(*) as total_targets'),
                    DB::raw("SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count"),
                    DB::raw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count"),
                    DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
                )
                ->groupBy('marketer_id')
                ->get();

            $marketerIds = $marketerStats->pluck('marketer_id')->unique();
            $users = User::whereIn('id', $marketerIds)->get()->keyBy('id');

            $data = $marketerStats->map(function ($stat) use ($users) {
                $user = $users->get($stat->marketer_id);
                $total = (int) $stat->total_targets;
                $completed = (int) $stat->completed_count;

                return [
                    'marketer_id' => $stat->marketer_id,
                    'marketer_name' => $user?->name ?? 'غير معروف',
                    'marketer_email' => $user?->email ?? 'غير معروف',
                    'team_name' => $user?->team?->name ?? null,
                    'total_targets' => $total,
                    'new' => (int) $stat->new_count,
                    'in_progress' => (int) $stat->in_progress_count,
                    'completed' => $completed,
                    'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                ];
            })->sortByDesc('total_targets')->values();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات المسوقين بنجاح',
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function reservationStatistics(Request $request, int $marketerId): JsonResponse
    {
        try {
            $query = SalesReservation::query()->where('marketing_employee_id', $marketerId);

            if ($request->filled('contract_id')) {
                $query->where('contract_id', (int) $request->input('contract_id'));
            }

            if ($request->filled('from')) {
                $query->whereDate('created_at', '>=', $request->input('from'));
            }

            if ($request->filled('to')) {
                $query->whereDate('created_at', '<=', $request->input('to'));
            }

            $marketerStats = (clone $query)
                ->select(
                    'marketing_employee_id',
                    DB::raw('COUNT(*) as total_reservations'),
                    DB::raw("SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count"),
                    DB::raw("SUM(CASE WHEN status = 'under_negotiation' THEN 1 ELSE 0 END) as under_negotiation_count"),
                    DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
                )
                ->groupBy('marketing_employee_id')
                ->get();

            $marketerIds = $marketerStats->pluck('marketing_employee_id')->unique();
            $users = User::whereIn('id', $marketerIds)->get()->keyBy('id');

            $unitStatusByMarketer = SalesReservation::query()
                ->join('contract_units', 'sales_reservations.contract_unit_id', '=', 'contract_units.id')
                ->whereIn('sales_reservations.marketing_employee_id', $marketerIds)
                ->when($request->filled('contract_id'), fn ($q) => $q->where('sales_reservations.contract_id', (int) $request->input('contract_id')))
                ->when($request->filled('from'), fn ($q) => $q->whereDate('sales_reservations.created_at', '>=', $request->input('from')))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('sales_reservations.created_at', '<=', $request->input('to')))
                ->select(
                    'sales_reservations.marketing_employee_id',
                    'contract_units.status as unit_status',
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('sales_reservations.marketing_employee_id', 'contract_units.status')
                ->get()
                ->groupBy('marketing_employee_id');

            $marketersData = $marketerStats->map(function ($stat) use ($users, $unitStatusByMarketer) {
                $user = $users->get($stat->marketing_employee_id);
                $total = (int) $stat->total_reservations;
                $confirmed = (int) $stat->confirmed_count;
                $underNegotiation = (int) $stat->under_negotiation_count;
                $cancelled = (int) $stat->cancelled_count;

                $unitStats = $unitStatusByMarketer->get($stat->marketing_employee_id, collect());
                $unitStatusMap = $unitStats->pluck('count', 'unit_status')->toArray();
                $unitTotal = array_sum($unitStatusMap);

                return [
                    'marketer_id' => $stat->marketing_employee_id,
                    'marketer_name' => $user?->name ?? 'غير معروف',
                    'team_name' => $user?->team?->name ?? null,
                    'total_reservations' => $total,
                    'confirmed' => $confirmed,
                    'confirmed_percent' => $total > 0 ? round(($confirmed / $total) * 100, 1) : 0,
                    'under_negotiation' => $underNegotiation,
                    'under_negotiation_percent' => $total > 0 ? round(($underNegotiation / $total) * 100, 1) : 0,
                    'cancelled' => $cancelled,
                    'cancelled_percent' => $total > 0 ? round(($cancelled / $total) * 100, 1) : 0,
                    'unit_status' => [
                        'available' => (int) ($unitStatusMap['available'] ?? 0),
                        'available_percent' => $unitTotal > 0 ? round((($unitStatusMap['available'] ?? 0) / $unitTotal) * 100, 1) : 0,
                        'reserved' => (int) ($unitStatusMap['reserved'] ?? 0),
                        'reserved_percent' => $unitTotal > 0 ? round((($unitStatusMap['reserved'] ?? 0) / $unitTotal) * 100, 1) : 0,
                        'sold' => (int) ($unitStatusMap['sold'] ?? 0),
                        'sold_percent' => $unitTotal > 0 ? round((($unitStatusMap['sold'] ?? 0) / $unitTotal) * 100, 1) : 0,
                        'pending' => (int) ($unitStatusMap['pending'] ?? 0),
                        'pending_percent' => $unitTotal > 0 ? round((($unitStatusMap['pending'] ?? 0) / $unitTotal) * 100, 1) : 0,
                        'under_negotiation' => (int) ($unitStatusMap['under_negotiation'] ?? 0),
                        'under_negotiation_percent' => $unitTotal > 0 ? round((($unitStatusMap['under_negotiation'] ?? 0) / $unitTotal) * 100, 1) : 0,
                    ],
                ];
            })->sortByDesc('total_reservations')->values();

            $overallTotal = $marketerStats->sum('total_reservations');
            $overallConfirmed = $marketerStats->sum('confirmed_count');
            $overallUnderNegotiation = $marketerStats->sum('under_negotiation_count');
            $overallCancelled = $marketerStats->sum('cancelled_count');

            $overallUnitStatus = SalesReservation::query()
                ->join('contract_units', 'sales_reservations.contract_unit_id', '=', 'contract_units.id')
                ->where('sales_reservations.marketing_employee_id', $marketerId)
                ->when($request->filled('contract_id'), fn ($q) => $q->where('sales_reservations.contract_id', (int) $request->input('contract_id')))
                ->when($request->filled('from'), fn ($q) => $q->whereDate('sales_reservations.created_at', '>=', $request->input('from')))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('sales_reservations.created_at', '<=', $request->input('to')))
                ->select('contract_units.status as unit_status', DB::raw('COUNT(*) as count'))
                ->groupBy('contract_units.status')
                ->pluck('count', 'unit_status')
                ->toArray();

            $overallUnitTotal = array_sum($overallUnitStatus);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات الحجوزات بنجاح',
                'data' => [
                    'overall' => [
                        'total' => (int) $overallTotal,
                        'confirmed' => (int) $overallConfirmed,
                        'confirmed_percent' => $overallTotal > 0 ? round(($overallConfirmed / $overallTotal) * 100, 1) : 0,
                        'under_negotiation' => (int) $overallUnderNegotiation,
                        'under_negotiation_percent' => $overallTotal > 0 ? round(($overallUnderNegotiation / $overallTotal) * 100, 1) : 0,
                        'cancelled' => (int) $overallCancelled,
                        'cancelled_percent' => $overallTotal > 0 ? round(($overallCancelled / $overallTotal) * 100, 1) : 0,
                        'unit_status' => [
                            'available' => (int) ($overallUnitStatus['available'] ?? 0),
                            'available_percent' => $overallUnitTotal > 0 ? round((($overallUnitStatus['available'] ?? 0) / $overallUnitTotal) * 100, 1) : 0,
                            'reserved' => (int) ($overallUnitStatus['reserved'] ?? 0),
                            'reserved_percent' => $overallUnitTotal > 0 ? round((($overallUnitStatus['reserved'] ?? 0) / $overallUnitTotal) * 100, 1) : 0,
                            'sold' => (int) ($overallUnitStatus['sold'] ?? 0),
                            'sold_percent' => $overallUnitTotal > 0 ? round((($overallUnitStatus['sold'] ?? 0) / $overallUnitTotal) * 100, 1) : 0,
                            'pending' => (int) ($overallUnitStatus['pending'] ?? 0),
                            'pending_percent' => $overallUnitTotal > 0 ? round((($overallUnitStatus['pending'] ?? 0) / $overallUnitTotal) * 100, 1) : 0,
                            'under_negotiation' => (int) ($overallUnitStatus['under_negotiation'] ?? 0),
                            'under_negotiation_percent' => $overallUnitTotal > 0 ? round((($overallUnitStatus['under_negotiation'] ?? 0) / $overallUnitTotal) * 100, 1) : 0,
                        ],
                    ],
                    'marketers' => $marketersData,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
