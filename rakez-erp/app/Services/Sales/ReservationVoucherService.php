<?php

namespace App\Services\Sales;

use App\Models\SalesReservation;
use App\Services\Pdf\PdfFactory;
use Illuminate\Support\Facades\Storage;

class ReservationVoucherService
{
    /**
     * View data for `reservations.voucher` Blade. Prefers live relations; falls back to snapshot when a field is empty.
     */
    public function payloadForVoucherBlade(SalesReservation $reservation): array
    {
        $reservation->loadMissing([
            'contract.city',
            'contract.district',
            'contractUnit',
            'marketingEmployee.team',
        ]);

        $snapshot = is_array($reservation->snapshot) ? $reservation->snapshot : [];
        $pSnap = $snapshot['project'] ?? [];
        $uSnap = $snapshot['unit'] ?? [];
        $eSnap = $snapshot['employee'] ?? [];

        $c = $reservation->contract;
        $u = $reservation->contractUnit;
        $e = $reservation->marketingEmployee;

        $strOrSnap = static function (?string $live, array $snap, string $key): string {
            $v = (string) ($live ?? '');
            if ($v !== '') {
                return $v;
            }

            return (string) ($snap[$key] ?? '');
        };

        $project = [
            'name' => $strOrSnap($c?->project_name !== null ? (string) $c->project_name : null, $pSnap, 'name'),
            'city' => $strOrSnap($c?->city?->name, $pSnap, 'city'),
            'district' => $strOrSnap($c?->district?->name, $pSnap, 'district'),
            'developer_name' => $strOrSnap($c?->developer_name !== null ? (string) $c->developer_name : null, $pSnap, 'developer_name'),
        ];

        $unit = [
            'number' => $u && ($u->unit_number ?? '') !== '' && $u->unit_number !== null
                ? (string) $u->unit_number
                : (string) ($uSnap['number'] ?? ''),
            'type' => $u && ($u->unit_type ?? '') !== ''
                ? (string) $u->unit_type
                : (string) ($uSnap['type'] ?? ''),
            'area' => $u && $u->area !== null && (string) $u->area !== ''
                ? (string) $u->area
                : (string) ($uSnap['area'] ?? ''),
            'floor' => $u && $u->floor !== null
                ? (string) $u->floor
                : (string) ($uSnap['floor'] ?? ''),
            'price' => $u && $u->price !== null
                ? (float) $u->price
                : (float) ($uSnap['price'] ?? 0),
        ];

        $employee = [
            'name' => ($e?->name ?? '') !== ''
                ? (string) $e->name
                : (string) ($eSnap['name'] ?? ''),
            'team' => ($e?->team?->name ?? '') !== ''
                ? (string) $e->team->name
                : (string) ($eSnap['team'] ?? ''),
        ];

        return [
            'reservation' => $reservation,
            'project' => $project,
            'unit' => $unit,
            'employee' => $employee,
        ];
    }

    /**
     * Generate PDF voucher for a reservation (project/unit/employee from snapshot).
     */
    public function generate(SalesReservation $reservation): string
    {
        $data = [
            'reservation' => $reservation,
            'project' => $reservation->snapshot['project'] ?? [],
            'unit' => $reservation->snapshot['unit'] ?? [],
            'employee' => $reservation->snapshot['employee'] ?? [],
        ];

        $filename = "reservation_{$reservation->id}_voucher.pdf";
        $path = "reservations/{$filename}";
        
        Storage::disk('public')->put($path, PdfFactory::output('reservations.voucher', $data));
        
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
