<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreSecondPartyDataRequest;
use App\Http\Requests\Contract\UpdateSecondPartyDataRequest;
use App\Http\Requests\Contract\ImportSecondPartyDataCsv;
use App\Http\Resources\Contract\SecondPartyDataResource;
use App\Jobs\ProcessSecondPartyDataCsv;
use App\Models\Contract;
use App\Models\CsvImport;
use App\Services\Contract\SecondPartyDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Exception;

class SecondPartyDataController extends Controller
{
    protected SecondPartyDataService $secondPartyDataService;

    public function __construct(SecondPartyDataService $secondPartyDataService)
    {
        $this->secondPartyDataService = $secondPartyDataService;
    }


    public function store(StoreSecondPartyDataRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            $secondPartyData = $this->secondPartyDataService->store($contractId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ بيانات الطرف الثاني بنجاح',
                'data' => new SecondPartyDataResource($secondPartyData),
            ], 201);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'موجودة بالفعل') ? 422 : 500;
            $statusCode = str_contains($e->getMessage(), 'غير مصرح') ? 403 : $statusCode;
            $statusCode = str_contains($e->getMessage(), 'يجب أن يكون') ? 422 : $statusCode;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


    public function update(UpdateSecondPartyDataRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            $secondPartyData = $this->secondPartyDataService->updateByContractId($contractId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات الطرف الثاني بنجاح',
                'data' => new SecondPartyDataResource($secondPartyData),
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'غير موجودة') ? 404 : 500;
            $statusCode = str_contains($e->getMessage(), 'غير مصرح') ? 403 : $statusCode;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


    public function show(int $contractId): JsonResponse
    {
        try {
            $contract = \App\Models\Contract::findOrFail($contractId);
            $this->authorize('view', $contract);

            $secondPartyData = $this->secondPartyDataService->getByContractId($contractId);

            if (!$secondPartyData) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات الطرف الثاني غير موجودة لهذا العقد',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new SecondPartyDataResource($secondPartyData),
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'غير مصرح') ? 403 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Upload CSV with second party data for a specific contract (ID from URL).
     */
    public function import_csv(ImportSecondPartyDataCsv $request, int $contractId): JsonResponse
    {
        $contract = Contract::with('secondPartyData', 'info')->find($contractId);

        if (!$contract) {
            return response()->json(['success' => false, 'message' => 'العقد غير موجود'], 404);
        }

        if ($contract->secondPartyData) {
            return response()->json(['success' => false, 'message' => 'بيانات الطرف الثاني موجودة بالفعل لهذا العقد'], 422);
        }

        if (!$contract->info) {
            return response()->json(['success' => false, 'message' => 'يجب أن يكون العقد لديه معلومات عليه قبل إضافة بيانات الطرف الثاني'], 422);
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
            'type' => CsvImport::TYPE_SECOND_PARTY_DATA,
            'uploaded_by' => Auth::id(),
            'file_path' => $storedPath,
            'status' => CsvImport::STATUS_PENDING,
        ]);

        ProcessSecondPartyDataCsv::dispatch($csvImport->id, Auth::id(), $contractId);

        return response()->json([
            'success' => true,
            'message' => 'CSV uploaded successfully. Import is being processed.',
            'import_id' => $csvImport->id,
            'status' => $csvImport->status,
        ], 202);
    }

    /**
     * Poll the status of a CSV second party data import.
     */
    public function import_csv_status(int $id): JsonResponse
    {
        $csvImport = CsvImport::where('id', $id)
            ->where('uploaded_by', Auth::id())
            ->where('type', CsvImport::TYPE_SECOND_PARTY_DATA)
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

