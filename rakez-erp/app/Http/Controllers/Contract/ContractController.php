<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractRequest;
use App\Http\Requests\Contract\UpdateContractRequest;
use App\Http\Requests\Contract\UpdateContractStatusRequest;
use App\Http\Requests\Contract\ImportContractsCsv;
use App\Http\Resources\Contract\ContractResource;
use App\Http\Resources\Contract\ContractIndexResource;
use App\Jobs\ProcessContractsCsv;
use App\Models\Contract;
use App\Models\CsvImport;
use App\Services\Contract\ContractService;
use App\Services\Contract\InventoryAgencyOverviewService;
use App\Services\Contract\InventoryDashboardService;
use App\Services\Pdf\ContractPdfDataService;
use App\Services\Pdf\PdfFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Mpdf\MpdfException;
use Exception;

class ContractController extends Controller
{
    public function __construct(
        protected ContractService $contractService,
        protected InventoryAgencyOverviewService $inventoryAgencyOverviewService,
        protected InventoryDashboardService $inventoryDashboardService,
        protected ContractPdfDataService $pdfDataService
    ) {
    }


    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح - يرجى تسجيل الدخول',
                ], 401);
            }
            $filters = [
                'status' => $request->input('status'),
                'city_id' => $request->input('city_id'),
                'district_id' => $request->input('district_id'),
                'project_name' => $request->input('project_name'),
                'has_photography' => $request->input('has_photography'),
                'has_montage' => $request->input('has_montage'),
            ];

            // Apply access control filters: all users see own contracts + contracts with status approved/completed
            if ($user->can('contracts.view_all')) {
                // Can view all; allow optional user_id filter from request
                if ($request->filled('user_id')) {
                    $filters['user_id'] = (int) $request->input('user_id');
                }
            } elseif ($user->isManager() && $user->team_id) {
                $filters['user_id'] = $user->id;
                $filters['include_public_status_contracts'] = true;
            } else {
                $filters['user_id'] = $user->id;
                $filters['include_public_status_contracts'] = true;
            }

            $perPage = $request->input('per_page', 15);

            $contracts = $this->contractService->getContracts($filters, (int) $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقود بنجاح',
                'data' => ContractIndexResource::collection($contracts->items()),
                'meta' => [
                    'total' => $contracts->total(),
                    'count' => $contracts->count(),
                    'per_page' => $contracts->perPage(),
                    'current_page' => $contracts->currentPage(),
                    'last_page' => $contracts->lastPage(),
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
       // $this->authorize('create', Contract::class);

        try {
            $validated = $request->validated();

            $contract = $this->contractService->storeContract($validated);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء العقد بنجاح وحالته قيد الانتظار',
                'data' => new ContractResource($contract->load('user', 'info'))
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Inventory dashboard: marketing projects count, units stats, pending and closed contracts KPIs.
     * Query params: include_pending_count, include_closed_count (default true).
     */
    public function inventoryDashboard(Request $request): JsonResponse
    {
        try {
            $includePendingCount = $request->boolean('include_pending_count', true);
            $includeClosedCount = $request->boolean('include_closed_count', true);

            $data = $this->inventoryDashboardService->getDashboardData([
                'include_pending_count' => $includePendingCount,
                'include_closed_count' => $includeClosedCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات لوحة التحكم للمخزون بنجاح',
                'data' => $data,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            // Fetch contract without service-level auth check
            $contract = $this->contractService->getContractById($id, null);

            $this->authorize('view', $contract);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقد بنجاح',
                'data' => new ContractResource($contract)
            ], 200);
        } catch (Exception $e) {
            $statusCode = 404;
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                $statusCode = 403;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Download contract details as PDF (عربي — mPDF RTL).
     * GET /api/contracts/show/{id}/pdf
     */
    public function showPdf(int $id): Response|JsonResponse
    {
        try {
            $contract = $this->contractService->getContractById($id, null);
            $this->authorize('view', $contract);

            $data = $this->pdfDataService->buildShowPdfPayload($contract);
            $filename = sprintf('contract_%d_%s.pdf', $contract->id, now()->format('Y-m-d'));

            return PdfFactory::download('pdfs.contract_show', $data, $filename);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (MpdfException $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر إنشاء ملف PDF: ' . $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $notFound = str_contains($message, 'not found') || str_contains($message, 'No query results');

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $notFound ? 404 : 500);
        }
    }

    /**
     * Contract fill data for PDF template (عقد حصري — ملء قالب).
     * GET /api/contracts/{id}/fill-data — returns JSON only; frontend uses downloadFilledContract(contractData).
     */
    public function fillData(int $id): JsonResponse
    {
        try {
            $contract = $this->contractService->getContractById($id, null);
            $this->authorize('view', $contract);
            $data = $this->pdfDataService->getFillData($contract);
            return response()->json($data, 200, ['Content-Type' => 'application/json']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getMessage() === 'Contract not found' ? 404 : 500);
        }
    }

    /**
     * PDF for contract fill-data (عقد حصري — ملء قالب). Same underlying data as {@see fillData}.
     * GET /api/contracts/{id}/fill-data/pdf
     */
    public function fillDataPdf(int $id): Response|JsonResponse
    {
        try {
            $contract = $this->contractService->getContractById($id, null);
            $this->authorize('view', $contract);

            $data = $this->pdfDataService->buildFillDataPdfPayload($contract);
            $filename = sprintf('contract_fill_%d_%s.pdf', $contract->id, now()->format('Y-m-d'));

            return PdfFactory::download('pdfs.contract_fill_data', $data, $filename);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (MpdfException $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر إنشاء ملف PDF: ' . $e->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $notFound = str_contains($message, 'not found') || str_contains($message, 'No query results')
                || str_contains($message, 'Contract not found');

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $notFound ? 404 : 500);
        }
    }

    /**
     * Contract summary for PDF (ملخص عقد — إدارة مشاريع).
     * GET /api/contracts/{id}/summary-pdf-data — returns JSON only; frontend uses generateContractSummaryPdf(contract).
     */
    public function summaryPdfData(int $id): JsonResponse
    {
        try {
            $contract = $this->contractService->getContractById($id, null);
            $this->authorize('view', $contract);
            $data = [
                'project_name' => (string) ($contract->project_name ?? ''),
                'developer_name' => (string) ($contract->developer_name ?? ''),
                'city' => (string) ($contract->city?->name ?? ''),
                'district' => (string) ($contract->district?->name ?? ''),
                'side' => $contract->side,
                'contract_type' => (string) ($contract->contract_type ?? ''),
                'status' => (string) ($contract->status ?? ''),
                'notes' => (string) ($contract->notes ?? ''),
                'created_at' => $contract->created_at?->toIso8601String() ?? '',
            ];
            return response()->json($data, 200, ['Content-Type' => 'application/json']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getMessage() === 'Contract not found' ? 404 : 500);
        }
    }

    public function update(UpdateContractRequest $request, int $id): JsonResponse
    {
        try {
            // Fetch contract to authorize
            $contract = $this->contractService->getContractById($id, null);

            $this->authorize('update', $contract);

            $validated = $request->validated();

            // Pass null for userId to skip service auth check since we already authorized
            $contract = $this->contractService->updateContract($id, $validated, null);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث العقد بنجاح',
                'data' => new ContractResource($contract->load('user', 'info'))
            ], 200);
        } catch (Exception $e) {
            $statusCode = 422;
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                $statusCode = 403;
            } elseif ($e->getMessage() === 'Contract not found') {
                $statusCode = 404;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


    public function destroy(int $id): JsonResponse
    {
        try {
            $contract = $this->contractService->getContractById($id, null);

            $this->authorize('delete', $contract);

            $this->contractService->deleteContract($id, null);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف العقد بنجاح'
            ], 200);
        } catch (Exception $e) {
            $statusCode = 422;
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                $statusCode = 403;
            } elseif ($e->getMessage() === 'Contract not found') {
                $statusCode = 404;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function adminIndex(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->input('status'),
                'user_id' => $request->input('user_id'),
                'city_id' => $request->input('city_id'),
                'district_id' => $request->input('district_id'),
                'project_name' => $request->input('project_name'),
                'has_photography' => $request->input('has_photography'),
                'has_montage' => $request->input('has_montage'),
            ];

            $perPage = $request->input('per_page', 15);

            $contracts = $this->contractService->getContractsForAdmin($filters, (int) $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقود بنجاح',
                'data' => ContractIndexResource::collection($contracts->items()),
                'meta' => [
                    'total' => $contracts->total(),
                    'count' => $contracts->count(),
                    'per_page' => $contracts->perPage(),
                    'current_page' => $contracts->currentPage(),
                    'last_page' => $contracts->lastPage(),
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function locations(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->input('status'),
                'user_id' => $request->input('user_id'),
                'city_id' => $request->input('city_id'),
                'district_id' => $request->input('district_id'),
                'project_name' => $request->input('project_name'),
                'has_photography' => $request->input('has_photography'),
                'has_montage' => $request->input('has_montage'),
            ];

            $perPage = (int) $request->input('per_page', 200);
            $perPage = max(1, min($perPage, 500));

            $rows = $this->contractService->getContractLocationsForAdmin($filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب المواقع بنجاح',
                'data' => collect($rows->items())->map(function ($row) {
                    return [
                        'contract_id' => (int) $row->contract_id,
                        'project_name' => $row->project_name,
                        'status' => $row->status,
                        'location_url' => $row->location_url,
                    ];
                }),
                'meta' => [
                    'total' => $rows->total(),
                    'count' => $rows->count(),
                    'per_page' => $rows->perPage(),
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
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
     * Inventory/Admin: contracts list for inventory dashboard.
     * Delegates to InventoryAgencyOverviewService for data and meta.
     */
    public function inventoryAgencyOverview(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->input('status'),
                'user_id' => $request->input('user_id'),
                'city_id' => $request->input('city_id'),
                'district_id' => $request->input('district_id'),
                'project_name' => $request->input('project_name'),
                'has_photography' => $request->input('has_photography'),
                'has_montage' => $request->input('has_montage'),
            ];

            $perPage = (int) $request->input('per_page', 50);
            $perPage = max(1, min($perPage, 200));

            $result = $this->inventoryAgencyOverviewService->getOverviewData($filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب بيانات المخزون بنجاح',
                'data' => $result['data'],
                'meta' => $result['meta'],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function adminUpdateStatus(UpdateContractStatusRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            $contract = $this->contractService->updateContractStatus($id, $validated['status']);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة العقد بنجاح',
                'data' => new ContractResource($contract->load('user', 'info'))
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getMessage() === 'Contract not found' ? 404 : 422);
        }
    }

    public function projectManagementUpdateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:ready,rejected',
            ], [
                'status.required' => 'الحالة مطلوبة',
                'status.in' => 'الحالة يجب أن تكون: جاهز أو مرفوض',
            ]);

            $contract = $this->contractService->updateContractStatusByProjectManagement($id, $request->status);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة العقد بنجاح',
                'data' => new ContractResource($contract->load('user', 'info', 'secondPartyData'))
            ], 200);
        } catch (Exception $e) {
            $statusCode = 422;
            if (str_contains($e->getMessage(), 'غير موجود')) $statusCode = 404;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get teams assigned to a contract.
     * GET /hr/teams/getTeamsForContract/{contractId}
     */
    public function getTeamsForContract(int $contractId): JsonResponse
    {
        try {
            $teams = $this->contractService->getContractTeams($contractId);

            return response()->json([
                'success' => true,
                'data' => $teams,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], str_contains($e->getMessage(), 'No query results') || str_contains($e->getMessage(), 'Contract not found') ? 404 : 500);
        }
    }

    /**
     * Get teams assigned to a contract (project_management context).
     * GET /project_management/teams/index/{contractId}
     */
    public function getTeamsForContract_HR(int $contractId): JsonResponse
    {
        return $this->getTeamsForContract($contractId);
    }

    /**
     * Add teams to a contract.
     * POST /project_management/teams/add/{contractId}
     * Body: { "team_ids": [1, 2, 3] }
     */
    public function addTeamsToContract(Request $request, int $contractId): JsonResponse
    {
        try {
            $request->validate([
                'team_ids' => 'required|array',
                'team_ids.*' => 'integer|exists:teams,id',
            ], [
                'team_ids.required' => 'team_ids مطلوب',
            ]);

            $contract = $this->contractService->attachTeamsToContract($contractId, $request->input('team_ids'));

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الفرق بنجاح',
                'data' => new ContractResource($contract->load('user', 'info', 'teams')),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], str_contains($e->getMessage(), 'No query results') || str_contains($e->getMessage(), 'Contract not found') ? 404 : 422);
        }
    }

    /**
     * Remove teams from a contract.
     * POST /project_management/teams/remove/{contractId}
     * Body: { "team_ids": [1, 2, 3] }
     */
    public function removeTeamsFromContract(Request $request, int $contractId): JsonResponse
    {
        try {
            $request->validate([
                'team_ids' => 'required|array',
                'team_ids.*' => 'integer|exists:teams,id',
            ], [
                'team_ids.required' => 'team_ids مطلوب',
            ]);

            $contract = $this->contractService->detachTeamsFromContract($contractId, $request->input('team_ids'));

            return response()->json([
                'success' => true,
                'message' => 'تم إزالة الفرق بنجاح',
                'data' => new ContractResource($contract->load('user', 'info', 'teams')),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], str_contains($e->getMessage(), 'No query results') || str_contains($e->getMessage(), 'Contract not found') ? 404 : 422);
        }
    }

    /**
     * Upload a CSV file for bulk contract import.
     * Validates file and header, then dispatches a queue job.
     */
    public function import_contracts_csv(ImportContractsCsv $request): JsonResponse
    {
        $file = $request->file('file');

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return response()->json(['success' => false, 'message' => 'Unable to read the CSV file.'], 422);
        }

        $header = fgetcsv($handle);
        fclose($handle);

        if (!$header) {
            return response()->json(['success' => false, 'message' => 'CSV file is empty or has no header row.'], 422);
        }

        $header = array_map(fn ($col) => strtolower(trim($col)), $header);

        $requiredColumns = [
            'developer_name', 'developer_number',
            'project_name', 'developer_requiment',
            'units_json',
        ];
        $missing = array_diff($requiredColumns, $header);

        if (!empty($missing)) {
            return response()->json([
                'success' => false,
                'message' => 'CSV is missing required columns.',
                'missing_columns' => array_values($missing),
            ], 422);
        }

        $hasIds = in_array('city_id', $header, true) && in_array('district_id', $header, true);
        $hasCode = in_array('city_code', $header, true) && in_array('district_name', $header, true);
        if (! $hasIds && ! $hasCode) {
            return response()->json([
                'success' => false,
                'message' => 'CSV must include either (city_id and district_id) or (city_code and district_name) columns.',
            ], 422);
        }

        $storedPath = $file->store('csv-imports', 'local');

        $csvImport = CsvImport::create([
            'type' => CsvImport::TYPE_CONTRACTS,
            'uploaded_by' => Auth::id(),
            'file_path' => $storedPath,
            'status' => CsvImport::STATUS_PENDING,
        ]);

        ProcessContractsCsv::dispatch($csvImport->id, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'CSV uploaded successfully. Import is being processed.',
            'import_id' => $csvImport->id,
            'status' => $csvImport->status,
        ], 202);
    }
}
