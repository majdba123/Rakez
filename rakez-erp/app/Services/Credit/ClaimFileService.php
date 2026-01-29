<?php

namespace App\Services\Credit;

use App\Models\SalesReservation;
use App\Models\ClaimFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class ClaimFileService
{
    /**
     * Generate a claim file for a reservation.
     */
    public function generateClaimFile(int $reservationId, User $user): ClaimFile
    {
        $reservation = SalesReservation::with([
            'contract.info',
            'contractUnit',
            'marketingEmployee.team',
            'titleTransfer',
        ])->findOrFail($reservationId);

        // Validate reservation is sold
        if ($reservation->credit_status !== 'sold') {
            throw new Exception('يمكن إنشاء ملف المطالبة فقط للمشاريع المباعة');
        }

        DB::beginTransaction();
        try {
            // Build file data snapshot
            $fileData = $this->buildFileData($reservation);

            $claimFile = ClaimFile::create([
                'sales_reservation_id' => $reservationId,
                'generated_by' => $user->id,
                'file_data' => $fileData,
            ]);

            DB::commit();

            return $claimFile->fresh(['reservation', 'generatedBy']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate PDF for a claim file.
     */
    public function generatePdf(int $claimFileId): string
    {
        $claimFile = ClaimFile::with(['reservation', 'generatedBy'])->findOrFail($claimFileId);

        $data = [
            'claim_file' => $claimFile,
            'file_data' => $claimFile->file_data,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        $pdf = Pdf::loadView('claim.file', $data);

        $filename = sprintf(
            'claim_files/claim_%d_%s.pdf',
            $claimFile->id,
            now()->format('Ymd_His')
        );

        Storage::disk('public')->put($filename, $pdf->output());

        $claimFile->update(['pdf_path' => $filename]);

        return $filename;
    }

    /**
     * Build file data snapshot from reservation.
     */
    protected function buildFileData(SalesReservation $reservation): array
    {
        $contract = $reservation->contract;
        $unit = $reservation->contractUnit;
        $info = $contract?->info;

        return [
            // Project Information
            'project_name' => $contract?->project_name ?? $info?->project_name ?? null,
            'project_location' => $contract?->city ?? null,

            // Unit Information
            'unit_number' => $unit?->unit_number ?? null,
            'unit_type' => $unit?->type ?? null,
            'unit_area' => $unit?->area ?? null,
            'unit_price' => $unit?->price ?? null,

            // Client Information
            'client_name' => $reservation->client_name,
            'client_mobile' => $reservation->client_mobile,
            'client_nationality' => $reservation->client_nationality,
            'client_iban' => $reservation->client_iban,

            // Financial Details
            'down_payment_amount' => $reservation->down_payment_amount,
            'down_payment_status' => $reservation->down_payment_status,
            'payment_method' => $reservation->payment_method,
            'purchase_mechanism' => $reservation->purchase_mechanism,
            'brokerage_commission_percent' => $reservation->brokerage_commission_percent,
            'commission_payer' => $reservation->commission_payer,
            'tax_amount' => $reservation->tax_amount,

            // Marketing Details
            'team_name' => $reservation->marketingEmployee?->team?->name,
            'marketer_name' => $reservation->marketingEmployee?->name,

            // Dates
            'contract_date' => $reservation->contract_date?->format('Y-m-d'),
            'confirmed_at' => $reservation->confirmed_at?->format('Y-m-d H:i:s'),
            'title_transfer_date' => $reservation->titleTransfer?->completed_date?->format('Y-m-d'),

            // Reservation Details
            'reservation_id' => $reservation->id,
            'reservation_type' => $reservation->reservation_type,
        ];
    }

    /**
     * Get claim file by ID.
     */
    public function getClaimFile(int $claimFileId): ClaimFile
    {
        return ClaimFile::with(['reservation.contract', 'reservation.contractUnit', 'generatedBy'])
            ->findOrFail($claimFileId);
    }

    /**
     * Get claim file for a reservation.
     */
    public function getClaimFileByReservation(int $reservationId): ?ClaimFile
    {
        return ClaimFile::where('sales_reservation_id', $reservationId)->first();
    }
}

