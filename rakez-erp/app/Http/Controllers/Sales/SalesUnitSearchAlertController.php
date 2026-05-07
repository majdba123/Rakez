<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesUnitSearchAlertRequest;
use App\Http\Requests\Sales\UpdateSalesUnitSearchAlertRequest;
use App\Http\Resources\Sales\SalesUnitSearchAlertResource;
use App\Models\SalesUnitSearchAlert;
use App\Services\Sales\UnitSearchCriteria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SalesUnitSearchAlertController extends Controller
{
    public function __construct(
        private UnitSearchCriteria $criteria
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = SalesUnitSearchAlert::query()
            ->with(['lastMatchedUnit', 'deliveries'])
            ->latest();

        if (! $this->canAccessAll($request->user())) {
            $query->where('sales_staff_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $alerts = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => SalesUnitSearchAlertResource::collection($alerts->items()),
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
            ],
        ]);
    }

    public function store(StoreSalesUnitSearchAlertRequest $request): JsonResponse
    {
        $data = $this->payload($request->validated());
        $data['sales_staff_id'] = $request->user()->id;
        $data['status'] = $data['status'] ?? SalesUnitSearchAlert::STATUS_ACTIVE;
        $data['expires_at'] = $data['expires_at']
            ?? now()->addDays((int) config('sales.unit_search_alerts.default_expiration_days', 30));

        $alert = SalesUnitSearchAlert::create($data)->fresh(['lastMatchedUnit', 'deliveries']);

        return response()->json([
            'success' => true,
            'message' => 'Sales unit search alert created successfully',
            'data' => new SalesUnitSearchAlertResource($alert),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, SalesUnitSearchAlert $alert): JsonResponse
    {
        $this->authorizeAlert($request, $alert);

        return response()->json([
            'success' => true,
            'data' => new SalesUnitSearchAlertResource($alert->load(['lastMatchedUnit', 'deliveries'])),
        ]);
    }

    public function update(UpdateSalesUnitSearchAlertRequest $request, SalesUnitSearchAlert $alert): JsonResponse
    {
        $this->authorizeAlert($request, $alert);

        $alert->update($this->payload($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Sales unit search alert updated successfully',
            'data' => new SalesUnitSearchAlertResource($alert->fresh(['lastMatchedUnit', 'deliveries'])),
        ]);
    }

    public function destroy(Request $request, SalesUnitSearchAlert $alert): JsonResponse
    {
        $this->authorizeAlert($request, $alert);

        $alert->update(['status' => SalesUnitSearchAlert::STATUS_CANCELLED]);
        $alert->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sales unit search alert cancelled successfully',
        ]);
    }

    private function payload(array $validated): array
    {
        $clientKeys = [
            'client_name',
            'client_mobile',
            'client_email',
            'client_sms_opt_in',
            'client_sms_opted_in_at',
            'client_sms_locale',
            'status',
            'expires_at',
        ];

        $payload = array_intersect_key($validated, array_flip($clientKeys));

        $criteria = $this->criteria->normalizeForPersistence($validated);

        foreach ($this->criteriaKeys() as $key) {
            if (array_key_exists($key, $validated) && ($validated[$key] === null || $validated[$key] === '')) {
                $criteria[$key] = null;
            }
        }

        return array_merge($payload, $criteria);
    }

    private function authorizeAlert(Request $request, SalesUnitSearchAlert $alert): void
    {
        if ($this->canAccessAll($request->user()) || $alert->sales_staff_id === $request->user()->id) {
            return;
        }

        throw new AuthorizationException('You are not authorized to access this alert.');
    }

    private function canAccessAll($user): bool
    {
        return $user->hasRole('admin') || $user->can('sales.search_alerts.manage');
    }

    private function criteriaKeys(): array
    {
        return [
            'city_id',
            'district_id',
            'project_id',
            'unit_type',
            'floor',
            'min_price',
            'max_price',
            'min_area',
            'max_area',
            'min_bedrooms',
            'max_bedrooms',
            'query_text',
        ];
    }
}
