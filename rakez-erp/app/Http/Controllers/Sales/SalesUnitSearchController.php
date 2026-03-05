<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SearchUnitsRequest;
use App\Http\Resources\Sales\SalesUnitSearchResource;
use App\Services\Sales\SalesUnitSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesUnitSearchController extends Controller
{
    public function __construct(
        private SalesUnitSearchService $searchService
    ) {}

    public function search(SearchUnitsRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $units = $this->searchService->search($filters, $request->user());

            return response()->json([
                'success' => true,
                'data'    => SalesUnitSearchResource::collection($units->items()),
                'meta'    => [
                    'current_page' => $units->currentPage(),
                    'last_page'    => $units->lastPage(),
                    'per_page'     => $units->perPage(),
                    'total'        => $units->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في البحث عن الوحدات: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $data = $this->searchService->getAvailableFilters($request->user());

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب قيم الفلاتر: ' . $e->getMessage(),
            ], 500);
        }
    }
}
