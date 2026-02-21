<?php

namespace App\Http\Controllers\ProjectManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectManagement\UpdatePreparationStageRequest;
use App\Http\Requests\ProjectManagement\UpdateProjectLinkRequest;
use App\Models\Contract;
use App\Models\ContractPreparationStage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProjectManagementContractController extends Controller
{
    /**
     * Get contract detail for project management (with project_link and 7 stages).
     * GET /api/project_management/contracts/{id}
     */
    public function show(int $id): JsonResponse
    {
        $contract = Contract::with(['preparationStages', 'info', 'projectMedia', 'teams'])
            ->findOrFail($id);

        Gate::authorize('view', $contract);

        $stages = $contract->preparationStages->keyBy('stage_number');
        $stagesList = [];
        for ($n = 1; $n <= ContractPreparationStage::TOTAL_STAGES; $n++) {
            $stage = $stages->get($n);
            $stagesList[] = [
                'stage_number' => $n,
                'label_ar' => ContractPreparationStage::STAGE_LABELS_AR[$n],
                'document_link' => $stage?->document_link,
                'entry_date' => $stage?->entry_date?->format('Y-m-d'),
                'completed' => $stage?->isCompleted() ?? false,
                'completed_at' => $stage?->completed_at?->toIso8601String(),
            ];
        }

        $completedCount = $contract->preparationStages->whereNotNull('completed_at')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $contract->id,
                'project_name' => $contract->project_name,
                'developer_name' => $contract->developer_name,
                'city' => $contract->city,
                'district' => $contract->district,
                'status' => $contract->status,
                'notes' => $contract->notes,
                'project_image_url' => $contract->project_image_url,
                'project_link' => $contract->project_link,
                'preparation_progress' => [
                    'completed_count' => $completedCount,
                    'total' => ContractPreparationStage::TOTAL_STAGES,
                    'percent' => (int) round(($completedCount / ContractPreparationStage::TOTAL_STAGES) * 100),
                ],
                'stages' => $stagesList,
                'info' => $contract->info ? [
                    'agreement_duration_days' => $contract->info->agreement_duration_days,
                    'agency_number' => $contract->info->agency_number,
                ] : null,
                'teams' => $contract->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
            ],
        ], 200);
    }

    /**
     * Update project link.
     * PATCH /api/project_management/contracts/{id}/project-link
     */
    public function updateProjectLink(UpdateProjectLinkRequest $request, int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);
        $contract->update(['project_link' => $request->validated()['project_link'] ?? null]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث رابط المشروع بنجاح',
            'data' => ['project_link' => $contract->project_link],
        ], 200);
    }

    /**
     * Update a preparation stage (document link, entry date, optionally mark complete).
     * PATCH /api/project_management/contracts/{id}/stages/{stageNumber}
     */
    public function updateStage(UpdatePreparationStageRequest $request, int $id, int $stageNumber): JsonResponse
    {
        if ($stageNumber < 1 || $stageNumber > ContractPreparationStage::TOTAL_STAGES) {
            return response()->json([
                'success' => false,
                'message' => 'رقم المرحلة غير صالح (1–7)',
            ], 422);
        }

        $contract = Contract::findOrFail($id);
        Gate::authorize('update', $contract);

        $stage = ContractPreparationStage::firstOrCreate(
            [
                'contract_id' => $id,
                'stage_number' => $stageNumber,
            ],
            [
                'document_link' => null,
                'entry_date' => null,
                'completed_at' => null,
            ]
        );

        $data = $request->validated();
        $update = [];
        if (array_key_exists('document_link', $data)) {
            $update['document_link'] = $data['document_link'];
        }
        if (array_key_exists('entry_date', $data)) {
            $update['entry_date'] = $data['entry_date'];
        }
        if (!empty($data['mark_complete'])) {
            $update['completed_at'] = $stage->completed_at ?? now();
        }
        $stage->update($update);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المرحلة بنجاح',
            'data' => [
                'stage_number' => $stage->stage_number,
                'label_ar' => ContractPreparationStage::STAGE_LABELS_AR[$stage->stage_number],
                'document_link' => $stage->document_link,
                'entry_date' => $stage->entry_date?->format('Y-m-d'),
                'completed' => $stage->isCompleted(),
                'completed_at' => $stage->completed_at?->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * Download contract as PDF (تحميل العقد).
     * GET /api/project_management/contracts/{id}/export
     */
    public function export(int $id): Response
    {
        $contract = Contract::with('info')->findOrFail($id);
        Gate::authorize('view', $contract);

        $pdf = Pdf::loadView('pdfs.project_management_contract', [
            'contract' => $contract,
        ]);
        $filename = 'contract_' . $id . '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }
}
