<?php

namespace App\Services\Sales;

use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Services\Notifications\TwilioWhatsAppService;
use App\Services\Pdf\PdfFactory;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UnitDeveloperPackageService
{
    public function __construct(
        private TwilioWhatsAppService $whatsAppService,
    ) {}

    public function send(ContractUnit $unit, array $payments, ?string $message = null): array
    {
        $unit->loadMissing([
            'contract.city',
            'contract.district',
            'contract.info',
            'contract.secondPartyData',
            'salesReservations.paymentInstallments',
        ]);

        $contract = $unit->contract;
        if (!$contract) {
            throw new RuntimeException('Unit must belong to a contract');
        }
        if (blank($contract->developer_number)) {
            throw new RuntimeException('Contract developer number is missing');
        }

        $reservation = $unit->salesReservations
            ->whereIn('status', ['under_negotiation', 'confirmed'])
            ->sortByDesc('created_at')
            ->first();

        $payments = $this->normalizePayments($payments);
        $payload = [
            'unit' => $unit,
            'contract' => $contract,
            'contractInfo' => $contract->info,
            'secondPartyData' => $contract->secondPartyData,
            'reservation' => $reservation,
            'payments' => $payments,
            'generated_at' => now()->format('Y-m-d H:i'),
        ];

        $filename = sprintf(
            'developer_unit_%s_%s.pdf',
            preg_replace('/[^A-Za-z0-9\-_]/', '_', (string) ($unit->unit_number ?? $unit->id)),
            now()->format('Y-m-d_His')
        );
        $path = 'sales/developer-packages/' . $filename;

        Storage::disk('public')->put($path, PdfFactory::output('pdfs.sales_unit_developer_package', $payload));

        $pdfUrl = url('/storage/' . ltrim($path, '/'));
        $sid = $this->whatsAppService->send(
            (string) $contract->developer_number,
            $message ?: $this->defaultMessage($unit, $contract, $reservation),
            $pdfUrl
        )->sid;

        return [
            'unit_id' => $unit->id,
            'contract_id' => $contract->id,
            'developer_number' => $contract->developer_number,
            'reservation_id' => $reservation?->id,
            'pdf_path' => $path,
            'pdf_url' => $pdfUrl,
            'whatsapp_sid' => $sid,
        ];
    }

    private function normalizePayments(array $payments): array
    {
        return array_values(array_map(fn (array $payment) => [
            'payment' => (float) $payment['payment'],
            'date' => $payment['date'] ?? null,
        ], $payments));
    }

    private function defaultMessage(ContractUnit $unit, $contract, ?SalesReservation $reservation): string
    {
        $parts = [
            'Unit developer package',
            'Project: ' . ($contract->project_name ?? '-'),
            'Unit: ' . ($unit->unit_number ?? $unit->id),
        ];

        if ($reservation) {
            $parts[] = 'Reservation: #' . $reservation->id;
            $parts[] = 'Client: ' . ($reservation->client_name ?? '-');
        }

        return implode("\n", $parts);
    }
}
