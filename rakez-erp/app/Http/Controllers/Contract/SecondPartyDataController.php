<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Concerns\RespondsWithCsvImportUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreSecondPartyDataRequest;
use App\Http\Requests\Contract\UpdateSecondPartyDataRequest;
use App\Http\Requests\Contract\ImportSecondPartyDataCsv;
use App\Http\Resources\Contract\SecondPartyDataResource;
use App\Jobs\ProcessSecondPartyDataCsv;
use App\Models\Contract;
use App\Models\CsvImport;
use App\Services\Contract\SecondPartyDataService;
use App\Services\Pdf\ContractPdfDataService;
use App\Services\Pdf\PdfFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Mpdf\MpdfException;
use Exception;

class SecondPartyDataController extends Controller
{
    use RespondsWithCsvImportUpload;

    protected SecondPartyDataService $secondPartyDataService;

    public function __construct(
        SecondPartyDataService $secondPartyDataService,
        protected ContractPdfDataService $contractPdfDataService
    ) {
        $this->secondPartyDataService = $secondPartyDataService;
    }


    public function downloadPdf(int $contractId): Response|JsonResponse
    {
        try {
            $contract = Contract::findOrFail($contractId);
            $this->authorize('view', $contract);

            $secondPartyData = $this->secondPartyDataService->getByContractId($contractId);
            if (!$secondPartyData) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات الطرف الثاني غير موجودة لهذا العقد',
                ], 404);
            }

            $data = $this->contractPdfDataService->buildSecondPartyDataOnlyPdfPayload($secondPartyData);
            $filename = sprintf('second_party_data_%d_%s.pdf', $secondPartyData->id, now()->format('Y-m-d'));

            return PdfFactory::download('pdfs.second_party_data_only', $data, $filename);
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
            $notFound = str_contains($message, 'No query results') || str_contains($message, 'not found');

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $notFound ? 404 : 500);
        }
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

        return $this->runCsvImport(
            $csvImport,
            fn () => ProcessSecondPartyDataCsv::dispatchSync($csvImport->id, Auth::id(), $contractId),
            fn () => ProcessSecondPartyDataCsv::dispatch($csvImport->id, Auth::id(), $contractId)
        );
    }
}

