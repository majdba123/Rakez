<?php

namespace App\Services\Sales;

use App\Models\SalesReservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReservationVoucherService
{
    /**
     * Generate PDF voucher for a reservation.
     */
    public function generate(SalesReservation $reservation): string
    {
        $data = [
            'reservation' => $reservation,
            'project' => $reservation->snapshot['project'] ?? [],
            'unit' => $reservation->snapshot['unit'] ?? [],
            'employee' => $reservation->snapshot['employee'] ?? [],
        ];

        $pdf = Pdf::loadView('reservations.voucher', $data);
        
        $filename = "reservation_{$reservation->id}_voucher.pdf";
        $path = "reservations/{$filename}";
        
        Storage::disk('public')->put($path, $pdf->output());
        
        return $path;
    }

    /**
     * Get voucher file path for a reservation.
     */
    public function getVoucherPath(SalesReservation $reservation): ?string
    {
        return $reservation->voucher_pdf_path;
    }

    /**
     * Get full storage path for voucher.
     */
    public function getVoucherStoragePath(SalesReservation $reservation): ?string
    {
        if (!$reservation->voucher_pdf_path) {
            return null;
        }

        return Storage::disk('public')->path($reservation->voucher_pdf_path);
    }
}
