<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractInfoRequest;
use App\Http\Requests\Contract\UpdateContractInfoRequest;
use App\Http\Requests\Contract\ImportContractInfoCsv;
use App\Http\Resources\Contract\ContractResource;
use App\Http\Resources\Contract\ContractInfoResource;
use App\Jobs\ProcessContractInfoCsv;
use App\Models\ContractInfo;
use App\Models\Contract;
use App\Models\CsvImport;
use App\Services\Contract\ContractService;
use App\Services\Pdf\ContractPdfDataService;
use App\Services\Pdf\PdfFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Mpdf\MpdfException;
use Exception;

class ContractInfoController extends Controller
{
    protected ContractService $contractService;

    public function __construct(
        ContractService $contractService,
        protected ContractPdfDataService $contractPdfDataService
    ) {
        $this->contractService = $contractService;
    }

    /**
     * PDF: contract_infos only (معلومات العقد فقط، عربي).
     * GET /api/contracts/info/{contractId}/pdf
     */
    public function downloadPdf(int $contractId): Response|JsonResponse
    {
        try {
            $contract = $this->contractService->getContractById($contractId, null);
            $this->authorize('view', $contract);

            $info = ContractInfo::query()->where('contract_id', $contractId)->first();
            if (!$info) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد بيانات معلومات العقد لهذا العقد',
                ], 404);
            }

            $data = $this->contractPdfDataService->buildContractInfoOnlyPdfPayload($info);
            $filename = sprintf('contract_info_%d_%s.pdf', $info->id, now()->format('Y-m-d'));

            return PdfFactory::download('pdfs.contract_info_only', $data, $filename);
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
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
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
     * Store contract info for a contract
     */
    public function store(StoreContractInfoRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            // Check permission: only owner, admin, project_management can store contract info
            $contract = $this->contractService->getContractById($contractId, auth()->id(), forContractInfo: true);

            // Prevent creating a new ContractInfo if one already exists
            if ($contract->info) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات العقد موجودة بالفعل ولا يمكن إنشاؤها مرة أخرى',
                ], 422);
            }

            // Only allow storing info if contract status is approved
            if ($contract->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'يمكن فقط حفظ بيانات العقد عندما تكون حالته موافق عليها',
                ], 422);
            }

            $info = $this->contractService->storeContractInfo($contractId, $data, $contract);

            // Change contract status to complete
            $contract->update(['status' => 'completed']);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ بيانات العقد',
                'data' => new ContractInfoResource($info->load('contract.user'))
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update contract info for a contract
     */
    public function update(UpdateContractInfoRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            // Check permission: only owner, admin, project_management can update contract info
            $contract = $this->contractService->getContractById($contractId, auth()->id(), forContractInfo: true);

            $info = $this->contractService->updateContractInfo($contractId, $data, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات العقد',
                'data' => new ContractInfoResource($info->load('contract.user'))
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all unique second parties (no duplicates by email)
     * جلب جميع الأطراف الثانية بدون تكرار حسب الإيميل
     */
    public function getAllSecondParties(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);

            // Get unique second parties by email (take latest info for each email)
            $secondParties = ContractInfo::whereNotNull('second_party_email')
                ->where('second_party_email', '!=', '')
                ->selectRaw('
                    second_party_email,
                    MAX(second_party_name) as second_party_name,
                    MAX(second_party_phone) as second_party_phone,
                    MAX(second_party_address) as second_party_address,
                    MAX(second_party_cr_number) as second_party_cr_number,
                    MAX(second_party_signatory) as second_party_signatory,
                    MAX(second_party_id_number) as second_party_id_number,
                    MAX(second_party_role) as second_party_role,
                    COUNT(*) as contracts_count
                ')
                ->groupBy('second_party_email')
                ->orderBy('second_party_name')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الأطراف الثانية بنجاح',
                'data' => $secondParties->items(),
                'meta' => [
                    'total' => $secondParties->total(),
                    'count' => $secondParties->count(),
                    'per_page' => $secondParties->perPage(),
                    'current_page' => $secondParties->currentPage(),
                    'last_page' => $secondParties->lastPage(),
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all contracts by second party email
     * جلب جميع العقود حسب إيميل الطرف الثاني
     */
    public function getContractsBySecondPartyEmail(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $email = $request->input('email');
            $perPage = $request->input('per_page', 15);

            $contracts = Contract::whereHas('info', function ($query) use ($email) {
                $query->where('second_party_email', $email);
            })
            ->with(['user', 'info'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقود بنجاح',
                'data' => ContractResource::collection($contracts),
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

    /**
     * Upload CSV with contract info fields for a specific contract (ID from URL).
     * CSV should have exactly one data row with the info columns.
     */
    public function import_csv(ImportContractInfoCsv $request, int $contractId): JsonResponse
    {
        $contract = Contract::with('info')->find($contractId);

        if (!$contract) {
            return response()->json(['success' => false, 'message' => 'العقد غير موجود'], 404);
        }

        if ($contract->info) {
            return response()->json(['success' => false, 'message' => 'بيانات العقد موجودة بالفعل ولا يمكن إنشاؤها مرة أخرى'], 422);
        }

        if ($contract->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'يمكن فقط حفظ بيانات العقد عندما تكون حالته موافق عليها'], 422);
        }

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

        $storedPath = $file->store('csv-imports', 'local');

        $csvImport = CsvImport::create([
            'type' => CsvImport::TYPE_CONTRACT_INFO,
            'uploaded_by' => Auth::id(),
            'file_path' => $storedPath,
            'status' => CsvImport::STATUS_PENDING,
        ]);

        ProcessContractInfoCsv::dispatch($csvImport->id, Auth::id(), $contractId);

        return response()->json([
            'success' => true,
            'message' => 'CSV uploaded successfully. Import is being processed.',
            'import_id' => $csvImport->id,
            'status' => $csvImport->status,
        ], 202);
    }

    /**
     * Poll the status of a CSV contract info import.
     */
    public function import_csv_status(int $id): JsonResponse
    {
        $csvImport = CsvImport::where('id', $id)
            ->where('uploaded_by', Auth::id())
            ->where('type', CsvImport::TYPE_CONTRACT_INFO)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'import_id' => $csvImport->id,
            'status' => $csvImport->status,
            'total_rows' => $csvImport->total_rows,
            'processed_rows' => $csvImport->processed_rows,
            'successful_rows' => $csvImport->successful_rows,
            'failed_rows' => $csvImport->failed_rows,
            'row_errors' => $csvImport->row_errors,
            'error_message' => $csvImport->error_message,
            'completed_at' => $csvImport->completed_at,
        ]);
    }
}
