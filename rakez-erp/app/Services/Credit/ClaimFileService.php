<?php

namespace App\Services\Credit;

use App\Models\SalesReservation;
use App\Models\ClaimFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\Pdf\PdfFactory;
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

        $filename = sprintf(
            'claim_files/claim_%d_%s.pdf',
            $claimFile->id,
            now()->format('Ymd_His')
        );

        Storage::disk('public')->put($filename, PdfFactory::output('claim.file', $data));

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
            // Project Information (5.1 البيانات المعروضة)
            'project_name' => $contract?->project_name ?? $info?->project_name ?? null,
            'project_location' => $contract?->city ?? null,
            'project_district' => $contract?->district ?? null,

            // Unit Information
            'unit_number' => $unit?->unit_number ?? null,
            'unit_type' => $unit?->unit_type ?? null,
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
     * List claim files (for Tab 5: إصدار ملف المطالبة والإفراغات).
     */
    public function listClaimFiles(int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return ClaimFile::with([
                'reservation.contract.info',
                'reservation.contractUnit',
                'reservations',
                'generatedBy',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get claim file by ID.
     */
    public function getClaimFile(int $claimFileId): ClaimFile
    {
        return ClaimFile::with([
                'reservation.contract',
                'reservation.contractUnit',
                'reservations',
                'generatedBy',
            ])
            ->findOrFail($claimFileId);
    }

    /**
     * Get claim file for a reservation.
     */
    public function getClaimFileByReservation(int $reservationId): ?ClaimFile
    {
        return ClaimFile::where('sales_reservation_id', $reservationId)->first();
    }

    /**
     * List sold reservations that do not yet have a claim file (candidates for create).
     * Excludes reservations with individual claim files and those already in combined claim files.
     */
    public function listClaimFileCandidates(int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return SalesReservation::with(['contract.info', 'contractUnit'])
            ->where('credit_status', 'sold')
            ->whereDoesntHave('claimFile')
            ->whereNotIn('id', function ($query) {
                $query->select('sales_reservation_id')
                    ->from('claim_file_reservations');
            })
            ->orderBy('confirmed_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Generate claim files for multiple reservations (bulk). Skips invalid/duplicate; returns created and errors.
     *
     * @return array{created: array<int, int>, errors: array<int, string>} reservation_id => claim_file_id or error message
     */
    public function generateClaimFilesBulk(array $reservationIds, User $user): array
    {
        $created = [];
        $errors = [];

        foreach ($reservationIds as $reservationId) {
            $id = is_numeric($reservationId) ? (int) $reservationId : 0;
            if ($id <= 0) {
                $errors[$id] = 'معرف حجز غير صالح';
                continue;
            }
            if ($this->getClaimFileByReservation($id)) {
                $errors[$id] = 'ملف مطالبة موجود مسبقاً لهذا الحجز';
                continue;
            }
            try {
                $claimFile = $this->generateClaimFile($id, $user);
                $created[$id] = $claimFile->id;
            } catch (Exception $e) {
                $errors[$id] = $e->getMessage();
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * Generate a single combined claim file from multiple reservations.
     * All reservations must be sold and belong to the same contract.
     */
    public function generateCombinedClaimFile(
        array $reservationIds,
        string $claimType,
        ?string $notes,
        User $user
    ): ClaimFile {
        $reservations = SalesReservation::with([
            'contract.info',
            'contractUnit',
            'marketingEmployee.team',
            'titleTransfer',
        ])->whereIn('id', $reservationIds)->get();

        if ($reservations->count() !== count($reservationIds)) {
            $missing = array_diff($reservationIds, $reservations->pluck('id')->all());
            throw new Exception('حجوزات غير موجودة: ' . implode(', ', $missing));
        }

        $contractIds = $reservations->pluck('contract_id')->unique();
        if ($contractIds->count() > 1) {
            throw new Exception('جميع الحجوزات يجب أن تكون من نفس العقد/المشروع');
        }

        foreach ($reservations as $reservation) {
            if ($reservation->credit_status !== 'sold') {
                throw new Exception(
                    "الحجز {$reservation->id} ليس بحالة مباع (credit_status = sold)"
                );
            }
        }

        $alreadyClaimed = ClaimFile::whereIn('sales_reservation_id', $reservationIds)->exists();
        if ($alreadyClaimed) {
            throw new Exception('بعض الحجوزات لديها ملف مطالبة فردي مسبقاً');
        }

        $alreadyCombined = DB::table('claim_file_reservations')
            ->whereIn('sales_reservation_id', $reservationIds)
            ->exists();
        if ($alreadyCombined) {
            throw new Exception('بعض الحجوزات مضمّنة مسبقاً في ملف مطالبة مجمع');
        }

        DB::beginTransaction();
        try {
            $items = [];
            $totalClaimAmount = 0;
            $totalUnitPrice = 0;

            foreach ($reservations as $reservation) {
                $snapshot = $this->buildFileData($reservation);
                $items[] = $snapshot;

                $price = (float) ($snapshot['unit_price'] ?? 0);
                $commission = (float) ($snapshot['brokerage_commission_percent'] ?? 0);
                $totalUnitPrice += $price;
                $totalClaimAmount += ($commission > 0 && $price > 0)
                    ? round($price * $commission / 100, 2)
                    : 0;
            }

            $contract = $reservations->first()->contract;
            $fileData = [
                'summary' => [
                    'contract_id' => $contract?->id,
                    'project_name' => $contract?->project_name ?? $contract?->info?->project_name,
                    'reservation_count' => count($reservations),
                    'total_unit_price' => round($totalUnitPrice, 2),
                    'total_claim_amount' => round($totalClaimAmount, 2),
                ],
                'items' => $items,
            ];

            $claimFile = ClaimFile::create([
                'sales_reservation_id' => null,
                'generated_by' => $user->id,
                'is_combined' => true,
                'claim_type' => $claimType,
                'notes' => $notes,
                'total_claim_amount' => round($totalClaimAmount, 2),
                'file_data' => $fileData,
            ]);

            $claimFile->reservations()->attach($reservationIds);

            DB::commit();

            return $claimFile->fresh(['reservations', 'generatedBy']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check if a reservation is already part of any combined claim file.
     */
    public function isReservationInCombinedClaim(int $reservationId): bool
    {
        return DB::table('claim_file_reservations')
            ->where('sales_reservation_id', $reservationId)
            ->exists();
    }
}



