<?php

namespace App\Services\Sales;

use App\Models\Commission;
use App\Models\Deposit;
use App\Services\Pdf\PdfFactory;
use Illuminate\Support\Facades\Storage;

class PdfGeneratorService
{
    /**
     * Generate commission claim PDF.
     */
    public function generateCommissionClaimPdf(Commission $commission): string
    {
        $commission->load(['contractUnit', 'salesReservation', 'distributions.recipient']);

        $data = [
            'commission' => $commission,
            'distributions' => $commission->distributions,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        $filename = "commission_claim_{$commission->id}_" . time() . ".pdf";
        $path = "commissions/claims/{$filename}";
        
        Storage::disk('public')->put($path, PdfFactory::output('pdfs.commission-claim', $data));
        
        return $path;
    }

    /**
     * Generate deposit claim PDF.
     */
    public function generateDepositClaimPdf(Deposit $deposit): string
    {
        $deposit->load(['salesReservation', 'contract', 'contractUnit', 'confirmedBy']);

        $data = [
            'deposit' => $deposit,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        $filename = "deposit_claim_{$deposit->id}_" . time() . ".pdf";
        $path = "deposits/claims/{$filename}";
        
        Storage::disk('public')->put($path, PdfFactory::output('pdfs.deposit-claim', $data));
        
        return $path;
    }
}
