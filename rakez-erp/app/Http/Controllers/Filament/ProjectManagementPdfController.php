<?php

namespace App\Http\Controllers\Filament;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ExclusiveProjectRequest;
use App\Models\User;
use App\Services\ExclusiveProjectService;
use App\Services\Governance\GovernanceAccessService;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\Pdf\ContractPdfDataService;
use App\Services\Pdf\PdfFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectManagementPdfController extends Controller
{
    public function __construct(
        protected ContractPdfDataService $contractPdfDataService,
        protected ExclusiveProjectService $exclusiveProjectService,
        protected GovernanceAccessService $governanceAccessService,
        protected GovernanceAuditLogger $governanceAuditLogger,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function contractInfo(int $contractId): Response
    {
        $this->authorizeGovernancePdf('contracts.view_all');

        $contract = Contract::with('info')->findOrFail($contractId);
        $this->authorize('view', $contract);

        abort_if(! $contract->info, 404, 'Contract info not found.');

        $data = $this->contractPdfDataService->buildContractInfoOnlyPdfPayload($contract->info);
        $filename = sprintf('contract_info_%d_%s.pdf', $contract->id, now()->format('Y-m-d'));
        $this->auditPdfDownload($contract, 'contract_info', ['filename' => $filename]);

        return PdfFactory::download('pdfs.contract_info_only', $data, $filename);
    }

    /**
     * @throws AuthorizationException
     */
    public function secondParty(int $contractId): Response
    {
        $this->authorizeGovernancePdf('contracts.view_all');

        $contract = Contract::with('secondPartyData')->findOrFail($contractId);
        $this->authorize('view', $contract);

        abort_if(! $contract->secondPartyData, 404, 'Second party data not found.');

        $data = $this->contractPdfDataService->buildSecondPartyDataOnlyPdfPayload($contract->secondPartyData);
        $filename = sprintf('second_party_data_%d_%s.pdf', $contract->id, now()->format('Y-m-d'));
        $this->auditPdfDownload($contract, 'second_party_data', ['filename' => $filename]);

        return PdfFactory::download('pdfs.second_party_data_only', $data, $filename);
    }

    public function exclusiveContract(int $requestId): Response|BinaryFileResponse
    {
        $this->authorizeGovernancePdf('exclusive_projects.view');

        $request = ExclusiveProjectRequest::findOrFail($requestId);

        $path = $request->contract_pdf_path ?: $this->exclusiveProjectService->exportContract($request->id);
        abort_unless(is_string($path) && $path !== '', 404, 'Exclusive contract PDF is not available.');
        $this->auditPdfDownload($request, 'exclusive_contract', ['storage_path' => $path]);

        return response()->download(Storage::path($path));
    }

    private function authorizeGovernancePdf(string $permission): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);
        abort_unless($this->governanceAccessService->allows($user, $permission), 403);
    }

    private function auditPdfDownload(Model $subject, string $documentType, array $metadata = []): void
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User, 403);

        $this->governanceAuditLogger->log('governance.projects.pdf.downloaded', $subject, [
            'document_type' => $documentType,
            'metadata' => $metadata,
        ], $actor);
    }
}
